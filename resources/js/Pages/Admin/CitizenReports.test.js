// Iteración 44f — US-059-LEG: los informes de los ciudadanos, como los ve el
// Administrador — sin el correo de quien los envió — para responder o descartar.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import CitizenReports from './CitizenReports.vue';
import { answerCitizenReport, confirmCitizenLocation, discardCitizenReport, fetchCitizenReports } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
// It. 58: el mapa de Leaflet se prueba en LocationMap.test.js; aquí, qué puntos recibe.
vi.mock('@/Components/LocationMap.vue', () => ({
    default: defineComponent({
        name: 'LocationMap',
        props: { modelValue: { type: Object, required: true }, others: { type: Array, default: () => [] }, label: { type: String, default: null }, readonly: Boolean },
        setup: () => () => h('div', { 'data-test': 'location-map' }),
    }),
}));

/** What the server answers (it. 52): the page, how many there are and whether there are more. */
const answer = (rows, extra = {}) => ({ data: rows, total: rows.length, has_more: false, ...extra });


// It. 46c (US-064-SEC): identificadores públicos, y la referencia corta que recibió el ciudadano.
const report = (changes = {}) => ({
    id: '01j9xq3m7v8k2d4f6g8h07kq3m',
    reference: '07KQ-3M9D',
    worksite_id: '01j9xq3m7v8k2d4f6g8h0jkmnp',
    worksite: 'Pavimentación Calle 30',
    received_at: '2026-09-30T15:00:00+00:00',
    message: 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.',
    photo_urls: ['/citizen-reports/01j9xq3m7v8k2d4f6g8h07kq3m/photos/1'],
    status: 'new',
    status_label: 'Nuevo',
    answer: null,
    ...changes,
});
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function openReports(rows = [report()]) {
    fetchCitizenReports.mockResolvedValue(answer(rows));
    const wrapper = mount(CitizenReports);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Informes ciudadanos', () => {
    it('La veeduría recibe los informes sin ver el correo del ciudadano: the worksite, the date, the message, the photo and its state', async () => {
        const wrapper = await openReports();
        const card = wrapper.get('[data-test="citizen-report"]');

        expect(card.text()).toContain('Pavimentación Calle 30');
        expect(card.text()).toContain('30/09/2026');
        expect(card.text()).toContain('La obra lleva dos semanas sin trabajadores');
        expect(card.text()).toContain('Nuevo');
        expect(card.text()).toContain('Informe n.º 07KQ-3M9D');
        expect(card.get('img').attributes('src')).toBe('/citizen-reports/01j9xq3m7v8k2d4f6g8h07kq3m/photos/1');
        expect(wrapper.text()).toContain('Ley 850 de 2003, art. 18');
        expect(wrapper.text()).not.toContain('@');
    });

    it('El ciudadano adjunta de 1 a 3 fotos: shows each one, numbered, and each opens on its own', async () => {
        const base = '/citizen-reports/01j9xq3m7v8k2d4f6g8h07kq3m/photos';
        const card = (await openReports([report({ photo_urls: [`${base}/1`, `${base}/2`, `${base}/3`] })])).get('[data-test="citizen-report"]');

        expect(card.findAll('img').map((image) => image.attributes('src'))).toEqual([`${base}/1`, `${base}/2`, `${base}/3`]);
        expect(card.findAll('img').map((image) => image.attributes('alt'))).toEqual(['Foto 1 de 3 que envió el ciudadano', 'Foto 2 de 3 que envió el ciudadano', 'Foto 3 de 3 que envió el ciudadano']);
        expect(card.findAll('a[target="_blank"]').map((link) => link.attributes('href'))).toEqual([`${base}/1`, `${base}/2`, `${base}/3`]);
    });

    it('shows no photo for a report without one', async () => {
        const card = (await openReports([report({ photo_urls: [] })])).get('[data-test="citizen-report"]');

        expect(card.find('img').exists()).toBe(false);
    });

    it('says when there are no reports yet', async () => {
        expect((await openReports([])).text()).toContain('Aún no hay informes de ciudadanos.');
    });

    it('La veeduría responde al ciudadano: writes the answer, and the list shows it answered', async () => {
        answerCitizenReport.mockResolvedValue({ message: 'Respuesta enviada al ciudadano.' });
        const wrapper = await openReports();
        fetchCitizenReports.mockResolvedValue(answer([report({ status: 'answered', status_label: 'Atendido', answer: 'Gracias. El sábado va un veedor.' })]));

        await button(wrapper, 'Responder').trigger('click');
        await wrapper.get('textarea').setValue('Gracias. El sábado va un veedor.');
        await button(wrapper, 'Enviar respuesta').trigger('click');
        await flushPromises();

        expect(answerCitizenReport).toHaveBeenCalledWith('01j9xq3m7v8k2d4f6g8h07kq3m', 'Gracias. El sábado va un veedor.');
        expect(wrapper.get('[role="status"]').text()).toBe('Respuesta enviada al ciudadano.');
        expect(wrapper.get('[data-test="citizen-report"]').text()).toContain('Atendido');
        expect(wrapper.get('[data-test="citizen-report"]').text()).toContain('Gracias. El sábado va un veedor.');
    });

    it('La veeduría descarta un informe: it asks to confirm first', async () => {
        discardCitizenReport.mockResolvedValue({ message: 'Informe descartado.' });
        const wrapper = await openReports();

        await button(wrapper, 'Descartar').trigger('click');
        expect(discardCitizenReport).not.toHaveBeenCalled();
        await button(wrapper, 'Confirmar descarte').trigger('click');
        await flushPromises();

        expect(discardCitizenReport).toHaveBeenCalledWith('01j9xq3m7v8k2d4f6g8h07kq3m');
        expect(wrapper.get('[role="status"]').text()).toBe('Informe descartado.');
    });
});

