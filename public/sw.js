// US-018: la app del veedor abre sin señal. Guarda sus dos pantallas
// ("Nuevo Reporte" y "Mis Reportes", la última vez que se vieron con señal)
// y los archivos de Vite, que llevan su hash en el nombre. Los reportes que
// se crean sin señal los guarda la app en IndexedDB (lib/outbox.js), no este
// archivo. Todo lo demás pasa directo a la red.
const CACHE = 'govtrace-veedor-v1';
const PAGES = ['/reports/new', '/my-reports'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key !== CACHE) {
                await caches.delete(key);
            }
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
    } else if (PAGES.includes(url.pathname)) {
        event.respondWith(networkFirst(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }
    const response = await fetch(request);
    if (response.ok) {
        await cache.put(request, response.clone());
    }
    return response;
}

// Una visita de Inertia (JSON) y la carga de la página (HTML) son dos respuestas de la misma URL.
function keyOf(request) {
    if (!request.headers.get('X-Inertia')) {
        return request.url;
    }
    const url = new URL(request.url);
    url.searchParams.set('__inertia', '1');
    return url.toString();
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) {
            await cache.put(keyOf(request), response.clone());
        }
        return response;
    } catch (offline) {
        const cached = await cache.match(keyOf(request));
        if (cached) {
            return cached;
        }
        throw offline;
    }
}
