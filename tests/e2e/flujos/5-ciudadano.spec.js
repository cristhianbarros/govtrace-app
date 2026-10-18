// It. 40a — el flujo del ciudadano (docs/mapa-funcional.md, sección 2): sin
// cuenta, el mapa de la veeduría del fixture, una obra y sus evidencias.
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { CENTRAL, ORG, PEOPLE, WORKSITE, latestCodeTo, latestMailAbout, logIn, onThisPort } from './support.js';

// It. 46h: un rostro que no es de una persona real (La Gioconda, de dominio público), y un paisaje sin rostros.
const FACE = fileURLToPath(new URL('../../fixtures/evidence/rostro-pintura.jpg', import.meta.url));
const NO_FACE = fileURLToPath(new URL('../../fixtures/evidence/sin-rostro.jpg', import.meta.url));

test.describe.configure({ mode: 'serial' });

async function openTheWorksite(page) {
    await page.goto(ORG);
    await page.locator('.leaflet-marker-icon').first().click();
    // It. 60: el pin abre la tarjeta de la obra; "Ver la obra" lleva a su página.
    await page.locator('[data-test="worksite-card"]').getByRole('link', { name: 'Ver la obra' }).click();
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
    // It. 53 (R-PRIV-02): el punto es aproximado, y se ve: un círculo de unos 100 m por pin, y la nota.
    await expect(page.locator('.leaflet-overlay-pane svg path').first()).toBeAttached();
    expect(await page.locator('.leaflet-overlay-pane svg path').count()).toBe(await page.locator('.leaflet-marker-icon').count());
    await expect(page.locator('[data-test="approximate-note"]')).toHaveText('Cada punto es aproximado: la obra está a unos 100 m de él. Es para proteger a quien tomó la foto.');
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

// It. 60 (US-029): al tocar un pin, la tarjeta de la obra, sin salir del mapa.
// 2026-10-17, reportado por el usuario: en el computador, al bajar, los botones de zoom del mapa
// (z-index 1000 de Leaflet) se pintaban encima de las pestañas fijas.
test('En el computador, al bajar, el mapa no tapa las pestañas de la veeduría', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(ORG);
    const zoom = page.locator('.leaflet-control-zoom');
    await expect(zoom).toBeVisible();

    // Baja hasta que los botones de zoom quedan debajo del encabezado fijo, a la altura de las pestañas.
    const header = await page.locator('header').boundingBox();
    await page.evaluate((middle) => {
        const buttons = document.querySelector('.leaflet-control-zoom').getBoundingClientRect();
        window.scrollBy(0, buttons.top + buttons.height / 2 - middle);
    }, header.height * 0.75);
    const box = await zoom.boundingBox();
    expect(box.y + box.height / 2).toBeLessThan(header.height);
    const onTop = await page.evaluate(([x, y]) => Boolean(document.elementFromPoint(x, y)?.closest('header')), [box.x + box.width / 2, box.y + box.height / 2]);
    expect(onTop).toBe(true);
});

// 2026-10-17, pedido por el usuario: quien se aleja de las obras moviendo el mapa no tiene que buscarlas a mano.
test('Un botón vuelve a mostrar todas las obras del mapa', async ({ page }) => {
    await page.goto(ORG);
    const map = page.locator('.leaflet-container');
    const pin = page.locator('.leaflet-marker-icon').first();
    await map.scrollIntoViewIfNeeded();
    // Dentro del recuadro del mapa: un pin fuera de él sigue en la página, pero no se ve.
    const pinOnTheMap = async () => {
        const [spot, frame] = [await pin.boundingBox(), await map.boundingBox()];
        return Boolean(spot && spot.x >= frame.x && spot.x + spot.width <= frame.x + frame.width && spot.y >= frame.y && spot.y + spot.height <= frame.y + frame.height);
    };
    await expect.poll(pinOnTheMap).toBe(true);

    // Lejos de las obras: unos zooms hacia afuera y dos arrastres largos.
    for (let step = 0; step < 4; step++) {
        await page.locator('.leaflet-control-zoom-out').click();
    }
    const box = await map.boundingBox();
    for (let drag = 0; drag < 2; drag++) {
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + 10, box.y + 10, { steps: 8 });
        await page.mouse.up();
    }
    await expect.poll(pinOnTheMap).toBe(false);

    await page.getByRole('button', { name: 'Ver todas las obras' }).click();

    await expect.poll(pinOnTheMap).toBe(true);
});

