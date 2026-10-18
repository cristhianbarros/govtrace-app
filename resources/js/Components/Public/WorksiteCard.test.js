// Iteración 60 — US-029: la tarjeta de una obra sobre el mapa, al tocar su
// pin: su estado, su nombre, su municipio, su valor, la última evidencia y
// hasta 3 miniaturas, sin salir del mapa. El servidor la arma (WorksiteCardTest).
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WorksiteCard from './WorksiteCard.vue';
import { formatDay } from '@/lib/format.js';
import { fetchWorksiteCard } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

enableAutoUnmount(afterEach);

const ID = '01j9xq3m7v8k2d4f6g8h0jkmnp';
const card = (changes = {}) => ({
    id: ID,
    name: 'Pavimentación Calle 30',
    municipality: 'Santa Marta',
    value: 1250000000,
    condition: { color: 'yellow', label: 'Alerta', reason: 'El reporte publicado más reciente, del 12/10/2026, es de retraso.' },
    last_evidence_at: '2026-10-12T15:00:00+00:00',
    photos: [
        { report_id: '01j9xq3m7v8k2d4f6g8h0jkm12', thumbnail_url: '/public/evidences/a1/thumbnail' },
        { report_id: '01j9xq3m7v8k2d4f6g8h0jkm11', thumbnail_url: '/public/evidences/b2/thumbnail' },
        { report_id: '01j9xq3m7v8k2d4f6g8h0jkm11', thumbnail_url: '/public/evidences/b3/photo' },
    ],
    ...changes,
});

async function openCard(answer = card()) {
    fetchWorksiteCard.mockResolvedValue(answer);
    const wrapper = mount(WorksiteCard, { props: { worksiteId: ID }, attachTo: document.body });
    await flushPromises();
    return wrapper;
}

const link = (wrapper, text) => wrapper.findAll('a').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
});

describe('La tarjeta de la obra (it. 60)', () => {
    it('Al tocar un pin se abre la tarjeta de la obra: its state and why, name, municipality and value, the newest date, 3 thumbnails and the two ways on', async () => {
        const wrapper = await openCard();
        const dialog = wrapper.get('[role="dialog"]');

        expect(fetchWorksiteCard).toHaveBeenCalledWith(ID);
        expect(dialog.attributes('aria-labelledby')).toBe(dialog.get('h2').attributes('id'));
        expect(dialog.get('h2').text()).toBe('Pavimentación Calle 30');
        expect(dialog.get('[data-test="condition"]').text()).toContain('! Alerta');
        expect(dialog.get('[data-test="condition"]').text()).toContain('El reporte publicado más reciente, del 12/10/2026, es de retraso.');
        expect(dialog.get('[data-test="place"]').text()).toBe('Santa Marta · $1.250.000.000');
        expect(dialog.get('[data-test="last-evidence"]').text()).toBe(`Última evidencia publicada: ${formatDay('2026-10-12T15:00:00+00:00')}`);
        expect(dialog.findAll('[data-test="card-thumbnail"] img').map((image) => image.attributes('src'))).toEqual(['/public/evidences/a1/thumbnail', '/public/evidences/b2/thumbnail', '/public/evidences/b3/photo']);
        expect(link(wrapper, 'Ver la obra').attributes('href')).toBe(`/worksite/${ID}`);
        expect(link(wrapper, 'Informar a la veeduría').attributes('href')).toBe(`/worksite/${ID}#informar`);
    });

    it('Una obra sin evidencias publicadas lo dice en su tarjeta: no thumbnails, and no value when SECOP has none', async () => {
        const wrapper = await openCard(card({ last_evidence_at: null, photos: [], value: null, condition: { color: 'green', label: 'Normal', reason: 'Aún no hay evidencias publicadas.' } }));

        expect(wrapper.get('[data-test="last-evidence"]').text()).toBe('Sin evidencias publicadas todavía.');
        expect(wrapper.find('[data-test="card-thumbnail"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="place"]').text()).toBe('Santa Marta');
    });

    it('Una miniatura lleva a esa evidencia en la página de la obra', async () => {
        const wrapper = await openCard();

        expect(wrapper.findAll('[data-test="card-thumbnail"]').map((thumbnail) => thumbnail.attributes('href'))).toEqual([
            `/worksite/${ID}#evidencia-01j9xq3m7v8k2d4f6g8h0jkm12`,
            `/worksite/${ID}#evidencia-01j9xq3m7v8k2d4f6g8h0jkm11`,
            `/worksite/${ID}#evidencia-01j9xq3m7v8k2d4f6g8h0jkm11`,
        ]);
        expect(wrapper.get('[data-test="card-thumbnail"]').attributes('aria-label')).toBe('Ver la foto 1 de 3 en la página de la obra');
    });

    it('La tarjeta se usa con el teclado: the focus goes to it, and Esc or "Cerrar" close it', async () => {
        const wrapper = await openCard();

        expect(document.activeElement).toBe(wrapper.get('[role="dialog"]').element);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('close')).toHaveLength(1);
        await wrapper.get('button[aria-label="Cerrar la tarjeta"]').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(2);
    });

    it('says it is loading, and when it could not load, with a retry', async () => {
        fetchWorksiteCard.mockReturnValue(new Promise(() => {}));
        const loading = mount(WorksiteCard, { props: { worksiteId: ID } });
        expect(loading.text()).toContain('Cargando la obra…');

        fetchWorksiteCard.mockReset();
        fetchWorksiteCard.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(card());
        const wrapper = mount(WorksiteCard, { props: { worksiteId: ID } });
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.get('h2').text()).toBe('Pavimentación Calle 30');
    });
});

// It. 66b (US-069): cuántos ciudadanos informaron, también en la tarjeta del mapa.
describe('Lo que informan los ciudadanos, en la tarjeta (it. 66b)', () => {
    it('El número público es solo eso: sin mensajes, fotos ni nombres — the card says how many and the state', async () => {
        const wrapper = await openCard(card({ citizens: { count: 3, status: 'Un veedor la está documentando', open: true } }));

        expect(wrapper.get('[data-test="card-citizens"]').text()).toBe('3 ciudadanos informaron sobre esta obra · Un veedor la está documentando');
    });

    it('Un informe descartado no cuenta en público: no line when nobody counts', async () => {
        const wrapper = await openCard(card({ citizens: null }));

        expect(wrapper.find('[data-test="card-citizens"]').exists()).toBe(false);
    });
});