// It. 52 — de a una página, y por estado.
describe('Los informes de a una página, por estado (it. 52)', () => {
    it('Veo 20 informes y Ver 20 más trae los siguientes: appends the next page, says how many there are', async () => {
        const wrapper = await openReports([report()]);
        fetchCitizenReports.mockResolvedValue(answer([report({ id: 'dos', message: 'Segundo' })], { total: 21, has_more: true }));
        await wrapper.get('select#status').setValue('new');
        await flushPromises();
        expect(wrapper.get('[data-test="more"]').exists()).toBe(true);

        fetchCitizenReports.mockResolvedValue(answer([report({ id: 'tres', message: 'Tercero' })], { total: 21 }));
        await wrapper.get('[data-test="more"]').trigger('click');
        await flushPromises();

        expect(fetchCitizenReports).toHaveBeenLastCalledWith({ status: 'new', page: 2 });
        expect(wrapper.findAll('[data-test="citizen-report"]')).toHaveLength(2);
        expect(wrapper.find('[data-test="more"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="total"]').text()).toBe('21 informes');
    });

    it('Filtro los informes por estado: from the first page, and Todos brings them all back', async () => {
        const wrapper = await openReports([report()]);

        await wrapper.get('select#status').setValue('answered');
        await flushPromises();
        expect(fetchCitizenReports).toHaveBeenLastCalledWith({ status: 'answered', page: 1 });

        await wrapper.get('select#status').setValue('');
        await flushPromises();
        expect(fetchCitizenReports).toHaveBeenLastCalledWith({ page: 1 });
    });

    it('says so when no report has that status', async () => {
        const wrapper = await openReports([report()]);
        fetchCitizenReports.mockResolvedValue(answer([]));

        await wrapper.get('select#status').setValue('discarded');
        await flushPromises();

        expect(wrapper.text()).toContain('Ningún informe con ese estado.');
    });
});

// It. 58 (US-035): la ubicación que propuso el ciudadano para una obra sin ubicación.
describe('La ubicación que propuso el ciudadano (it. 58)', () => {
    const MINCA = { latitude: 11.1427, longitude: -74.1173 };
    const OTHER = { latitude: 11.15, longitude: -74.12 };
    const proposed = (changes = {}) => report({ worksite: 'Construcción de la vía a Minca', photo_urls: [], proposed_location: { ...MINCA, status: 'unlocated', others: [], ...changes } });

    it('Confirmar la ubicación que propuso un ciudadano: shows the point on a map, asks to confirm, and reloads the list', async () => {
        confirmCitizenLocation.mockResolvedValue({ message: 'La ubicación de la obra quedó confirmada: ya aparece en el mapa, y la geocerca de 500 m está activa para los veedores.' });
        const wrapper = await openReports([proposed()]);
        const block = wrapper.get('[data-test="proposed-location"]');

        expect(block.text()).toContain('El ciudadano dice que la obra queda aquí.');
        expect(block.text()).toContain('La obra aún no tiene ubicación oficial.');
        expect(wrapper.findComponent({ name: 'LocationMap' }).props()).toMatchObject({ modelValue: MINCA, others: [], readonly: true, label: 'Donde el ciudadano dice que queda la obra' });

        fetchCitizenReports.mockResolvedValue(answer([proposed({ status: 'confirmed' })]));
        await button(wrapper, 'Confirmar esta ubicación').trigger('click');
        expect(confirmCitizenLocation).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('La obra aparecerá en el mapa en este punto, y la geocerca de los veedores se medirá desde aquí.');
        await button(wrapper, 'Sí, confirmar').trigger('click');
        await flushPromises();

        expect(confirmCitizenLocation).toHaveBeenCalledWith('01j9xq3m7v8k2d4f6g8h07kq3m');
        expect(wrapper.get('[role="status"]').text()).toBe('La ubicación de la obra quedó confirmada: ya aparece en el mapa, y la geocerca de 500 m está activa para los veedores.');
        expect(wrapper.get('[data-test="proposed-location"]').text()).toContain('Ubicación confirmada aquí.');
        expect(button(wrapper, 'Confirmar esta ubicación')).toBeUndefined();
    });

    it('Con varias ubicaciones propuestas, confirmar una cierra las demás: the map shows the other one, and once the work has its location there is nothing to confirm', async () => {
        const wrapper = await openReports([proposed({ others: [OTHER] })]);
        expect(wrapper.findComponent({ name: 'LocationMap' }).props('others')).toEqual([OTHER]);
        expect(wrapper.get('[data-test="other-proposals"]').text()).toBe('Gris: otra ubicación que propuso un ciudadano para esta obra.');

        // Otro informe confirmó la suya: esta ya no se ofrece.
        const closed = await openReports([proposed({ status: 'elsewhere' })]);
        expect(closed.get('[data-test="proposed-location"]').text()).toContain('La obra ya tiene ubicación oficial, en otro lugar.');
        expect(button(closed, 'Confirmar esta ubicación')).toBeUndefined();
    });

    it('offers nothing to confirm for a discarded report, and shows no block for a report without a place', async () => {
        const wrapper = await openReports([proposed(), report({ id: '01j9xq3m7v8k2d4f6g8h07kq3n', proposed_location: null })].map((row, index) => (index === 0 ? { ...row, status: 'discarded', status_label: 'Descartado' } : row)));

        expect(wrapper.findAll('[data-test="proposed-location"]')).toHaveLength(1);
        expect(button(wrapper, 'Confirmar esta ubicación')).toBeUndefined();
    });
});
