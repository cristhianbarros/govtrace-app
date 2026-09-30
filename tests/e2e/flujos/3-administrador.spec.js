// It. 40a — el flujo del Administrador de Organización (docs/mapa-funcional.md,
// sección 2), en la organización del fixture: dos evidencias esperan en su Bandeja.
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, WORKSITE, logIn, logOut } from './support.js';

test.describe.configure({ mode: 'serial' });

async function enter(page, screen) {
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    if (screen) {
        await page.goto(`${ORG}/admin/${screen}`);
    }
}

test('Entra y llega a su Bandeja, con las evidencias por revisar', async ({ page }) => {
    await enter(page);

    await expect(page.getByRole('heading', { name: 'Bandeja de entrada', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Publicar' })).toHaveCount(2);
});

test('Cada evidencia de la Bandeja dice de qué obra es y qué veedor la envió (V4)', async ({ page }) => {
    await enter(page);
    const card = page.locator('article').first();

    await expect(card.locator('[data-test="worksite"]')).toHaveText(WORKSITE);
    await expect(card).toContainText('Santa Marta');
    await expect(card).toContainText('Enviada por Veedor E2E');
});

test('Publica una evidencia: pasa al mapa', async ({ page }) => {
    await enter(page);

    await page.getByRole('button', { name: 'Publicar' }).first().click();

    await expect(page.getByText('Evidencia publicada. Ya es visible en el mapa.')).toBeVisible();
});

test('Rechaza una evidencia con un motivo para el veedor', async ({ page }) => {
    await enter(page);

    await page.getByRole('button', { name: 'Rechazar' }).first().click();
    await page.getByLabel('Motivo del rechazo (lo verá el veedor)').fill('No se ve el avance que describe.');
    await page.getByRole('button', { name: 'Confirmar rechazo' }).click();

    await expect(page.getByText('Evidencia rechazada. Su veedor verá el motivo.')).toBeVisible();
});

test('Arma su equipo: invita a un veedor, que queda pendiente', async ({ page }) => {
    await enter(page, 'observers');

    await page.getByLabel('Invitar un veedor de campo').fill('e2e.nuevo@correo.co');
    await page.getByRole('button', { name: 'Enviar invitación' }).click();

    await expect(page.getByText('Invitación enviada a e2e.nuevo@correo.co.', { exact: false })).toBeVisible();
    await expect(page.getByText('Invitación pendiente')).toBeVisible();
});

test.fixme('V3: suma a otro Administrador a su organización', async () => {});

test('Ve su territorio, sus contratos y sus obras', async ({ page }) => {
    await enter(page, 'territory');
    await expect(page.getByText('Magdalena').first()).toBeVisible();

    await page.goto(`${ORG}/admin/contracts`);
    await expect(page.getByRole('heading', { name: 'Contratos', exact: true })).toBeVisible();
    await expect(page.getByText('Número de proceso').first()).toBeVisible();

    await page.goto(`${ORG}/admin/worksites`);
    await expect(page.getByText(WORKSITE).first()).toBeVisible();
});

test('Cuida la identidad de la organización y sigue el resumen y la auditoría', async ({ page }) => {
    await enter(page, 'organization');
    await expect(page.getByLabel('Nombre de fantasía')).toHaveValue('Veeduría de Pruebas E2E');

    await page.goto(`${ORG}/admin/summary`);
    await expect(page.getByRole('heading', { name: 'Resumen del territorio', exact: true })).toBeVisible();

    await page.goto(`${ORG}/admin/audit`);
    await expect(page.getByRole('heading', { name: 'Registro de auditoría', exact: true })).toBeVisible();
    await expect(page.getByText('Publicó una evidencia').first()).toBeVisible();
});

test('Autoriza al Super Administrador por 30 días y revoca la autorización', async ({ page }) => {
    await enter(page, 'authorization');

    await page.getByRole('button', { name: 'Autorizar por 30 días' }).click();
    await expect(page.getByText(/puede crear reportes en nombre de la organización/)).toBeVisible();

    await page.getByRole('button', { name: 'Revocar autorización' }).click();
    await expect(page.getByText(/Autorización revocada\./)).toBeVisible();
});

test.fixme('V9: le llega un aviso de que hay evidencias por revisar', async () => {});

test('Cierra la sesión desde el menú de su cuenta (V1)', async ({ page }) => {
    await enter(page);

    await logOut(page);
});
