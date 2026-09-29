// @vitest-environment node
//
// Iteración 30 — US-018: subir la bandeja de salida cuando vuelve la señal.
import { describe, expect, it, vi } from 'vitest';
import { createOutbox, memoryStore } from './outbox.js';
import { flushOutbox } from './sync.js';

const report = (capturedAt) => ({
    fields: { secop_contract_id: 'CO1.PCCNTR.1234567', classification: 'Retraso', comment: 'Sin avance', latitude: '11.2419', longitude: '-74.199', accuracy_meters: '12', captured_at: capturedAt },
    hashes: ['ab'.repeat(32)],
    files: [new File([new Uint8Array([1, 2, 3])], 'foto.jpg', { type: 'image/jpeg' })],
});

async function outboxOf(...capturedAts) {
    const outbox = createOutbox(memoryStore(), { now: () => Date.parse('2026-09-29T12:00:00Z') });
    for (const capturedAt of capturedAts) {
        await outbox.add(report(capturedAt));
    }
    return outbox;
}

describe('la sincronización (US-018)', () => {
    it('Envío automático al recuperar la señal: sends every pending report, as it was captured, and empties the outbox', async () => {
        const outbox = await outboxOf('2026-09-28T09:00:00Z', '2026-09-28T10:00:00Z');
        const send = vi.fn(async () => ({ id: 1 }));

        const result = await flushOutbox({ outbox, send });

        expect(result).toEqual({ sent: 2, rejected: [], failed: false });
        expect(await outbox.pending()).toEqual([]);
        const form = send.mock.calls[0][0];
        expect(form.get('captured_at')).toBe('2026-09-28T09:00:00Z');
        expect(form.get('latitude')).toBe('11.2419');
        expect(form.getAll('hashes[]')).toEqual(['ab'.repeat(32)]);
        expect(form.getAll('files[]')[0].name).toBe('foto.jpg');
    });

    it('Falla al reenviar: the report stays when the server does not answer, and it stops there', async () => {
        const outbox = await outboxOf('2026-09-28T09:00:00Z', '2026-09-28T10:00:00Z');
        const send = vi.fn(async () => {
            throw new Error('Network Error');
        });

        const result = await flushOutbox({ outbox, send });

        expect(result).toEqual({ sent: 0, rejected: [], failed: true });
        expect(send).toHaveBeenCalledTimes(1);
        expect(await outbox.pending()).toHaveLength(2);
    });

    it('keeps a report the server could not take for now: an error of its own, or the organization suspended', async () => {
        const outbox = await outboxOf('2026-09-28T09:00:00Z');

        for (const status of [500, 503, 403, 419]) {
            const result = await flushOutbox({ outbox, send: vi.fn(async () => Promise.reject({ response: { status, data: { message: 'x' } } })) });
            expect(result.failed).toBe(true);
        }
        expect(await outbox.pending()).toHaveLength(1);
    });

    it('drops a report the server refused for good, and says why', async () => {
        const outbox = await outboxOf('2026-09-28T09:00:00Z', '2026-09-28T10:00:00Z');
        const send = vi.fn()
            .mockRejectedValueOnce({ response: { status: 422, data: { errors: { location: ['Está fuera de la geocerca de la obra.'] } } } })
            .mockResolvedValueOnce({ id: 2 });

        const result = await flushOutbox({ outbox, send });

        expect(result).toEqual({ sent: 1, rejected: ['Está fuera de la geocerca de la obra.'], failed: false });
        expect(await outbox.pending()).toEqual([]);
    });
});
