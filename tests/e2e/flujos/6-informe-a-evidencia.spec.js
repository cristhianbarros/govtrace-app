// It. 65b — US-068: del informe ciudadano a una evidencia, de punta a punta.
// El ciudadano informa; la Administradora le pide la visita al veedor; el veedor
// la ve en "Nuevo reporte" y reporta la obra desde ahí; el reporte se sella en
// la red local; la Administradora lo publica desde la Bandeja, y al ciudadano le
// llegan los dos correos: que un veedor va a ir, y el enlace a la evidencia.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, latestCodeTo, latestMailAbout, logIn, logOut } from './support.js';

const NO_FACE = fileURLToPath(new URL('../../fixtures/evidence/sin-rostro.jpg', import.meta.url));

test.describe.configure({ mode: 'serial' });

const citizen = `e2e.visita.${Date.now()}@correo.co`;
const MESSAGE = `La cancha del parque quedó a medio pintar (${Date.now()}).`;

test('El ciudadano informa, y la Administradora le pide la visita al veedor sugerido', async ({ page }) => {
    await page.goto(ORG);
    await page.locator('.leaflet-marker-icon').first().click();
    await page.locator('[data-test="worksite-card"]').getByRole('link', { name: 'Ver la obra' }).click();
    await page.waitForURL('**/worksite/*');
    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await expect(page.getByRole('status')).toContainText('Le enviamos un código de 6 dígitos');
    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await page.getByLabel('¿Qué vio en la obra?').fill(MESSAGE);
    await page.getByRole('button', { name: 'Enviar a la veeduría' }).click();
    await expect(page.getByRole('status')).toContainText('Su informe llegó a la veeduría.');

    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${ORG}/admin/citizen-reports`);
    const report = page.locator('[data-test="citizen-report"]').filter({ hasText: MESSAGE });
    await report.getByRole('button', { name: 'Pedir a un veedor que lo documente' }).click();
    // El último que reportó la obra viene elegido.
    await expect(report.locator('select[data-test="visit-observer"] option:checked')).toHaveText('Veedor E2E');
    await report.getByRole('button', { name: 'Pedir la visita' }).click();
    await expect(page.getByRole('status')).toHaveText('Le pedimos la visita a Veedor E2E. Al ciudadano le avisamos que un veedor va a visitar la obra.');
    expect(await latestMailAbout(citizen, 'va a visitar la obra')).not.toContain('Veedor E2E');
    await logOut(page);
});

test('El veedor reporta la obra desde su visita pendiente', async ({ page }) => {
    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');

    const visit = page.locator('[data-test="visit"]').filter({ hasText: MESSAGE });
    await expect(visit).toBeVisible();
    await visit.getByRole('button', { name: 'Reportar esta obra' }).click();
    await expect(page.locator('[data-test="visit-context"]')).toContainText(MESSAGE);
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Avance').check();
    await page.locator('input[type="file"]').first().setInputFiles(NO_FACE);
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('No encontramos rostros.', { timeout: 30_000 });
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await page.getByRole('button', { name: 'Enviar Reporte' }).click();

    await expect(page.getByText('Reporte recibido con éxito.')).toBeVisible();
    await expect(page.locator('[data-test="visit"]').filter({ hasText: MESSAGE })).toHaveCount(0);
    await logOut(page);
});

test('La Administradora publica la evidencia, el informe queda documentado y al ciudadano le llega el enlace', async ({ page }) => {
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');

    // Llega a la Bandeja cuando la red local lo sella (el worker, unos segundos).
    const evidence = page.locator('article').filter({ has: page.locator('[data-test="citizen-report-answered"]', { hasText: MESSAGE }) });
    await expect(async () => {
        await page.reload();
        await expect(evidence).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 90_000 });
    await evidence.getByRole('button', { name: 'Publicar' }).click();
    await expect(page.getByText('Evidencia publicada. Ya es visible en el mapa.')).toBeVisible();

    await page.goto(`${ORG}/admin/citizen-reports`);
    await page.getByRole('tab', { name: /^Documentados/ }).click();
    await expect(page.locator('[data-test="citizen-report"]').filter({ hasText: MESSAGE })).toContainText('Documentado');

    // El asunto va codificado (MIME); el cuerpo, legible.
    const mail = await latestMailAbout(citizen, 'documentó lo que usted informó');
    expect(mail).toContain('/worksite/');
    expect(mail).not.toContain('Veedor E2E');
});
