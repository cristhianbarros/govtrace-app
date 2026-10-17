// It. 62b — make photo-memory: cuánta memoria toma revisar y adjuntar una foto
// de celular, en un Chromium de verdad. Las fotos se hacen aquí, del tamaño de
// las de un celular (12 y 50 megapíxeles), con el EXIF de una foto tomada en
// vertical. Mide la memoria de todos los procesos del navegador: el pico
// mientras se prepara la foto, y lo que queda tomado después, foto tras foto.
// No es una prueba: mide, para comparar antes y después de un cambio.
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, logIn } from '../flujos/support.js';

const PHOTOS = 'storage/framework/testing/photos';
const SOURCE = fileURLToPath(new URL('../../fixtures/evidence/rostros-lejanos.jpg', import.meta.url));

/** The resident memory of every Chromium process, in MB. */
function browserMemory() {
    let kilobytes = 0;
    for (const pid of readdirSync('/proc').filter((entry) => /^\d+$/.test(entry))) {
        try {
            if (!/chrom|headless_shell/.test(readFileSync(`/proc/${pid}/cmdline`, 'utf8'))) {
                continue;
            }
            kilobytes += Number(/VmRSS:\s+(\d+)/.exec(readFileSync(`/proc/${pid}/status`, 'utf8'))?.[1] ?? 0);
        } catch {
            // The process ended while it was read.
        }
    }
    return Math.round(kilobytes / 1024);
}

/** The photo with three far faces, enlarged to a phone's size; `upright` adds EXIF Orientation 6, as a photo taken upright. */
async function phonePhoto(browser, name, width, height, upright) {
    const page = await browser.newPage();
    const source = `data:image/jpeg;base64,${readFileSync(SOURCE).toString('base64')}`;
    const base64 = await page.evaluate(async ({ source, width, height, upright }) => {
        const image = new Image();
        image.src = source;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(image, 0, 0, width, height);
        let bytes = new Uint8Array(await (await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88))).arrayBuffer());
        if (upright) {
            const exif = [0x45, 0x78, 0x69, 0x66, 0, 0, 0x4d, 0x4d, 0, 0x2a, 0, 0, 0, 8, 0, 1, 0x01, 0x12, 0, 3, 0, 0, 0, 1, 0, 6, 0, 0, 0, 0, 0, 0];
            bytes = new Uint8Array([0xff, 0xd8, 0xff, 0xe1, 0, exif.length + 2, ...exif, ...bytes.subarray(2)]);
        }
        let text = '';
        for (let offset = 0; offset < bytes.length; offset += 0x8000) {
            text += String.fromCharCode(...bytes.subarray(offset, offset + 0x8000));
        }
        return btoa(text);
    }, { source, width, height, upright });
    await page.close();
    writeFileSync(`${PHOTOS}/${name}`, Buffer.from(base64, 'base64'));
}

async function settle(cdp) {
    await cdp.send('HeapProfiler.collectGarbage');
    await new Promise((resolve) => setTimeout(resolve, 1500));
    return browserMemory();
}

async function attach(page, cdp, file, found) {
    const before = await settle(cdp);
    let peak = before;
    const sampler = setInterval(() => (peak = Math.max(peak, browserMemory())), 10);
    await page.locator('input[type="file"]').first().setInputFiles(`${PHOTOS}/${file}`);
    await expect(page.locator('[data-test="faces-found"]')).toBeVisible({ timeout: 120_000 });
    if (found) {
        // La foto llegó entera y derecha hasta el detector.
        await expect(page.locator('[data-test="faces-found"]')).toHaveText(found);
    }
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.locator('[data-test="remove-evidence"]')).toBeVisible({ timeout: 60_000 });
    clearInterval(sampler);
    await page.locator('[data-test="remove-evidence"]').click();
    const after = await settle(cdp);
    return { file, before, peak: peak - before, after };
}

test('Cuánta memoria toma adjuntar una foto de celular', async ({ browser, page }) => {
    test.setTimeout(600_000);
    mkdirSync(PHOTOS, { recursive: true });
    await phonePhoto(browser, '12mp-vertical.jpg', 4000, 3000, true);
    await phonePhoto(browser, '50mp-vertical.jpg', 8160, 6120, true);
    await phonePhoto(browser, '50mp-horizontal.jpg', 8160, 6120, false);

    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').filter({ hasText: 'Parque de pruebas' }).first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    const cdp = await page.context().newCDPSession(page);

    const rows = [];
    // La primera carga el detector de rostros, una vez por pantalla.
    for (const [file, found] of [['12mp-vertical.jpg'], ['50mp-horizontal.jpg', 'Encontramos 3 rostros y los difuminamos.'], ['50mp-vertical.jpg'], ['12mp-vertical.jpg'], ['50mp-vertical.jpg'], ['12mp-vertical.jpg']]) {
        rows.push(await attach(page, cdp, file, found));
    }

    console.log('\nMemoria del navegador al adjuntar una foto (MB): antes, el pico por encima de antes, y después');
    for (const { file, before, peak, after } of rows) {
        console.log(`  ${file}: antes ${before}, pico +${peak}, después ${after}`);
    }
});
