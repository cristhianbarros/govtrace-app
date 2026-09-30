// It. 40a — make ux-check: el checkpoint base de la interfaz (docs/ux-analisis.md).
// Recorre cada pantalla de cada rol en un celular y en un computador, guarda su
// captura (storage/framework/testing/ux/shots) y mide lo mismo que el análisis:
// axe (WCAG 2.2 AA), el tamaño de la letra y el de los botones. Falla si una
// pantalla empeora respecto de la línea base (tests/ux/baseline.json). Después
// de una mejora, make ux-baseline la reescribe, y el cambio se revisa en el diff.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { CENTRAL, ORG, PEOPLE, logIn } from '../e2e/flujos/support.js';

const require = createRequire(import.meta.url);
const AXE = require.resolve('axe-core/axe.min.js');
const SHOTS = fileURLToPath(new URL('../../storage/framework/testing/ux/shots/', import.meta.url));
const MEASURES = fileURLToPath(new URL('../../storage/framework/testing/ux/medidas.json', import.meta.url));
const BASELINE = fileURLToPath(new URL('./baseline.json', import.meta.url));
const PHOTO = fileURLToPath(new URL('../fixtures/evidence/foto.jpg', import.meta.url));

// Cuánto puede empeorar una pantalla antes de fallar, en puntos porcentuales:
// un nombre más largo o una fila más no es una regresión.
const TOLERANCE = { textUnder16: 5, textUnder14: 5, smallTargets: 10 };
// Pantallas que dependen de algo fuera del fixture (la red Stellar, el historial
// de SECOP): se fotografían, pero no se comparan.
const NOT_COMPARED = ['superadmin-sellado', 'superadmin-secop'];

const VIEWPORTS = {
    movil: { viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 },
    escritorio: { viewport: { width: 1366, height: 768 }, deviceScaleFactor: 1 },
};

const TARGETS = 'a[href], button, input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select, textarea, summary, [role=button], [role=tab], label:has(input[type=radio]), label:has(input[type=checkbox])';

/** What the analysis measured, on the screen as it is now. */
async function measure(page) {
    // La barra de abajo es fija en el celular: según dónde esté desplazada la
    // página, tapa un rato lo que pasa por debajo, y axe lo cuenta como un
    // botón tapado (target-size), aunque basta desplazarse. Para medir, se deja
    // al final de la página; la captura ya se tomó como la ve la persona.
    await page.addStyleTag({ content: 'nav[aria-label="Navegación"] { position: static !important; }' });
    await page.addScriptTag({ path: AXE });
    const axe = await page.evaluate(async () => {
        const result = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        return Object.fromEntries(result.violations.map((violation) => [violation.id, violation.nodes.length]));
    });
    const layout = await page.evaluate((selector) => {
        const visible = (element) => {
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return box.width > 2 && box.height > 2 && style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) > 0;
        };
        let characters = 0;
        let under16 = 0;
        let under14 = 0;
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const text = walker.currentNode.textContent.trim();
            const element = walker.currentNode.parentElement;
            if (!text || !element || !visible(element) || element.closest('.leaflet-tile-pane, .leaflet-control-attribution')) {
                continue;
            }
            const size = parseFloat(getComputedStyle(element).fontSize);
            characters += text.length;
            under16 += size < 16 ? text.length : 0;
            under14 += size < 14 ? text.length : 0;
        }
        const targets = [...document.querySelectorAll(selector)].filter(visible);
        const small = targets.filter((target) => {
            const box = target.getBoundingClientRect();
            return box.width < 44 || box.height < 44;
        });
        const percent = (part, whole) => (whole === 0 ? 0 : Math.round((100 * part) / whole));
        return { textUnder16: percent(under16, characters), textUnder14: percent(under14, characters), smallTargets: percent(small.length, targets.length), targets: targets.length };
    }, TARGETS);

    return { axe, ...layout };
}

