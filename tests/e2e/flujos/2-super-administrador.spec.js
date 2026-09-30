// It. 40a — el flujo del Super Administrador (docs/mapa-funcional.md, sección 2).
// Gobierna la organización que dio de alta el flujo 1-alta, que corre antes.
import { expect, test } from '@playwright/test';
import { CENTRAL, PEOPLE, logIn, logOut } from './support.js';

const ALTA = 'Veeduría de Alta E2E';
const PANEL = [
    ['/admin/parameters', 'Parámetros'],
    ['/admin/audit', 'Registro de auditoría'],
    ['/admin/secop-health', 'Salud de SECOP II'],
    ['/admin/sealing', 'Sellado'],
    ['/admin/usage', 'Uso'],
];

test.describe.configure({ mode: 'serial' });

async function enterThePanel(page) {
    await logIn(page, CENTRAL, PEOPLE.superAdmin);
    await page.waitForURL('**/admin/organizations');
}

function rowOf(page, name) {
    return page.locator('[data-test="organization-row"]').filter({ hasText: name });
}

test('Entra al panel global, a las organizaciones', async ({ page }) => {
    await enterThePanel(page);

    await expect(page.getByRole('heading', { name: 'Organizaciones', exact: true })).toBeVisible();
    await expect(rowOf(page, ALTA)).toBeVisible();
});

test('Corrige el NIT de una organización a solicitud formal', async ({ page }) => {
    await enterThePanel(page);
    const row = rowOf(page, ALTA);

    await row.getByRole('button', { name: 'Editar NIT' }).click();
    await row.getByLabel('NIT', { exact: true }).fill('901555999-2');
    await row.getByRole('button', { name: 'Guardar NIT' }).click();

    await expect(page.getByText('El NIT ha sido actualizado.')).toBeVisible();
    await expect(row).toContainText('901555999-2');
});

test.fixme('V8: cambia la razón social de una organización a solicitud formal', async () => {});

test('Suspende una organización y la reactiva', async ({ page }) => {
    await enterThePanel(page);
    const row = rowOf(page, ALTA);

    await row.getByRole('button', { name: 'Suspender' }).click();
    await row.getByRole('button', { name: 'Confirmar suspensión' }).click();
    await expect(row).toContainText('Suspendida');

    await row.getByRole('button', { name: 'Reactivar' }).click();
    await expect(row).toContainText('Activa');
});

test.fixme('V3: asigna otro Administrador a una organización, o reemplaza al que tiene', async () => {});

test('Opera la plataforma: parámetros, auditoría, SECOP, sellado y uso', async ({ page }) => {
    await enterThePanel(page);

    for (const [path, title] of PANEL) {
        await page.goto(`${CENTRAL}${path}`);
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    }
});

test.fixme('V7: crea un reporte en nombre de una organización que lo autorizó', async () => {});

test.fixme('V15: sincroniza SECOP a mano', async () => {});

test('Cierra la sesión desde el menú de su cuenta (V1)', async ({ page }) => {
    await enterThePanel(page);
    await expect(page.locator('header')).toContainText('Super Administrador E2E');

    await logOut(page);

    await page.goto(`${CENTRAL}/admin/organizations`);
    await page.waitForURL('**/login');
});
