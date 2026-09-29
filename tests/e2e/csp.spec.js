// Iteración 41 — la CSP en un Chromium de verdad (R-TST-03). Pest ve que la
// cabecera está; solo un navegador ve lo que ella deja cargar: el mapa y sus
// imágenes, Leaflet, el Service Worker, las vistas previas de las fotos. Aquí,
// ninguna pantalla pública ni del veedor provoca una sola violación.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const PHOTO = fileURLToPath(new URL('../fixtures/evidence/foto.jpg', import.meta.url));

/** Every CSP violation of the page, from before its first script runs. */
async function watchViolations(page) {
    const violations = [];
    await page.exposeFunction('reportCspViolation', (violation) => violations.push(violation));
    await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) =>
            window.reportCspViolation(`${event.violatedDirective} bloqueó ${event.blockedURI} (${event.sourceFile}:${event.lineNumber})`),
        );
    });
    page.on('console', (message) => {
        if (/Content Security Policy/i.test(message.text())) {
            violations.push(message.text());
        }
    });

    return violations;
}

test('La CSP no bloquea nada en las pantallas públicas ni en las del veedor', async ({ page }) => {
    const violations = await watchViolations(page);

    // La cabecera está: si no, no habría nada que probar.
    const response = await page.goto('/');
    expect(response.headers()['content-security-policy']).toContain("img-src 'self' data: blob: https://tile.openstreetmap.org");

    // El mapa público pide sus imágenes a OpenStreetMap (que respondan depende de internet; que se pidan, de la CSP).
    await expect(page.locator('img.leaflet-tile').first()).toBeAttached({ timeout: 30_000 });
    await page.goto('/worksite/1');
    await expect(page.getByText('Parque de pruebas sin conexión').first()).toBeVisible();
    await page.goto('/stats');
    await page.goto('/verify');
    await expect(page.getByRole('heading', { name: 'Validador de evidencias' })).toBeVisible();

    // El veedor: su Service Worker, la búsqueda, el GPS y la vista previa de una foto.
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('e2e.veedor@correo.co');
    await page.getByLabel('Contraseña').fill('Veeduria#2026');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL('**/reports/new');
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.locator('input[type="file"]').setInputFiles(PHOTO);
    await expect(page.getByRole('button', { name: 'Enviar Reporte' })).toBeVisible();
    await page.goto('/my-reports');

    expect(violations).toEqual([]);
});
