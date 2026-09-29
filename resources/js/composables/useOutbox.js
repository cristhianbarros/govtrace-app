// US-018: la bandeja de salida compartida por las pantallas del veedor — cuántos
// reportes esperan, cuáles están por vencer y qué pasó al sincronizar. Se
// sincroniza al abrir la app, al volver la señal y cada 5 minutos mientras
// haya pendientes.
import { reactive } from 'vue';
import { createOutbox, indexedDbStore, MESSAGES } from '@/lib/outbox.js';
import { flushOutbox } from '@/lib/sync.js';
import { sendReport } from '@/services/api.js';

const RETRY_MS = 5 * 60 * 1000;

let outbox = null;
let syncing = null;
let started = false;

export const outboxState = reactive({ count: 0, expiring: 0, notice: null, rejected: [] });

const instance = () => (outbox ??= createOutbox(indexedDbStore()));

/** Solo para los tests: otra forma de guardar (un Map en memoria). */
export function configureOutbox({ store }) {
    outbox = createOutbox(store);
    Object.assign(outboxState, { count: 0, expiring: 0, notice: null, rejected: [] });
}

export async function refreshOutbox() {
    const { expiring } = await instance().review();
    outboxState.expiring = expiring;
    outboxState.count = (await instance().pending()).length;
}

/** @throws {OutboxFull} */
export async function saveOffline(report) {
    await instance().add(report);
    await refreshOutbox();
}

export async function clearOutbox() {
    await instance().clear();
    await refreshOutbox();
}

/** Una sincronización a la vez: si ya hay una en curso, se espera esa. */
export function syncOutbox() {
    syncing ??= (async () => {
        try {
            await refreshOutbox();
            // Sin pendientes, o con el teléfono sabiendo que no tiene señal: ni se intenta.
            if (outboxState.count === 0 || navigator.onLine === false) {
                return { sent: 0, rejected: [], failed: false };
            }
            const result = await flushOutbox({ outbox: instance(), send: sendReport });
            outboxState.notice = result.failed ? MESSAGES.syncFailed : null;
            outboxState.rejected = result.rejected;
            await refreshOutbox();
            return result;
        } finally {
            syncing = null;
        }
    })();
    return syncing;
}

export function startOutboxSync() {
    if (!started && typeof window !== 'undefined') {
        started = true;
        window.addEventListener('online', () => syncOutbox());
        setInterval(() => outboxState.count > 0 && syncOutbox(), RETRY_MS);
    }
    return syncOutbox();
}
