// Iteración 18 — US-036 y US-037 (UI): la bandeja de entrada del
// Administrador. Revisar cada evidencia oculta — sus fotos, su comentario,
// su sello — y publicarla o rechazarla, de a una; desde "Publicadas",
// retirarla. Las reglas son del servidor (it. 15); aquí, la pantalla.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Inbox from './Inbox.vue';
import { decideOnEvidence, fetchInbox } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const PUBLISHED = 'Evidencia publicada. Ya es visible en el mapa.';
const TOMBSTONE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';

const evidence = (overrides = {}) => ({
    id: 7,
    worksite_id: 1,
    worksite: { id: 1, name: 'Pavimentación Calle 30', municipality: 'Santa Marta' },
    observer: 'Carlos Pérez',
    classification: 'Retraso',
    comment: 'Obra detenida hace 2 meses',
    captured_at: '2026-09-28T15:00:00+00:00',
    received_at: '2026-09-28T15:02:00+00:00',
    suspicious_capture_time: false,
    files: [{ id: 70, kind: 'photo', sha256: 'ab'.repeat(32) }],
    seal: { merkle_root: 'cd'.repeat(32), tx_hash: 'ef'.repeat(32), ledger: 1201 },
    actions: ['publish', 'reject'],
    ...overrides,
});

async function openInbox(rows) {
    fetchInbox.mockResolvedValue(rows);
    const wrapper = mount(Inbox);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Bandeja de entrada', () => {
    it('shows that it is loading the evidences', () => {
        fetchInbox.mockReturnValue(new Promise(() => {}));

        expect(mount(Inbox).text()).toContain('Cargando evidencias…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchInbox.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([evidence()]);
        const wrapper = mount(Inbox);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Obra detenida hace 2 meses');
    });

    it('says when there is nothing to review', async () => {
        const wrapper = await openInbox([]);

        expect(fetchInbox).toHaveBeenCalledWith('hidden');
        expect(wrapper.text()).toContain('No hay evidencias por revisar.');
    });

    it('shows each evidence to review: its photos, classification, comment and seal', async () => {
        const wrapper = await openInbox([evidence()]);

        expect(wrapper.get('img').attributes('src')).toBe('/evidences/70/file');
        expect(wrapper.text()).toContain('Retraso');
        expect(wrapper.text()).toContain('Obra detenida hace 2 meses');
        expect(wrapper.text()).toContain('Con sello digital · bloque 1201');
        expect(wrapper.text()).not.toContain('Hora de captura sospechosa');
    });

    it('Publicar una evidencia: shows "Evidencia publicada. Ya es visible en el mapa." and takes it off the list', async () => {
        decideOnEvidence.mockResolvedValue({ message: PUBLISHED, status: 'Publicado' });
        const wrapper = await openInbox([evidence(), evidence({ id: 8, comment: 'Otra' })]);

        await button(wrapper, 'Publicar').trigger('click');
        await flushPromises();

        expect(decideOnEvidence).toHaveBeenCalledWith(7, 'publish', undefined);
        expect(wrapper.get('[role="status"]').text()).toBe(PUBLISHED);
        expect(wrapper.text()).not.toContain('Obra detenida hace 2 meses');
        expect(wrapper.text()).toContain('Otra');
    });

    it('No existe publicación masiva: every evidence has its own buttons, and nothing selects several', async () => {
        const wrapper = await openInbox([evidence(), evidence({ id: 8 }), evidence({ id: 9 })]);

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((candidate) => candidate.text() === 'Publicar')).toHaveLength(3);
        expect(wrapper.text()).not.toMatch(/Publicar todas|Publicar seleccionadas/);
    });

    it('Rechazar una evidencia con motivo: sends the reason and takes it off the list', async () => {
        decideOnEvidence.mockResolvedValue({ status: 'Rechazado' });
        const wrapper = await openInbox([evidence()]);

        await button(wrapper, 'Rechazar').trigger('click');
        await wrapper.get('textarea').setValue('La foto no corresponde a la obra');
        await button(wrapper, 'Confirmar rechazo').trigger('click');
        await flushPromises();

        expect(decideOnEvidence).toHaveBeenCalledWith(7, 'reject', 'La foto no corresponde a la obra');
        expect(wrapper.get('[role="status"]').text()).toBe('Evidencia rechazada. Su veedor verá el motivo.');
        expect(wrapper.text()).not.toContain('Obra detenida hace 2 meses');
    });

    it.each(['', '   '])('Rechazar exige un motivo: "%s" does not reject', async (reason) => {
        const wrapper = await openInbox([evidence()]);

        await button(wrapper, 'Rechazar').trigger('click');
        await wrapper.get('textarea').setValue(reason);

        expect(button(wrapper, 'Confirmar rechazo').attributes('disabled')).toBeDefined();
        expect(decideOnEvidence).not.toHaveBeenCalled();
    });

    it('Evidencia marcada por hora sospechosa: shows the mark, and it is decided like the others', async () => {
        decideOnEvidence.mockResolvedValue({ message: PUBLISHED, status: 'Publicado' });
        const wrapper = await openInbox([evidence({ suspicious_capture_time: true })]);

        expect(wrapper.text()).toContain('Hora de captura sospechosa');
        await button(wrapper, 'Publicar').trigger('click');
        await flushPromises();

        expect(decideOnEvidence).toHaveBeenCalledWith(7, 'publish', undefined);
    });

    it('shows why the server refused a decision, on that evidence', async () => {
        const message = 'La evidencia todavía no está sellada: se publica cuando llegue a «Sellada».';
        decideOnEvidence.mockRejectedValue({ response: { status: 409, data: { message } } });
        const wrapper = await openInbox([evidence()]);

        await button(wrapper, 'Publicar').trigger('click');
        await flushPromises();

        expect(wrapper.get('article [role="alert"]').text()).toBe(message);
        expect(wrapper.text()).toContain('Obra detenida hace 2 meses');
    });
});

