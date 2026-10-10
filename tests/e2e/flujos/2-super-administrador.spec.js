// It. 40a — el flujo del Super Administrador (docs/mapa-funcional.md, sección 2).
// Gobierna la organización que dio de alta el flujo 1-alta, que corre antes.
import { expect, test } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { CENTRAL, ORG, PEOPLE, latestLinkTo, logIn, logOut, attachPhoto } from './support.js';

const PHOTO = fileURLToPath(new URL('../../fixtures/evidence/foto.jpg', import.meta.url));
const E2E_ORG = 'Veeduría de Pruebas E2E';

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
    // V16: quién la administra (activó su cuenta en el flujo de alta).
    await expect(rowOf(page, ALTA).locator('[data-test="administrators"]')).toContainText('Activo');
});

test('Corrige los datos legales de una organización a solicitud formal: su NIT y su inscripción (it. 44d)', async ({ page }) => {
    await enterThePanel(page);
    const row = rowOf(page, ALTA);

    await row.getByRole('button', { name: 'Editar datos legales' }).click();
    await row.getByLabel('NIT', { exact: true }).fill('901555999-2');
    await row.getByLabel('Resolución o acta de inscripción').fill('Acta 45 de 2025');
    await row.getByLabel('Entidad que la registró').fill('Cámara de Comercio de Santa Marta');
    await row.getByRole('button', { name: 'Guardar datos legales' }).click();

    await expect(page.getByText('Los datos legales han sido actualizados.')).toBeVisible();
    await expect(row).toContainText('NIT 901555999-2 · Acta 45 de 2025, Cámara de Comercio de Santa Marta');
});

test('V8: cambia la razón social de una organización a solicitud formal (it. 43f)', async ({ page }) => {
    await enterThePanel(page);
    const row = rowOf(page, ALTA);

    await row.getByRole('button', { name: 'Editar datos legales' }).click();
    await row.getByLabel('Razón social').fill(`${ALTA} del Magdalena`);
    await row.getByRole('button', { name: 'Guardar datos legales' }).click();

    await expect(page.getByText('Los datos legales han sido actualizados.')).toBeVisible();
    await expect(row).toContainText(`${ALTA} del Magdalena`);
});

test('Suspende una organización y la reactiva', async ({ page }) => {
    await enterThePanel(page);
    const row = rowOf(page, ALTA);

    await row.getByRole('button', { name: 'Suspender' }).click();
    await row.getByRole('button', { name: 'Confirmar suspensión' }).click();
    await expect(row).toContainText('Suspendida');

    await row.getByRole('button', { name: 'Reactivar' }).click();
    await expect(row).toContainText('Activa');
});

test('V3: agrega otro Administrador a una organización que ya tiene uno (it. 43j)', async ({ page }) => {
    await enterThePanel(page);
    const administrators = rowOf(page, ALTA).locator('[data-test="administrators"]');

    await administrators.getByRole('button', { name: 'Agregar otro Administrador' }).click();
    await administrators.getByLabel('Nombre').fill('Ana Pérez');
    await administrators.getByLabel('Correo electrónico').fill(`e2e.alta.admin2.${Date.now()}@correo.co`);
    await administrators.getByRole('button', { name: 'Enviar invitación' }).click();

    await expect(page.getByText(/Invitación enviada a e2e\.alta\.admin2\./)).toBeVisible();
    await expect(administrators).toContainText('Ana Pérez');
    await expect(administrators).toContainText('Invitación pendiente');
});

