// It. 40a — el flujo del ciudadano (docs/mapa-funcional.md, sección 2): sin
// cuenta, el mapa de la veeduría del fixture, una obra y sus evidencias.
import { expect, test } from '@playwright/test';
import { ORG, WORKSITE } from './support.js';

test.describe.configure({ mode: 'serial' });

async function openTheWorksite(page) {
    await page.goto(ORG);
    await page.locator('.leaflet-marker-icon').first().click();
    await page.waitForURL('**/worksite/*');
}

test.fixme('V5: llega al mapa de la veeduría desde el Inicio de GovTrace', async () => {});

test('Ve el mapa de obras de la veeduría y sus filtros', async ({ page }) => {
    await page.goto(ORG);

    await expect(page.locator('.leaflet-marker-icon').first()).toBeVisible();
    await page.getByText('Filtrar obras').click();
    await expect(page.getByLabel('Estado')).toBeVisible();
});

test.fixme('V12: busca una obra por su nombre', async () => {});

test('Abre una obra: el contrato de SECOP y sus evidencias publicadas', async ({ page }) => {
    await openTheWorksite(page);

    await expect(page.getByRole('heading', { name: WORKSITE, level: 2 })).toBeVisible();
    await expect(page.getByText('Constructora de Pruebas S.A.S.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Verificar Sello Blockchain' }).first()).toBeVisible();
});

test('Comprueba una evidencia: su recibo, su descarga y su prueba', async ({ page }) => {
    await openTheWorksite(page);

    await page.getByRole('button', { name: 'Verificar Sello Blockchain' }).first().click();
    await expect(page.locator('[data-test="seal"]')).toContainText('Ledger');

    const worksiteId = new URL(page.url()).pathname.split('/').pop();
    const { data } = await (await page.request.get(`${ORG}/public/worksites/${worksiteId}`)).json();
    const file = data.timeline.find((entry) => entry.files)?.files[0];
    expect((await page.request.get(`${ORG}${file.download_url}`)).status()).toBe(200);
    expect((await page.request.get(`${ORG}${file.proof_url}`)).status()).toBe(200);
});

test('Ve las estadísticas del territorio y descarga los datos abiertos', async ({ page }) => {
    await page.goto(`${ORG}/stats`);
    await expect(page.getByRole('heading', { name: 'Estadísticas del territorio', exact: true })).toBeVisible();

    const openData = await page.request.get(`${ORG}/open-data.csv`);
    expect(openData.status()).toBe(200);
    expect(openData.headers()['content-type']).toContain('text/csv');
});

test('Abre el validador de evidencias', async ({ page }) => {
    await page.goto(`${ORG}/verify`);

    await expect(page.getByRole('heading', { name: 'Validador de evidencias', exact: true })).toBeVisible();
});

test.fixme('V14: encuentra el script de verificación independiente desde el validador', async () => {});

test.fixme('V13: contacta a la veeduría desde su página', async () => {});

test.fixme('Ley 1581: lee la política de tratamiento de datos personales (decisión pendiente)', async () => {});