describe('Publicadas', () => {
    async function openPublished(rows) {
        const wrapper = await openInbox([]);
        fetchInbox.mockResolvedValue(rows);
        await button(wrapper, 'Publicadas').trigger('click');
        await flushPromises();
        return wrapper;
    }

    it('says when there is nothing published', async () => {
        const wrapper = await openPublished([]);

        expect(fetchInbox).toHaveBeenLastCalledWith('published');
        expect(wrapper.text()).toContain('No hay evidencias publicadas.');
    });

    it('Retiro con motivo: warns about the tombstone, sends the reason and takes it off the list', async () => {
        decideOnEvidence.mockResolvedValue({ status: 'Retirado' });
        const wrapper = await openPublished([evidence({ actions: ['withdraw'] })]);

        expect(button(wrapper, 'Publicar')).toBeUndefined();
        await button(wrapper, 'Retirar').trigger('click');
        expect(wrapper.text()).toContain(TOMBSTONE);
        await wrapper.get('textarea').setValue('Aparece un menor de edad identificable');
        await button(wrapper, 'Confirmar retiro').trigger('click');
        await flushPromises();

        expect(decideOnEvidence).toHaveBeenCalledWith(7, 'withdraw', 'Aparece un menor de edad identificable');
        expect(wrapper.get('[role="status"]').text()).toBe('Evidencia retirada. En el mapa queda su lápida.');
        expect(wrapper.text()).not.toContain('Obra detenida hace 2 meses');
    });

    it('El motivo es obligatorio: without it the evidence is not withdrawn', async () => {
        const wrapper = await openPublished([evidence({ actions: ['withdraw'] })]);

        await button(wrapper, 'Retirar').trigger('click');

        expect(button(wrapper, 'Confirmar retiro').attributes('disabled')).toBeDefined();
    });
});

describe('Contexto de cada evidencia (it. 40b)', () => {
    it('Cada evidencia de la bandeja dice de qué obra es y quién la envió: the worksite, its municipality and the veedor', async () => {
        const wrapper = await openInbox([evidence()]);
        const card = wrapper.get('article');

        expect(card.get('[data-test="worksite"]').text()).toBe('Pavimentación Calle 30');
        expect(card.text()).toContain('Santa Marta');
        expect(card.text()).toContain('Enviada por Carlos Pérez');
    });

    it('still shows the evidence when the veedor is no longer known', async () => {
        const wrapper = await openInbox([evidence({ observer: null })]);

        expect(wrapper.get('[data-test="worksite"]').text()).toBe('Pavimentación Calle 30');
        expect(wrapper.text()).not.toContain('Enviada por');
    });
});
