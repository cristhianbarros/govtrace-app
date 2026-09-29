// US-018: la bandeja de salida del teléfono — los reportes que se guardaron
// sin señal, tal como se capturaron (lugar y hora congelados), con sus
// archivos y sus hashes. Límites: 10 reportes y 50 MB. Vigencia: 7 días
// desde la captura, con aviso 24 horas antes; después se descartan (el
// servidor tampoco los aceptaría sin marcarlos, R-SEC-05).
//
// La lógica no sabe dónde se guarda: `store` es IndexedDB en el navegador
// (indexedDbStore) y un Map en los tests (memoryStore).

export const LIMITS = { reports: 10, bytes: 50 * 1024 * 1024 };

const DAY_MS = 24 * 60 * 60 * 1000;
export const VALIDITY_MS = 7 * DAY_MS;
const WARNING_MS = VALIDITY_MS - DAY_MS;

export const MESSAGES = {
    saved: '📵 Sin conexión. Reporte guardado en el dispositivo. Se enviará automáticamente cuando recupere la señal.',
    full: '⚠️ Almacenamiento local lleno. Conéctese a internet para sincronizar los reportes pendientes antes de crear uno nuevo.',
    expiring: '⚠️ Tu reporte pendiente de sincronización expirará en 24 horas. Conéctate a una red para enviarlo antes de que se descarte.',
    syncFailed: '🔄 Error al sincronizar con el servidor. Se reintentará en unos minutos.',
    logout: '🚨 Tienes reportes sin enviar. Si cierras sesión ahora, se borrarán permanentemente del teléfono. ¿Deseas continuar?',
};

export const waitingLabel = (count) => (count === 1 ? '⏳ 1 reporte esperando conexión' : `⏳ ${count} reportes esperando conexión`);

export class OutboxFull extends Error {}

const newId = () => globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;

/**
 * @param {{ all(): Promise<object[]>, put(record: object): Promise<void>, delete(id: string): Promise<void>, clear(): Promise<void> }} store
 */
export function createOutbox(store, { now = () => Date.now() } = {}) {
    const age = (record) => now() - Date.parse(record.fields.captured_at);

    return {
        /** @param {{ fields: Record<string, string>, hashes: string[], files: File[] }} report */
        async add({ fields, hashes, files }) {
            const pending = await store.all();
            const size = files.reduce((total, file) => total + file.size, 0);
            const used = pending.reduce((total, record) => total + record.sizeBytes, 0);
            if (pending.length + 1 > LIMITS.reports || used + size > LIMITS.bytes) {
                throw new OutboxFull(MESSAGES.full);
            }

            const record = {
                id: newId(),
                fields: { ...fields },
                hashes: [...hashes],
                files: files.map((file) => ({ name: file.name, type: file.type, blob: file })),
                sizeBytes: size,
                savedAt: new Date(now()).toISOString(),
            };
            await store.put(record);
            return record;
        },

        /** The oldest capture first: the order they were taken. */
        async pending() {
            return (await store.all()).sort((a, b) => Date.parse(a.fields.captured_at) - Date.parse(b.fields.captured_at));
        },

        remove: (id) => store.delete(id),

        clear: () => store.clear(),

        /** Discards the expired ones; tells how many are about to expire. */
        async review() {
            let expiring = 0;
            let discarded = 0;
            for (const record of await store.all()) {
                if (age(record) > VALIDITY_MS) {
                    await store.delete(record.id);
                    discarded++;
                } else if (age(record) >= WARNING_MS) {
                    expiring++;
                }
            }
            return { expiring, discarded };
        },
    };
}

/** For the tests: the same interface, in memory. */
export function memoryStore() {
    const records = new Map();
    return {
        all: async () => [...records.values()],
        put: async (record) => void records.set(record.id, record),
        delete: async (id) => void records.delete(id),
        clear: async () => records.clear(),
    };
}

/** IndexedDB of this organization's subdomain (each origin has its own). */
export function indexedDbStore(name = 'govtrace-outbox') {
    const opening = new Promise((resolve, reject) => {
        const request = indexedDB.open(name, 1);
        request.onupgradeneeded = () => request.result.createObjectStore('reports', { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    async function run(mode, action) {
        const db = await opening;
        return new Promise((resolve, reject) => {
            const transaction = db.transaction('reports', mode);
            const request = action(transaction.objectStore('reports'));
            transaction.oncomplete = () => resolve(request?.result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error ?? new Error('IndexedDB: transacción abortada'));
        });
    }

    return {
        all: () => run('readonly', (reports) => reports.getAll()),
        put: (record) => run('readwrite', (reports) => reports.put(record)).then(() => undefined),
        delete: (id) => run('readwrite', (reports) => reports.delete(id)).then(() => undefined),
        clear: () => run('readwrite', (reports) => reports.clear()).then(() => undefined),
    };
}