test('Toca un pin y ve la tarjeta de la obra, con sus miniaturas; también con el teclado (it. 60)', async ({ page }) => {
    await page.goto(ORG);
    const pin = page.locator('.leaflet-marker-icon').first();
    await pin.click();

    const card = page.locator('[data-test="worksite-card"]');
    await expect(card.getByRole('heading', { name: WORKSITE })).toBeVisible();
    expect(page.url()).not.toContain('/worksite/');
    await expect(card.getByRole('link', { name: 'Informar a la veeduría' })).toBeVisible();
    // Las miniaturas son livianas: 320 px como mucho, no la foto de 1920.
    const thumbnail = card.locator('[data-test="card-thumbnail"] img').first();
    await expect(thumbnail).toHaveAttribute('src', /\/thumbnail$/);
    await expect.poll(() => thumbnail.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
    expect(await thumbnail.evaluate((image) => Math.max(image.naturalWidth, image.naturalHeight))).toBeLessThanOrEqual(320);

    // Esc la cierra y el foco vuelve al pin; Enter sobre el pin la abre otra vez, con el foco en ella.
    await page.keyboard.press('Escape');
    await expect(card).toHaveCount(0);
    await expect(pin).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(card).toBeFocused();

    // Una miniatura lleva a esa evidencia, en la página de la obra.
    await card.locator('[data-test="card-thumbnail"]').first().click();
    await page.waitForURL(/\/worksite\/[^#]+#evidencia-/);
    await expect(page.locator(`[id="${new URL(page.url()).hash.slice(1)}"]`)).toBeInViewport();
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

test('Informa a la veeduría con su correo verificado, y la veeduría le responde sin ver su correo (it. 44f)', async ({ page }) => {
    const citizen = `e2e.vecina.${Date.now()}@correo.co`;
    await openTheWorksite(page);

    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await expect(page.getByRole('status')).toContainText('Le enviamos un código de 6 dígitos');

    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await page.getByLabel('¿Qué vio en la obra?').fill('Desde el lunes no hay nadie trabajando y la valla está en el piso.');

    // It. 46h: las mismas opciones del veedor — la cámara y la galería — y de 1 a 3 fotos, cada una revisada.
    await expect(page.getByText('Fotos (opcional): hasta 3')).toBeVisible();
    await expect(page.getByText('Tomar foto')).toBeVisible();
    await page.locator('input[type="file"]:not([data-test])').setInputFiles([FACE, NO_FACE]);
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('Encontramos 1 rostro y lo difuminamos.', { timeout: 30_000 });
    await expect(page.getByText('Revise la foto antes de adjuntarla (1 de 2)')).toBeVisible();
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.locator('[data-test="faces-found"]')).toHaveText('No encontramos rostros.', { timeout: 30_000 });
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Usar esta foto', exact: true })).toHaveCount(0, { timeout: 30_000 });
    await expect(page.locator('[data-test="remove-evidence"]')).toHaveCount(2);

    await page.getByRole('button', { name: 'Enviar a la veeduría' }).click();
    await expect(page.getByRole('status')).toContainText('Su informe llegó a la veeduría.');
    // It. 62d (R-UX-08): la respuesta sale arriba de la sección, y "Enviar" está abajo: se trae a la vista.
    await expect(page.getByRole('status')).toBeInViewport();
    expect(await latestMailAbout(citizen, 'Recibimos su informe')).toContain('Recibimos su informe');

    // La Administradora lo recibe sin el correo, y le responde desde GovTrace.
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${ORG}/admin/citizen-reports`);
    const report = page.locator('[data-test="citizen-report"]').filter({ hasText: 'la valla está en el piso' });
    await expect(report).toContainText(WORKSITE);
    await expect(page.locator('main')).not.toContainText(citizen);
    // It. 46h: las dos fotos llegan, y cada una abre.
    await expect(report.locator('img')).toHaveCount(2);
    for (const photo of await report.locator('a[target="_blank"]').all()) {
        expect((await page.request.get(await photo.getAttribute('href'))).headers()['content-type']).toBe('image/jpeg');
    }
    await report.getByRole('button', { name: 'Responder' }).click();
    await report.getByLabel('Respuesta para el ciudadano').fill('Gracias. Esta semana va un veedor a documentarlo.');
    await report.getByRole('button', { name: 'Enviar respuesta' }).click();
    await expect(page.getByRole('status')).toHaveText('Respuesta enviada al ciudadano.');
    expect(await latestMailAbout(citizen, 'Respuesta a su informe')).toContain('Respuesta a su informe');
});

// It. 59 (US-066): el Inicio empieza por la obra, y GovTrace sugiere la veeduría.
// It. 62c (US-009): con la app de la cámara del teléfono al frente, un celular con poca memoria cerraba
// GovTrace. La cámara (aquí, la simulada de Chromium) se abre en la página, y el "atrás" la cierra sin salir.
test('La foto se toma dentro de GovTrace, sin salir de la app', async ({ page }) => {
    const citizen = `e2e.camara.${Date.now()}@correo.co`;
    const message = 'Escrito antes de abrir la cámara: la obra está parada.';
    await openTheWorksite(page);
    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await page.getByLabel('¿Qué vio en la obra?').fill(message);

    const camera = page.getByRole('dialog', { name: 'Tomar foto' });
    await page.getByRole('button', { name: 'Tomar foto', exact: true }).click();
    await expect(camera.getByRole('button', { name: 'Tomar la foto' })).toBeEnabled();
    expect(await page.evaluate(() => document.querySelector('video').srcObject?.getVideoTracks()[0]?.readyState)).toBe('live');

    // El "atrás" del teléfono cierra la cámara: la página y lo escrito siguen ahí.
    await page.goBack();
    await expect(camera).toHaveCount(0);
    await expect(page).toHaveURL(/\/worksite\//);
    await expect(page.getByLabel('¿Qué vio en la obra?')).toHaveValue(message);

    await page.getByRole('button', { name: 'Tomar foto', exact: true }).click();
    await camera.getByRole('button', { name: 'Tomar la foto' }).click();
    await expect(camera).toHaveCount(0);
    await expect(page.locator('[data-test="faces-found"]')).toBeVisible({ timeout: 30_000 });
    // It. 62d (R-UX-08): la revisión aparece debajo de los botones: se trae a la vista.
    await expect(page.locator('[data-test="faces-found"]')).toBeInViewport();
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    await expect(page.locator('[data-test="remove-evidence"]')).toHaveCount(1);
    await expect(page.getByText(/foto-\d\d-\d\d-\d\d\.jpg/)).toBeVisible();
    // It. 62g (R-UX-08): cerrada la revisión, la pantalla vuelve a las fotos del formulario.
    await expect(page.getByText(/foto-\d\d-\d\d-\d\d\.jpg/)).toBeInViewport();

    // La cámara devolvió su paso en la historia: el "atrás" ahora sí sale de la obra.
    await expect(page.getByLabel('¿Qué vio en la obra?')).toHaveValue(message);
    await page.goBack();
    await expect(page).not.toHaveURL(/\/worksite\//);
});

test('Busca la obra en el Inicio, GovTrace le sugiere la veeduría y le informa (it. 59)', async ({ page }) => {
    const citizen = `e2e.inicio.${Date.now()}@correo.co`;
    const asked = [];
    page.on('request', (request) => asked.push(request.url()));
    await page.goto(CENTRAL);
    await expect(page.getByRole('heading', { name: '¿Qué obra quiere reportar?' })).toBeVisible();

    await page.getByLabel('Nombre de la obra').fill('parque de pruebas sin');
    const work = page.locator('[data-test="work"]').filter({ hasText: WORKSITE });
    await expect(work).toHaveCount(1);
    await work.getByRole('button').click();

    // La que ya sigue la obra va primero, con su porqué; la dirección del Inicio no cambia.
    const suggested = page.locator('[data-test="suggested"]');
    await expect(suggested).toContainText('Veeduría de Pruebas E2E');
    await expect(suggested).toContainText(/Ya sigue esta obra: \d+ evidencias? publicadas?/);
    expect(new URL(page.url()).pathname + new URL(page.url()).search).toBe('/');

    // Un toque lleva al formulario de esa veeduría, en esa obra.
    await suggested.getByRole('link', { name: 'Informar a esta veeduría' }).click();
    await page.waitForURL(/\/worksite\/[^#]+#informar$/);
    await expect(page.getByRole('heading', { name: WORKSITE, level: 1 })).toBeVisible();
    await expect(page.locator('#informar')).toBeInViewport();

    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await expect(page.getByRole('status')).toContainText('Le enviamos un código de 6 dígitos');
    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await page.getByLabel('¿Qué vio en la obra?').fill('Llegué desde el Inicio de GovTrace: la obra sigue detenida.');
    await page.getByRole('button', { name: 'Enviar a la veeduría' }).click();
    await expect(page.getByRole('status')).toContainText('Su informe llegó a la veeduría.');

    // Ninguna petición llevó una ubicación.
    expect(asked.filter((url) => /latitude|longitude/.test(url))).toEqual([]);
});

// It. 58 (US-059-LEG, US-035): una obra que ningún veedor ha visitado.
test('Informa de una obra sin ubicación y dice dónde queda; la veeduría lo confirma y la obra aparece en el mapa (it. 58)', async ({ page }) => {
    const citizen = `e2e.vecino.${Date.now()}@correo.co`;
    const unlocatedWork = 'Polideportivo de pruebas del ciudadano';
    await page.goto(ORG);
    await page.getByRole('button', { name: 'Lista' }).click();

    // No está en el mapa: está en "Obras sin ubicación todavía", y la busca el servidor.
    await expect(page.locator('[data-test="listed"]').filter({ hasText: unlocatedWork })).toHaveCount(0);
    await page.getByLabel('Buscar una obra por su nombre').fill('polideportivo de pruebas');
    const unlocated = page.locator('[data-test="unlocated"]').filter({ hasText: unlocatedWork });
    await expect(unlocated).toHaveCount(1);
    await unlocated.getByRole('link').click();
    await page.waitForURL('**/contract/*');
    await expect(page.getByRole('heading', { name: unlocatedWork, level: 1 })).toBeVisible();
    await expect(page.locator('[data-test="unlocated"]')).toContainText('ningún veedor la ha visitado');

    await page.getByLabel('Su correo').fill(citizen);
    await page.getByLabel('Autorizo el tratamiento de mis datos personales según la política.').check();
    await page.getByRole('button', { name: 'Enviarme el código' }).click();
    await expect(page.getByRole('status')).toContainText('Le enviamos un código de 6 dígitos');
    await page.getByLabel('El código que le llegó al correo').fill(await latestCodeTo(citizen));
    await page.getByLabel('¿Qué vio en la obra?').fill('El polideportivo tiene la cubierta a medio poner y nadie trabaja.');

    // Marca dónde queda, tocando el mapa: empieza en la cabecera de Santa Marta.
    await page.getByRole('button', { name: 'Marcar dónde queda la obra' }).click();
    const map = page.locator('[data-test="propose-place"] .leaflet-container');
    await expect(map.locator('.leaflet-marker-icon')).toHaveCount(1);
    await map.click({ position: { x: 250, y: 200 } });
    await page.getByRole('button', { name: 'Enviar a la veeduría' }).click();
    await expect(page.getByRole('status')).toContainText('Su informe llegó a la veeduría.');

    // La Administradora ve dónde dice el ciudadano que queda, y lo confirma.
    await logIn(page, ORG, PEOPLE.admin);
    await page.waitForURL('**/admin/inbox');
    await page.goto(`${ORG}/admin/citizen-reports`);
    const report = page.locator('[data-test="citizen-report"]').filter({ hasText: 'la cubierta a medio poner' });
    await expect(report.locator('[data-test="proposed-location"]')).toContainText('El ciudadano dice que la obra queda aquí.');
    await expect(report.locator('[data-test="proposed-location"] .leaflet-marker-icon')).toHaveCount(1);
    await report.getByRole('button', { name: 'Confirmar esta ubicación' }).click();
    await report.getByRole('button', { name: 'Sí, confirmar' }).click();
    await expect(page.getByRole('status')).toContainText('La ubicación de la obra quedó confirmada');
    await expect(report.locator('[data-test="proposed-location"]')).toContainText('Ubicación confirmada aquí.');

    // Ya está en el mapa.
    await page.goto(ORG);
    await page.getByRole('button', { name: 'Lista' }).click();
    await page.getByLabel('Buscar una obra por su nombre').fill('polideportivo de pruebas');
    await expect(page.locator('[data-test="listed"]').filter({ hasText: unlocatedWork })).toHaveCount(1);
    await expect(page.locator('[data-test="unlocated"]').filter({ hasText: unlocatedWork })).toHaveCount(0);
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

test('V13: contacta a la veeduría desde su página (it. 43h)', async ({ page }) => {
    await page.goto(ORG);
    const contact = page.locator('footer [data-test="contact"]');

    await expect(contact.getByRole('link', { name: 'contacto@veeduria-e2e.org' })).toHaveAttribute('href', 'mailto:contacto@veeduria-e2e.org');
    await expect(contact.getByRole('link', { name: '+57 300 123 4567' })).toHaveAttribute('href', 'tel:+573001234567');
});

