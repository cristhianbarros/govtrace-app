// It. 40a — el flujo del ciudadano (docs/mapa-funcional.md, sección 2): sin
// cuenta, el mapa de la veeduría del fixture, una obra y sus evidencias.
import { expect, test } from '@playwright/test';
import { CENTRAL, ORG, WORKSITE, onThisPort } from './support.js';

test.describe.configure({ mode: 'serial' });

async function openTheWorksite(page) {
    await page.goto(ORG);
    await page.locator('.leaflet-marker-icon').first().click();
    await page.waitForURL('**/worksite/*');
}

test('Llega al mapa de la veeduría desde el Inicio de GovTrace (V5)', async ({ page }) => {
    await page.goto(CENTRAL);
    await expect(page.getByRole('heading', { name: '¿Cómo funciona?' })).toBeVisible();

    const listed = page.locator('[data-test="organization"]').filter({ hasText: 'Veeduría de Pruebas E2E' });
    await expect(listed).toContainText('Magdalena');
    await page.goto(onThisPort(await listed.getByRole('link', { name: 'Ver su mapa de obras' }).getAttribute('href')));
    await expect(page.getByRole('heading', { name: 'Obras vigiladas', exact: true })).toBeVisible();
    await expect(page.locator('header').getByRole('link', { name: 'Entrar' })).toBeVisible();
});

test('Ve el mapa de obras de la veeduría y sus filtros', async ({ page }) => {
    await page.goto(ORG);

    await expect(page.locator('.leaflet-marker-icon').first()).toBeVisible();
    // It. 40b: los estados, junto al mapa y sin desplazarse.
    const states = page.locator('[data-test="state"]');
    await expect(states).toHaveCount(3);
    for (const size of [{ width: 412, height: 915 }, { width: 1366, height: 768 }]) {
        await page.setViewportSize(size);
        const box = await states.last().boundingBox();
        expect(box.y + box.height, `los estados, visibles en ${size.width}×${size.height}`).toBeLessThanOrEqual(size.height);
    }
    await page.getByText('Más filtros').click();
    await expect(page.locator('#filter-status')).toBeVisible();
});

test('Busca una obra por su nombre, en la lista (V12)', async ({ page }) => {
    await page.goto(ORG);
    await page.getByRole('button', { name: 'Lista' }).click();

    await page.getByLabel('Buscar una obra por su nombre').fill('parque de PRUEBAS');
    const found = page.locator('[data-test="listed"]');
    await expect(found).toHaveCount(1);
    await expect(found).toContainText(WORKSITE);
    await found.getByRole('link').click();
    await page.waitForURL('**/worksite/*');
});

test('Abre una obra: el contrato de SECOP y sus evidencias publicadas', async ({ page }) => {
    await openTheWorksite(page);

    await expect(page.getByRole('heading', { name: WORKSITE, level: 1 })).toBeVisible();
    await expect(page.getByText('Constructora de Pruebas S.A.S.')).toBeVisible();
    // It. 40b: su estado y por qué, en palabras.
    await expect(page.locator('[data-test="condition"]')).toContainText('El reporte publicado más reciente');
    // It. 44a: el estado es una alerta, y los canales de la Contraloría están a mano.
    await expect(page.locator('[data-test="condition"]')).toContainText('No es la decisión de una autoridad.');
    await expect(page.getByRole('heading', { name: '¿Sabe de un problema en esta obra?' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Línea gratuita 199' })).toHaveAttribute('href', 'tel:199');
    await expect(page.getByRole('button', { name: 'Comprobar que es original' }).first()).toBeVisible();
});

test('Comprueba una evidencia: su recibo, su descarga y su prueba', async ({ page }) => {
    await openTheWorksite(page);

    await page.getByRole('button', { name: 'Comprobar que es original' }).first().click();
    await expect(page.locator('[data-test="seal"]')).toContainText('Ledger');

    // US-026: "Descargar archivo original" y su prueba, en la tarjeta (it. 43c).
    const original = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Descargar archivo original' }).first().click();
    expect((await original).suggestedFilename()).toMatch(/\.(jpg|png|pdf)$/);
    const proof = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Descargar su prueba' }).first().click();
    expect((await proof).suggestedFilename()).toMatch(/\.prueba\.json$/);
});

test('Lee la política de tratamiento de datos desde el mapa (it. 44e)', async ({ page }) => {
    await page.goto(ORG);
    await page.getByRole('link', { name: 'Política de tratamiento de datos' }).click();

    await expect(page.getByRole('heading', { name: 'Política de tratamiento de datos personales' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Sus derechos' })).toBeVisible();
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

test('Encuentra el verificador independiente desde el validador (V14)', async ({ page }) => {
    await page.goto(`${ORG}/verify`);

    await expect(page.locator('a[data-test="verifier"]')).toHaveAttribute('href', /tools\/verify$/);
});

test.fixme('V13: contacta a la veeduría desde su página', async () => {});

test.fixme('Ley 1581: lee la política de tratamiento de datos personales (decisión pendiente)', async () => {});
