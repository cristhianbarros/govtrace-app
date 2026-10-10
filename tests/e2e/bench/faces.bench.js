// It. 48 — make faces-bench: cuánto tarda la revisión de rostros de una foto,
// desde que se elige hasta que se ve la revisión, en un Chromium de verdad con
// la CPU frenada como en un celular de gama media (CPU_RATE=4, por defecto).
// No es una prueba: mide, para comparar antes y después de un cambio.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, logIn } from '../flujos/support.js';

const PHOTOS = {
    // 1920 x 1440 px con tres rostros lejanos (it. 46f).
    far: [fileURLToPath(new URL('../../fixtures/evidence/rostros-lejanos.jpg', import.meta.url)), 'Encontramos 3 rostros y los difuminamos.'],
    // 600 x 894 px con un rostro cercano (it. 46e).
    face: [fileURLToPath(new URL('../../fixtures/evidence/rostro-pintura.jpg', import.meta.url)), 'Encontramos 1 rostro y lo difuminamos.'],
};
const CPU_RATE = Number(process.env.CPU_RATE ?? 4);
const RUNS = Number(process.env.RUNS ?? 3);

/** In the page: from the file's "change" to the first frame painted with the review. */
async function review(page, [path, found]) {
    await page.evaluate(() => {
        window.facesBench = new Promise((resolve) => {
            let start = null;
            document.addEventListener('change', () => (start = performance.now()), { capture: true, once: true });
            const observer = new MutationObserver(() => {
                if (start !== null && document.querySelector('[data-test="faces-found"]')) {
                    observer.disconnect();
                    requestAnimationFrame(() => resolve(Math.round(performance.now() - start)));
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });
    });
    await page.locator('input[type="file"]').first().setInputFiles(path);
    await expect(page.locator('[data-test="faces-found"]')).toHaveText(found, { timeout: 120_000 });
    const ms = await page.evaluate(() => window.facesBench);
    await page.getByRole('button', { name: 'No usar esta foto', exact: true }).click();
    return ms;
}

async function openNewReport(page) {
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').filter({ hasText: 'Parque de pruebas' }).first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
}

test('Cuánto tarda la revisión de rostros de una foto', async ({ page }) => {
    test.setTimeout(RUNS * 300_000);
    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');

    const times = { 'la primera, al abrir la pantalla': [], 'otra de 1920 px': [], 'una de 600 px': [] };
    for (let run = 0; run < RUNS; run++) {
        await page.reload();
        await openNewReport(page);
        const cdp = await page.context().newCDPSession(page);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU_RATE });
        // El detector se precarga al abrir la pantalla: la primera foto puede alcanzarlo cargando.
        times['la primera, al abrir la pantalla'].push(await review(page, PHOTOS.far));
        times['otra de 1920 px'].push(await review(page, PHOTOS.far));
        times['una de 600 px'].push(await review(page, PHOTOS.face));
        await cdp.detach();
    }

    const median = (values) => [...values].sort((a, b) => a - b)[Math.floor(values.length / 2)];
    console.log(`\nRevisión de rostros, CPU ${CPU_RATE}x más lenta, ${RUNS} corridas (mediana, y cada corrida):`);
    for (const [photo, values] of Object.entries(times)) {
        console.log(`  ${photo}: ${median(values)} ms  [${values.join(', ')}]`);
    }
});
