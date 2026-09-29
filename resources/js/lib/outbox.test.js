// @vitest-environment node
//
// Iteración 30 — US-018: la bandeja de salida del teléfono. Con límites
// (10 reportes, 50 MB), vigencia de 7 días y aviso 24 horas antes. El
// almacenamiento real es IndexedDB (lo prueba make e2e en un navegador);
// aquí, uno en memoria con la misma interfaz.
import { describe, expect, it } from 'vitest';
import { createOutbox, memoryStore, MESSAGES, OutboxFull } from './outbox.js';

const MB = 1024 * 1024;
const DAY = 24 * 60 * 60 * 1000;
const NOW = Date.parse('2026-09-29T12:00:00Z');

const fileOf = (bytes, name = 'foto.jpg') => new File([new Uint8Array(bytes)], name, { type: 'image/jpeg' });
const report = (capturedAt = new Date(NOW - 60_000).toISOString(), files = [fileOf(1000)]) => ({
    fields: { secop_contract_id: 'CO1.PCCNTR.1234567', classification: 'Retraso', comment: '', latitude: '11.2419', longitude: '-74.199', accuracy_meters: '12', captured_at: capturedAt },
    hashes: files.map(() => 'ab'.repeat(32)),
    files,
});

async function outboxWith(count, totalBytes, capturedAt) {
    const outbox = createOutbox(memoryStore(), { now: () => NOW });
    for (let i = 0; i < count; i++) {
        await outbox.add(report(capturedAt, [fileOf(Math.floor(totalBytes / Math.max(count, 1)))]));
    }
    return outbox;
}

describe('la bandeja de salida (US-018)', () => {
    it('Guardado sin conexión: keeps the report as captured — its place and its time — with its files', async () => {
        const outbox = await outboxWith(1, 1000);
        const captured = report('2026-09-25T14:00:00Z', [fileOf(2048, 'obra.jpg')]);

        await outbox.add(captured);

        const pending = await outbox.pending();
        expect(pending).toHaveLength(2);
        const saved = pending.find((record) => record.fields.captured_at === '2026-09-25T14:00:00Z');
        expect(saved.fields).toEqual(captured.fields);
        expect(saved.files[0]).toMatchObject({ name: 'obra.jpg', type: 'image/jpeg' });
        expect(saved.files[0].blob.size).toBe(2048);
        expect(saved.hashes).toEqual(captured.hashes);
    });

    it.each([
        [9, 30 * MB, true],
        [10, 30 * MB, false],
        [4, 50 * MB, false],
    ])('Límite de almacenamiento local: %i pending of %i bytes', async (count, bytes, saved) => {
        const outbox = await outboxWith(count, bytes);

        const attempt = outbox.add(report());

        if (saved) {
            await expect(attempt).resolves.toBeTruthy();
            expect(await outbox.pending()).toHaveLength(count + 1);
        } else {
            await expect(attempt).rejects.toThrow(new OutboxFull(MESSAGES.full));
            expect(await outbox.pending()).toHaveLength(count);
        }
    });

    it.each([
        ['5 días', 5 * DAY, { expiring: 0, discarded: 0, pending: 1 }],
        ['6 días', 6 * DAY, { expiring: 1, discarded: 0, pending: 1 }],
        ['7 días y 1 minuto', 7 * DAY + 60_000, { expiring: 0, discarded: 1, pending: 0 }],
    ])('Vigencia de 7 días con aviso 24 horas antes: captured %s ago', async (_, age, expected) => {
        const outbox = await outboxWith(1, 1000, new Date(NOW - age).toISOString());

        const review = await outbox.review();

        expect(review).toEqual({ expiring: expected.expiring, discarded: expected.discarded });
        expect(await outbox.pending()).toHaveLength(expected.pending);
    });

    it('gives the messages of US-018, word for word', () => {
        expect(MESSAGES).toMatchObject({
            saved: '📵 Sin conexión. Reporte guardado en el dispositivo. Se enviará automáticamente cuando recupere la señal.',
            full: '⚠️ Almacenamiento local lleno. Conéctese a internet para sincronizar los reportes pendientes antes de crear uno nuevo.',
            expiring: '⚠️ Tu reporte pendiente de sincronización expirará en 24 horas. Conéctate a una red para enviarlo antes de que se descarte.',
            syncFailed: '🔄 Error al sincronizar con el servidor. Se reintentará en unos minutos.',
            logout: '🚨 Tienes reportes sin enviar. Si cierras sesión ahora, se borrarán permanentemente del teléfono. ¿Deseas continuar?',
        });
    });

    it('sends the oldest first, and forgets everything when asked', async () => {
        const outbox = createOutbox(memoryStore(), { now: () => NOW });
        await outbox.add(report('2026-09-28T10:00:00Z'));
        await outbox.add(report('2026-09-27T10:00:00Z'));

        expect((await outbox.pending()).map((record) => record.fields.captured_at)).toEqual(['2026-09-27T10:00:00Z', '2026-09-28T10:00:00Z']);
        await outbox.clear();
        expect(await outbox.pending()).toEqual([]);
    });
});
