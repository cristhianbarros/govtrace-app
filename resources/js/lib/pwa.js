// US-018: el Service Worker de la app del veedor (public/sw.js), para que abra
// sin señal. Cerrar sesión borra lo que guardó: en un teléfono compartido, el
// siguiente no ve las pantallas del anterior.

export function registerServiceWorker() {
    if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin Service Worker la app funciona igual; solo no abre sin señal.
        });
    }
}

export async function forgetOfflineCopies() {
    if (typeof caches !== 'undefined') {
        for (const key of await caches.keys()) {
            await caches.delete(key);
        }
    }
}