async function visit(page, viewport, name, url) {
    if (url) {
        await page.goto(url);
    }
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${SHOTS}${viewport}-${name}.png`, fullPage: true });

    const measures = existsSync(MEASURES) ? JSON.parse(readFileSync(MEASURES, 'utf8')) : {};
    measures[`${viewport}/${name}`] = await measure(page);
    writeFileSync(MEASURES, JSON.stringify(measures, null, 2));
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
    mkdirSync(SHOTS, { recursive: true });
    writeFileSync(MEASURES, '{}');
});

for (const [viewport, options] of Object.entries(VIEWPORTS)) {
    test.describe(viewport, () => {
        test.use(options);

        test(`público, ${viewport}`, async ({ page }) => {
            for (const [name, url] of [
                ['publico-inicio', `${CENTRAL}/`],
                ['publico-mapa', `${ORG}/`],
                ['publico-obra', `${ORG}/worksite/1`],
                ['publico-estadisticas', `${ORG}/stats`],
                ['publico-validador', `${ORG}/verify`],
                ['publico-entrar-veeduria', `${ORG}/login`],
                ['publico-entrar-central', `${CENTRAL}/login`],
                ['publico-privacidad', `${ORG}/privacidad`], // it. 44e
            ]) {
                await visit(page, viewport, name, url);
            }
        });

        test(`veedor, ${viewport}`, async ({ page }) => {
            await logIn(page, ORG, PEOPLE.veedor);
            await page.waitForURL('**/reports/new');
            await visit(page, viewport, 'veedor-nuevo-reporte');

            await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
            await page.locator('[data-test="contract-result"]').first().click();
            await page.locator('input[type="file"]').first().setInputFiles(PHOTO);
            await visit(page, viewport, 'veedor-reporte-armado');

            await visit(page, viewport, 'veedor-mis-reportes', `${ORG}/my-reports`);
        });

        // It. 44c: quien ya tenía cuenta declara sus impedimentos antes de reportar.
        test(`veedora sin declarar, ${viewport}`, async ({ page }) => {
            await logIn(page, ORG, PEOPLE.undeclared);
            await page.waitForURL('**/declaration');
            await visit(page, viewport, 'veedor-declaracion');
        });

        test(`administrador, ${viewport}`, async ({ page }) => {
            await logIn(page, ORG, PEOPLE.admin);
            await page.waitForURL('**/admin/inbox');
            for (const screen of ['inbox', 'citizen-reports', 'observers', 'territory', 'contracts', 'worksites', 'organization', 'summary', 'audit', 'authorization']) {
                await visit(page, viewport, `admin-${screen}`, `${ORG}/admin/${screen}`);
            }
        });

        test(`super administrador, ${viewport}`, async ({ page }) => {
            await logIn(page, CENTRAL, PEOPLE.superAdmin);
            await page.waitForURL('**/admin/organizations');
            for (const [name, path] of [
                ['superadmin-organizaciones', 'organizations'],
                ['superadmin-nueva-organizacion', 'organizations/new'],
                ['superadmin-parametros', 'parameters'],
                ['superadmin-auditoria', 'audit'],
                ['superadmin-secop', 'secop-health'],
                ['superadmin-sellado', 'sealing'],
                ['superadmin-uso', 'usage'],
            ]) {
                await visit(page, viewport, name, `${CENTRAL}/admin/${path}`);
            }
        });
    });
}

test('ninguna pantalla empeora respecto de la línea base', () => {
    const measures = JSON.parse(readFileSync(MEASURES, 'utf8'));

    if (process.env.UPDATE_BASELINE) {
        const sorted = Object.fromEntries(Object.keys(measures).sort().map((screen) => [screen, measures[screen]]));
        writeFileSync(BASELINE, `${JSON.stringify(sorted, null, 2)}\n`);
        return;
    }

    const baseline = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : {};
    const problems = [];
    for (const [screen, now] of Object.entries(measures)) {
        const before = baseline[screen];
        if (!before) {
            problems.push(`${screen}: pantalla sin línea base (corra make ux-baseline)`);
            continue;
        }
        if (NOT_COMPARED.some((name) => screen.endsWith(`/${name}`))) {
            continue;
        }
        for (const [rule, nodes] of Object.entries(now.axe)) {
            if (!(rule in before.axe)) {
                problems.push(`${screen}: axe, regla nueva "${rule}" (${nodes} elementos)`);
            }
        }
        for (const [metric, tolerance] of Object.entries(TOLERANCE)) {
            if (now[metric] > before[metric] + tolerance) {
                problems.push(`${screen}: ${metric} pasó de ${before[metric]} % a ${now[metric]} %`);
            }
        }
    }

    expect(problems, problems.join('\n')).toEqual([]);
});
