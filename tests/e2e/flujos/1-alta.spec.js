// It. 40a — el flujo entre roles de docs/mapa-funcional.md (sección 3.1): de
// una veeduría que quiere usar GovTrace a su primer reporte. Cada vacío que
// lo corta queda como fixme con su número; la iteración que lo cierra lo
// vuelve un test de verdad. El sello no entra aquí: la red Stellar lo prueba
// make test-stellar, y la evidencia sellada llega con el fixture.
import { expect, test } from '@playwright/test';
import { CENTRAL, PEOPLE, WORKSITE, latestLinkTo, logIn, orgUrl } from './support.js';

const ALTA = {
    name: 'Veeduría de Alta E2E',
    nit: '901555888-3',
    subdomain: 'veeduria-e2e-alta',
    admin: 'e2e.alta.admin@correo.co',
    veedor: 'e2e.alta.veedor@correo.co',
    password: 'Veeduria#2026Alta',
};

test.describe.configure({ mode: 'serial' });

test.fixme('V10: una veeduría pide su alta desde el Inicio de GovTrace', async () => {});

test('El Super Administrador da de alta la organización con su Administrador inicial', async ({ page }) => {
    await logIn(page, CENTRAL, PEOPLE.superAdmin);
    await page.waitForURL('**/admin/organizations');
    await page.goto(`${CENTRAL}/admin/organizations/new`);

    const organization = page.getByRole('group', { name: 'Organización' });
    await organization.getByLabel('Nombre').fill(ALTA.name);
    await organization.getByLabel('NIT (con dígito de verificación, si tiene)').fill(ALTA.nit);
    await organization.getByLabel('Subdominio').fill(ALTA.subdomain);
    const administrator = page.getByRole('group', { name: 'Administrador inicial (opcional)' });
    await administrator.getByLabel('Nombre').fill('Administradora de Alta');
    await administrator.getByLabel('Correo electrónico').fill(ALTA.admin);
    await page.getByRole('button', { name: 'Registrar organización' }).click();

    await expect(page.getByText(/Organización registrada\./)).toBeVisible({ timeout: 60_000 });
});

test('El Super Administrador ve la invitación pendiente del Administrador y se la reenvía (V2)', async ({ page }) => {
    await logIn(page, CENTRAL, PEOPLE.superAdmin);
    await page.waitForURL('**/admin/organizations');
    const administrators = page.locator('[data-test="organization-row"]').filter({ hasText: ALTA.name }).locator('[data-test="administrators"]');

    await expect(administrators).toContainText(ALTA.admin);
    await expect(administrators).toContainText('Invitación pendiente');
    const firstLink = latestLinkTo(ALTA.admin);
    await administrators.getByRole('button', { name: 'Reenviar invitación' }).click();
    await expect(page.getByText(`Invitación reenviada a ${ALTA.admin}.`, { exact: false })).toBeVisible();

    // El enlace anterior deja de servir: solo vale el del último correo.
    expect(latestLinkTo(ALTA.admin)).not.toBe(firstLink);
    await page.goto(firstLink);
    await expect(page.getByRole('button', { name: 'Activar mi cuenta' })).toHaveCount(0);
});

test('El Administrador activa su cuenta con el enlace del correo', async ({ page }) => {
    await page.goto(latestLinkTo(ALTA.admin));
    // It. 45c: su nombre, para su veeduría.
    await page.getByLabel('Su nombre').fill('Marta Ospina');
    await page.getByLabel('Contraseña', { exact: true }).fill(ALTA.password);
    await page.getByLabel('Confirmar contraseña').fill(ALTA.password);
    // It. 44e: la política de datos, enlazada, y la autorización del tratamiento de sus datos.
    await expect(page.getByRole('link', { name: 'Lea la política de tratamiento de datos' })).toHaveAttribute('href', '/privacidad');
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según esta política.').check();
    await page.getByRole('button', { name: 'Activar mi cuenta' }).click();

    await page.waitForURL('**/admin/inbox');
});

test('El Administrador configura el territorio', async ({ page }) => {
    await logIn(page, orgUrl(ALTA.subdomain), ALTA.admin, ALTA.password);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${orgUrl(ALTA.subdomain)}/admin/territory`);

    await page.getByLabel('Buscar departamento o municipio').fill('Magdalena');
    await page.locator('[data-test="territory-result"]').filter({ hasText: 'Departamento' }).first().click();
    await page.getByRole('button', { name: 'Guardar territorio' }).click();

    await expect(page.getByText(/Territorio guardado\./)).toBeVisible();
});

test('El Administrador invita a un veedor', async ({ page }) => {
    await logIn(page, orgUrl(ALTA.subdomain), ALTA.admin, ALTA.password);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${orgUrl(ALTA.subdomain)}/admin/observers`);

    await page.getByLabel('Invitar un veedor de campo').fill(ALTA.veedor);
    await page.getByRole('button', { name: 'Enviar invitación' }).click();

    await expect(page.getByText(`Invitación enviada a ${ALTA.veedor}.`, { exact: false })).toBeVisible();
});

test('El veedor activa su cuenta y encuentra la obra para reportar', async ({ page }) => {
    await page.goto(latestLinkTo(ALTA.veedor));
    await page.getByLabel('Su nombre').fill('Carlos Rojas');
    await page.getByLabel('Contraseña', { exact: true }).fill(ALTA.password);
    await page.getByLabel('Confirmar contraseña').fill(ALTA.password);

    // It. 44c: sin declarar que no tiene impedimentos para ser veedor, no activa su cuenta.
    await page.getByRole('button', { name: 'Activar mi cuenta' }).click();
    await expect(page.getByText('Para ser veedor, declare que no está en ninguno de estos casos.')).toBeVisible();
    await page.getByLabel('Declaro que no estoy en ninguno de estos casos.').check();
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según esta política.').check();
    await page.getByRole('button', { name: 'Activar mi cuenta' }).click();
    await page.waitForURL('**/reports/new');

    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await expect(page.locator('[data-test="contract-result"]').first()).toContainText(WORKSITE);
});

test('El ciudadano encuentra la veeduría nueva en el Inicio de GovTrace (V5)', async ({ page }) => {
    await page.goto(CENTRAL);

    await expect(page.locator('[data-test="organization"]').filter({ hasText: ALTA.name })).toContainText('Magdalena');
});
