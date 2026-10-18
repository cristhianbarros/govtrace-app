// It. 62f — make photo-time: cuánto tarda "Tomar la foto" hasta ver la revisión,
// y "Usar esta foto" hasta verla adjunta, con la cámara en la página (it. 62c, la
// simulada de Chromium) y la CPU frenada como en un celular de gama media
// (CPU_RATE=4, por defecto). El ciudadano, con el detector sin cargar al entrar;
// el veedor, con el que su pantalla precarga. No es una prueba: mide, para
// comparar antes y después de un cambio. El frenado no alcanza la codificación
// de imágenes, que Chrome hace en otro hilo: en un celular, ese paso pesa más.
import { expect, test } from '@playwright/test';
import { ORG, PEOPLE, latestCodeTo, logIn } from '../flujos/support.js';

const CPU_RATE = Number(process.env.CPU_RATE ?? 4);

async function watch(page) {
    await page.evaluate(() => {
        window.marks = [];
        const mark = (name) => window.marks.push([name, performance.now()]);
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-test="shutter"]')) mark('shutter');
            else if (event.target.closest('button')?.textContent.trim() === 'Usar esta foto') mark('use');
        }, true);
        let reviewing = false;
        let shown = false;
        let camera = false;
        let attached = document.querySelectorAll('[data-test="remove-evidence"]').length;
        new MutationObserver(() => {
            const open = !!document.querySelector('[role="dialog"][aria-label="Tomar foto"]');
            if (camera && !open) mark('closed');
            camera = open;
            // It. 62f: la foto, a la vista mientras se revisan sus rostros.
            const pending = !!document.querySelector('[data-test="photo-pending"]');
            if (pending && !shown) requestAnimationFrame(() => mark('shown'));
            shown = pending;
            const review = !!document.querySelector('[data-test="faces-found"]');
            if (review && !reviewing) requestAnimationFrame(() => mark('review'));
            reviewing = review;
            const count = document.querySelectorAll('[data-test="remove-evidence"]').length;
            if (count > attached) requestAnimationFrame(() => mark('attached'));
            attached = count;
        }).observe(document.body, { childList: true, subtree: true });
    });
}

async function takeAndUse(page) {
    await page.evaluate(() => (window.marks = []));
    await page.getByRole('button', { name: 'Tomar foto', exact: true }).click();
    const shutter = page.getByRole('dialog', { name: 'Tomar foto' }).getByRole('button', { name: 'Tomar la foto' });
    await expect(shutter).toBeEnabled({ timeout: 30_000 });
    const size = await page.evaluate(() => `${document.querySelector('video').videoWidth}x${document.querySelector('video').videoHeight}`);
    await shutter.click();
    await expect(page.locator('[data-test="faces-found"]')).toBeVisible({ timeout: 120_000 });
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Usar esta foto', exact: true })).toHaveCount(0, { timeout: 60_000 });
    await page.waitForTimeout(300);
    const marks = Object.fromEntries(await page.evaluate(() => window.marks));
    return {
        video: size,
        'tomar → cámara cerrada': Math.round(marks.closed - marks.shutter),
        'tomar → foto a la vista': marks.shown ? Math.round(marks.shown - marks.shutter) : null,
        'tomar → revisión': Math.round(marks.review - marks.shutter),
        'usar → adjunta': Math.round(marks.attached - marks.use),
    };
}

test('Cuánto tarda tomar y usar una foto', async ({ page }) => {
    test.setTimeout(600_000);
    const results = [];

    // El ciudadano: el detector se carga con su primera foto.
    const citizen = `bench.foto.${Date.now()}@correo.co`;
    await page.goto(ORG);
    await page.locator('.leaflet-marker-icon').first().click();
    await page.locator('[data-test="worksite-card"]').getByRole('link', { name: 'Ver la obra' }).click();
    await page.waitForURL('**/worksite/*');
    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await watch(page);
    let cdp = await page.context().newCDPSession(page);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU_RATE });
    results.push(['ciudadano, 1.ª foto', await takeAndUse(page)]);
    results.push(['ciudadano, 2.ª foto', await takeAndUse(page)]);
    await cdp.detach();

    // El veedor: su pantalla precarga el detector al abrirse.
    await logIn(page, ORG, PEOPLE.veedor);
    await page.waitForURL('**/reports/new');
    await page.getByLabel('Buscar Obra').fill('Parque de pruebas');
    await page.locator('[data-test="contract-result"]').filter({ hasText: 'Parque de pruebas' }).first().click();
    await expect(page.getByText(/Precisión del GPS/)).toBeVisible();
    await page.waitForTimeout(3000);
    await watch(page);
    cdp = await page.context().newCDPSession(page);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU_RATE });
    results.push(['veedor, 1.ª foto', await takeAndUse(page)]);
    results.push(['veedor, 2.ª foto', await takeAndUse(page)]);
    await cdp.detach();

    console.log(`\nTomar y usar una foto, CPU ${CPU_RATE}x más lenta (ms):`);
    for (const [who, times] of results) {
        console.log(`  ${who}: ${JSON.stringify(times)}`);
    }
});
