// It. 40a — el flujo del veedor de campo (docs/mapa-funcional.md, sección 2).
// Enviar un reporte, con y sin señal, lo prueba offline.spec.js.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PASSWORD, PEOPLE, WORKSITE, logIn, logOut } from './support.js';

// It. 46e: un rostro que no es de una persona real (La Gioconda, de dominio público), y el paisaje de su fondo.
const FACE = fileURLToPath(new URL('../../fixtures/evidence/rostro-pintura.jpg', import.meta.url));
const NO_FACE = fileURLToPath(new URL('../../fixtures/evidence/sin-rostro.jpg', import.meta.url));
// It. 46f: tres rostros lejanos (unos 56 px en 1920), uno en el centro.
const FAR_FACES = fileURLToPath(new URL('../../fixtures/evidence/rostros-lejanos.jpg', import.meta.url));
const SUCCESS = 'Reporte recibido con éxito.';

test.describe.configure({ mode: 'serial' });

async function enter(page) {
    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');
}

test('Entra y llega a Nuevo reporte', async ({ page }) => {
    await enter(page);

    await expect(page.getByRole('heading', { name: 'Nuevo Reporte', exact: true })).toBeVisible();
});

test('Una veedora que ya tenía cuenta declara sus impedimentos antes de reportar (it. 44c)', async ({ page }) => {
    await logIn(page, ORG, PEOPLE.undeclared);
    await page.waitForURL('**/declaration');
    await expect(page.getByRole('heading', { name: 'Antes de reportar' })).toBeVisible();
    await expect(page.getByText('Ley 850 de 2003, artículo 19')).toBeVisible();

    // Sin la casilla no sigue.
    await page.getByRole('button', { name: 'Declarar y continuar' }).click();
    await expect(page.getByRole('alert')).toHaveText('Para ser veedor, declare que no está en ninguno de estos casos.');

    await page.getByLabel('Declaro que no estoy en ninguno de estos casos.').check();
    await page.getByRole('button', { name: 'Declarar y continuar' }).click();
    await page.waitForURL('**/reports/new');
    await expect(page.getByRole('heading', { name: 'Nuevo Reporte', exact: true })).toBeVisible();
});

test('Puede instalar la app en su celular, con el nombre de su veeduría (V6)', async ({ page }) => {
    await enter(page);

    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest');
    const manifest = await (await page.request.get(`${ORG}/manifest.webmanifest`)).json();
    expect(manifest.name).toBe('GovTrace · Veeduría de Pruebas E2E');
    expect(manifest.start_url).toBe('/reports/new');
    expect((await page.request.get(`${ORG}/pwa/govtrace-512.png`)).status()).toBe(200);
});

test('Arma un reporte: la obra, lo que vio y la foto', async ({ page }) => {
    await enter(page);

    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Avance').check();
    await page.locator('input[type="file"]').first().setInputFiles(NO_FACE);

    // It. 46e: la foto se revisa antes de adjuntarla; en esta no hay nadie.
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('No encontramos rostros.', { timeout: 30_000 });
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Enviar Reporte' })).toBeEnabled();
});

/**
 * The fine detail of a part of an image: the mean difference of brightness
 * between neighbouring pixels. A blurred face keeps its colours (dark hair,
 * light skin) but loses its detail — the eyes, the mouth.
 */
async function fineDetail(page, sources, area) {
    return page.evaluate(
        async ({ sources, area }) => {
            const detail = async (src) => {
                const image = new Image();
                image.src = src;
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                const context = canvas.getContext('2d');
                context.drawImage(image, 0, 0);
                const { data } = context.getImageData(area.x, area.y, area.width, area.height);
                const brightness = (x, y) => {
                    const i = (y * area.width + x) * 4;
                    return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                };
                let total = 0;
                let count = 0;
                for (let y = 0; y < area.height - 1; y++) {
                    for (let x = 0; x < area.width - 1; x++) {
                        total += Math.abs(brightness(x + 1, y) - brightness(x, y)) + Math.abs(brightness(x, y + 1) - brightness(x, y));
                        count += 2;
                    }
                }
                return total / count;
            };
            return Promise.all(sources.map(detail));
        },
        { sources, area },
    );
}

test('Un rostro lejano también se difumina (it. 46f)', async ({ page }) => {
    await enter(page);
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();

    // El modelo de verdad, en un Chromium de verdad: la foto entera no los ve; la grilla de ventanas, sí.
    await page.locator('input[type="file"]').first().setInputFiles(FAR_FACES);
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('Encontramos 3 rostros y los difuminamos.', { timeout: 30_000 });
    await expect(page.locator('[data-test="detected-face"]')).toHaveCount(3);
    await page.getByRole('button', { name: 'No usar esta foto', exact: true }).click();
    await logOut(page);
});

test('Los rostros de una foto se difuminan en el celular antes de calcular su huella (it. 46e)', async ({ page }) => {
    // La Bandeja muestra la evidencia cuando el worker la sella en la red local: puede tardar.
    test.setTimeout(240_000);
    const comment = `Rostro difuminado E2E ${Date.now()}`;
    await enter(page);
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.getByLabel('Retraso').check();
    await page.getByLabel('Comentario (opcional)').fill(comment);

    // El detector, en el celular, encuentra el rostro y lo difumina; una zona más, a mano, con un toque.
    await page.locator('input[type="file"]').first().setInputFiles(FACE);
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('Encontramos 1 rostro y lo difuminamos.', { timeout: 30_000 });
    await page.locator('canvas[role="img"]').click({ position: { x: 15, y: 15 } });
    await expect(page.getByText('Zonas difuminadas a mano: 1')).toBeVisible();
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await page.getByRole('button', { name: 'Enviar Reporte' }).click();
    await expect(page.getByText(SUCCESS)).toBeVisible();
    await logOut(page);

    // La Administradora la ve en la Bandeja cuando queda sellada, con lo que se difuminó.
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    const card = page.locator('article').filter({ hasText: comment });
    await expect(async () => {
        await page.reload();
        await expect(card).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 180_000 });
    await expect(card.locator('[data-test="blurring"]')).toHaveText('2 zonas difuminadas en el celular');

    // El archivo que se guardó y se selló: el rostro, sin detalle; lo demás, como era.
    const stored = await card.locator('img').first().getAttribute('src');
    const original = `data:image/jpeg;base64,${readFileSync(FACE).toString('base64')}`;
    const [faceBefore, faceAfter] = await fineDetail(page, [original, stored], { x: 250, y: 190, width: 80, height: 130 });
    const [restBefore, restAfter] = await fineDetail(page, [original, stored], { x: 30, y: 700, width: 120, height: 120 });
    expect(faceAfter).toBeLessThan(faceBefore * 0.4);
    // Lo demás cambia apenas por volver a codificar el JPEG.
    expect(Math.abs(restAfter - restBefore)).toBeLessThan(restBefore * 0.3);
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
    await page.locator('[data-test="account-menu"]').click();
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
