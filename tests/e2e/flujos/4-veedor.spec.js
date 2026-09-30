// It. 40a — el flujo del veedor de campo (docs/mapa-funcional.md, sección 2).
// Enviar un reporte, con y sin señal, lo prueba offline.spec.js.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PASSWORD, PEOPLE, WORKSITE, logIn, logOut } from './support.js';

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
    await page.locator('input[type="file"]').first().setInputFiles(PHOTO);

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

test('Cambia su contraseña con la sesión abierta, desde el menú de su cuenta (V11)', async ({ page }) => {
    await enter(page);
    await page.locator('header button[aria-haspopup="menu"]').click();
    await page.getByRole('menuitem', { name: 'Cambiar contraseña' }).click();
    await page.waitForURL('**/account/password');
    await expect(page.getByRole('heading', { name: 'Cambiar contraseña', exact: true })).toBeVisible();

    // La cambia, y la deja como estaba para lo que sigue.
    for (const [current, next] of [[PASSWORD, 'Nueva#Clave2027'], ['Nueva#Clave2027', PASSWORD]]) {
        await page.getByLabel('Contraseña actual').fill(current);
        await page.getByLabel('Nueva contraseña', { exact: true }).fill(next);
        await page.getByLabel('Escriba otra vez la nueva contraseña').fill(next);
        const changed = page.waitForResponse((response) => response.url().endsWith('/account/password') && response.request().method() === 'PUT');
        await page.getByRole('button', { name: 'Cambiar contraseña' }).click();
        expect((await changed).status()).toBe(200);
        await expect(page.getByRole('status')).toHaveText('Su contraseña fue cambiada. La próxima vez entre con la nueva.');
    }
});

test('Cierra la sesión', async ({ page }) => {
    await enter(page);

    await logOut(page);
});
