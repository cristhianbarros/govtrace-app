// It. 40a — el flujo del veedor de campo (docs/mapa-funcional.md, sección 2).
// Enviar un reporte, con y sin señal, lo prueba offline.spec.js.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, WORKSITE, logIn } from './support.js';

const PHOTO = fileURLToPath(new URL('../../fixtures/evidence/foto.jpg', import.meta.url));

test.describe.configure({ mode: 'serial' });

async function enter(page) {
    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');
}

test('Entra y llega a Nuevo reporte', async ({ page }) => {
    await enter(page);

    await expect(page.getByRole('heading', { name: 'Nuevo Reporte', exact: true })).toBeVisible();
});

test.fixme('V6: instala la app en su celular, con el nombre, el logo y los íconos de su veeduría', async () => {});

test('Arma un reporte: la obra, lo que vio y la foto', async ({ page }) => {
    await enter(page);

    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Avance').check();
    await page.locator('input[type="file"]').setInputFiles(PHOTO);

    await expect(page.getByRole('button', { name: 'Enviar Reporte' })).toBeEnabled();
});

test('Sigue sus reportes: su estado, el motivo de un rechazo y el recibo', async ({ page }) => {
    await enter(page);
    await page.goto(`${ORG}/my-reports`);

    await expect(page.locator('[data-test="report"]').first()).toContainText(WORKSITE);
    await expect(page.getByText('Motivo: La foto no deja ver la obra.', { exact: false })).toBeVisible();

    await page.getByRole('button', { name: 'Ver recibo' }).first().click();
    await expect(page.getByText('Ledger').first()).toBeVisible();
});

test.fixme('V11: cambia su contraseña con la sesión abierta', async () => {});

test('Cierra la sesión', async ({ page }) => {
    await enter(page);

    await page.getByRole('button', { name: 'Salir' }).click();

    await page.waitForURL('**/login');
});