test('Invita a otro Super Administrador, que activa su cuenta; lo desactiva y lo reactiva (it. 46a, US-063-USR)', async ({ page, browser }) => {
    const email = `e2e.superadmin2.${Date.now()}@govtrace.test`;
    await enterThePanel(page);
    await page.goto(`${CENTRAL}/admin/super-administrators`);

    await page.getByRole('button', { name: 'Invitar a otro Super Administrador' }).click();
    const form = page.locator('form[data-test="invite"]');
    await form.getByLabel('Nombre').fill('Luis Gómez');
    await form.getByLabel('Correo electrónico').fill(email);
    await form.getByRole('button', { name: 'Enviar invitación' }).click();
    await expect(page.getByText(`Invitación enviada a ${email}.`, { exact: false })).toBeVisible();
    const luis = page.locator('[data-test="super-admin"]').filter({ hasText: email });
    await expect(luis).toContainText('Invitación pendiente');

    // El invitado, en su propio navegador, activa su cuenta y llega al panel global.
    const elsewhere = await browser.newContext();
    const invitee = await elsewhere.newPage();
    await invitee.goto(latestLinkTo(email));
    await invitee.getByLabel('Su nombre').fill('Luis Gómez');
    await invitee.getByLabel('Contraseña', { exact: true }).fill('Luis#2026clave');
    await invitee.getByLabel('Confirmar contraseña').fill('Luis#2026clave');
    await invitee.getByLabel('Autorizo el tratamiento de mis datos personales según esta política.').check();
    await invitee.getByRole('button', { name: 'Activar mi cuenta' }).click();
    await invitee.waitForURL('**/admin/organizations');
    await elsewhere.close();

    await page.reload();
    await expect(luis).toContainText('Activo');
    await luis.getByRole('button', { name: 'Desactivar' }).click();
    await luis.getByRole('button', { name: 'Confirmar desactivación' }).click();
    await expect(luis).toContainText('Inactivo');
    await luis.getByRole('button', { name: 'Reactivar' }).click();
    await expect(luis).toContainText('Activo');

    // Nadie se desactiva a sí mismo.
    await expect(page.locator('[data-test="super-admin"]').filter({ hasText: 'Usted' }).getByRole('button', { name: 'Desactivar' })).toHaveCount(0);
});

test('Opera la plataforma: parámetros, auditoría, SECOP, sellado y uso', async ({ page }) => {
    await enterThePanel(page);

    for (const [path, title] of PANEL) {
        await page.goto(`${CENTRAL}${path}`);
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    }
});

/** The Administradora of the e2e organization authorizes the Super Administrador, or revokes it (US-042-SEC). */
async function authorization(page, button) {
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${ORG}/admin/authorization`);
    await page.getByRole('button', { name: button }).click();
    await logOut(page);
}

test('V7: crea un reporte en nombre de una organización que lo autorizó (it. 43g)', async ({ page }) => {
    await authorization(page, 'Autorizar por 30 días');

    await enterThePanel(page);
    const row = rowOf(page, E2E_ORG);
    await expect(row.locator('[data-test="authorization"]')).toContainText('Lo autorizó a reportar en su nombre hasta el');
    await row.getByRole('link', { name: 'Reportar en su nombre' }).click();
    await page.waitForURL('**/report');
    await expect(page.getByRole('heading', { name: `Reportar en nombre de ${E2E_ORG}` })).toBeVisible();

    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').filter({ hasText: 'Parque de pruebas' }).first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Avance').check();
    await attachPhoto(page, PHOTO);
    await page.getByRole('button', { name: 'Enviar Reporte' }).click();
    await expect(page.getByRole('status')).toContainText(`Reporte recibido en nombre de ${E2E_ORG}.`);
    await logOut(page);

    // Que la Administradora pueda volver a autorizar en su propio flujo.
    await authorization(page, 'Revocar autorización');
});

test('Sincroniza SECOP a mano, sin esperar a la madrugada (V15)', async ({ page }) => {
    await enterThePanel(page);
    await page.goto(`${CENTRAL}/admin/secop-health`);

    await page.getByRole('button', { name: 'Sincronizar ahora' }).click();
    // Si otra corrida de estas pruebas la pidió hace menos de 5 minutos, la respuesta es la de espera.
    await expect(page.getByRole('status')).toHaveText(/Sincronización con SECOP II en marcha|Ya se pidió una sincronización hace menos de 5 minutos/);
});

test('Cierra la sesión desde el menú de su cuenta (V1)', async ({ page }) => {
    await enterThePanel(page);
    await expect(page.locator('header')).toContainText('Super Administrador E2E');

    await logOut(page);

    await page.goto(`${CENTRAL}/admin/organizations`);
    await page.waitForURL('**/login');
});
