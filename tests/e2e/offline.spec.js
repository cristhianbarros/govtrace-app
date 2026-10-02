// Iteración 30 — US-018 de extremo a extremo (R-TST-03): en un Chromium de
// verdad, contra la app de make up, el teléfono pierde la señal. El reporte
// queda en IndexedDB, la app abre igual sin conexión (Service Worker) y,
// al volver la señal, el reporte sube solo y aparece en "Mis Reportes".
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { attachPhoto } from './flujos/support.js';

const PHOTO = fileURLToPath(new URL('../fixtures/evidence/foto.jpg', import.meta.url));
const SAVED = '📵 Sin conexión. Reporte guardado en el dispositivo. Se enviará automáticamente cuando recupere la señal.';

test('Guardado sin conexión y envío automático al recuperar la señal', async ({ page, context }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('e2e.veedor@correo.co');
    await page.getByLabel('Contraseña').fill('Veeduria#2026');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL('**/reports/new');

    // Con señal, y con el Service Worker ya activo, la app queda guardada para abrir sin ella.
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.goto('/my-reports');
    await page.goto('/reports/new');

    // El reporte: la obra, el GPS, la clasificación y la foto.
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Retraso').check();
    await attachPhoto(page, PHOTO);
    await expect(page.getByRole('button', { name: 'Enviar Reporte' })).toBeEnabled();

    // Se pierde la señal justo al enviar.
    await context.setOffline(true);
    await page.getByRole('button', { name: 'Enviar Reporte' }).click();
    await expect(page.getByText(SAVED)).toBeVisible();

    // Sin señal, la app abre igual y dice cuántos esperan.
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Nuevo Reporte' })).toBeVisible();
    await page.goto('/my-reports');
    await expect(page.getByText('⏳ 1 reporte esperando conexión')).toBeVisible();

    // Vuelve la señal: sube solo y aparece con su estado.
    await context.setOffline(false);
    await expect(page.getByText('⏳ 1 reporte esperando conexión')).toBeHidden({ timeout: 30_000 });
    await expect(page.locator('[data-test="report"]').first()).toContainText('Parque de pruebas sin conexión', { timeout: 30_000 });
    await expect(page.locator('[data-test="report"]').first().locator('[data-test="technical"]')).toHaveText(/En Cola|Sellando|Sellado/);
});
