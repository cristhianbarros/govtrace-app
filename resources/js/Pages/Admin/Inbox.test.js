// Iteración 18 — US-036 y US-037 (UI): la bandeja de entrada del
// Administrador. Revisar cada evidencia oculta — sus fotos, su comentario,
// su sello — y publicarla o rechazarla, de a una; desde "Publicadas",
// retirarla. Las reglas son del servidor (it. 15); aquí, la pantalla.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Inbox from './Inbox.vue';
import { decideOnEvidence, fetchInbox } from '@/services/api.js';
import { router } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
// It. 45f: el mapa pequeño del punto que fijó la ubicación (Leaflet se prueba en LocationMap.test.js).
vi.mock('@/Components/LocationMap.vue', () => ({
    default: defineComponent({
        props: { modelValue: { type: Object, default: null }, readonly: { type: Boolean, default: false } },
        setup: (props) => () =>
            h('div', { 'data-test': 'location-map', 'data-readonly': String(props.readonly) }, `${props.modelValue.latitude}, ${props.modelValue.longitude}`),
    }),
}));

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
    location: { anchored_worksite: false, distance_meters: 120, point: null, corrected: false },
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

    it('the count on the Bandeja tab goes down with each decision: it asks the server for it again', async () => {
        decideOnEvidence.mockResolvedValue({ message: PUBLISHED, status: 'Publicado' });
        const wrapper = await openInbox([evidence(), evidence({ id: 8 })]);

        await button(wrapper, 'Publicar').trigger('click');
        await flushPromises();

        expect(router.reload).toHaveBeenCalledWith({ only: ['inboxPending'] });
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

// It. 45f — la ubicación de cada evidencia, sin las coordenadas del veedor.
describe('La ubicación de cada evidencia (it. 45f)', () => {
    const ANCHORED = '📍 Este reporte fijó la ubicación oficial de la obra.';
    const anchoring = (overrides = {}) =>
        evidence({ location: { anchored_worksite: true, distance_meters: 0, point: { latitude: 11.2418781, longitude: -74.199 }, corrected: false, ...overrides } });

    it('La evidencia que fijó la ubicación de la obra llega marcada: with the point on a small map, and a link to correct it', async () => {
        const wrapper = await openInbox([anchoring()]);

        expect(wrapper.text()).toContain(ANCHORED);
        expect(wrapper.text()).not.toContain('Después se corrigió.');
        const map = wrapper.get('[data-test="location-map"]');
        expect(map.text()).toBe('11.2418781, -74.199');
        expect(map.attributes('data-readonly')).toBe('true');
        expect(wrapper.get('a[data-test="correct-location"]').attributes('href')).toBe('/admin/worksites?corregir=1');
        expect(wrapper.get('a[data-test="correct-location"]').text()).toBe('Corregir ubicación');
    });

    it('says when the location that evidence fixed was corrected afterwards', async () => {
        const wrapper = await openInbox([anchoring({ corrected: true })]);

        expect(wrapper.text()).toContain(`${ANCHORED} Después se corrigió.`);
    });

    it('Cada evidencia de la bandeja dice a qué distancia de la obra se tomó: without a map nor the coordinates of the veedor', async () => {
        const wrapper = await openInbox([evidence()]);

        expect(wrapper.text()).toContain('Tomada a 120 m de la obra.');
        expect(wrapper.text()).not.toContain(ANCHORED);
        expect(wrapper.find('[data-test="location-map"]').exists()).toBe(false);
    });

    it('says nothing of the location of an evidence received before it was recorded', async () => {
        const wrapper = await openInbox([evidence({ location: { anchored_worksite: false, distance_meters: null, point: null, corrected: false } })]);

        expect(wrapper.text()).not.toContain('Tomada a');
        expect(wrapper.text()).not.toContain(ANCHORED);
    });

    it('Rechazar la evidencia que fijó la ubicación no la cambia: the confirmation says so, and offers to correct it', async () => {
        const wrapper = await openInbox([anchoring()]);

        await button(wrapper, 'Rechazar').trigger('click');

        const warning = wrapper.get('[data-test="anchored-reject-warning"]');
        expect(warning.text()).toContain('Este reporte fijó la ubicación oficial de la obra. Rechazarlo no la cambia: si el lugar está mal, corríjalo en Obras.');
        expect(warning.get('a').attributes('href')).toBe('/admin/worksites?corregir=1');
        expect(warning.get('a').text()).toBe('Corregir ubicación');
    });

    it('does not warn about the location when rejecting any other evidence', async () => {
        const wrapper = await openInbox([evidence()]);

        await button(wrapper, 'Rechazar').trigger('click');

        expect(wrapper.find('[data-test="anchored-reject-warning"]').exists()).toBe(false);
    });
});

// It. 46e — R-PRIV-05 reescrita: lo que el veedor difuminó en el celular.
describe('Lo difuminado en el celular (it. 46e)', () => {
    const photoWith = (blurring) => evidence({ files: [{ id: 'f1', kind: 'photo', sha256: 'ab'.repeat(32), blurring }] });

    it('La Bandeja dice cuántas zonas se difuminaron', async () => {
        const wrapper = await openInbox([photoWith({ faces: 2, dismissed: 0, manual: 1 })]);

        expect(wrapper.get('[data-test="blurring"]').text()).toBe('3 zonas difuminadas en el celular');
        expect(wrapper.find('[data-test="dismissed-blur"]').exists()).toBe(false);
    });

    it('Quito un recuadro que no es un rostro, y la Bandeja lo marca: the Administrador sees that a proposed blur was removed', async () => {
        const wrapper = await openInbox([photoWith({ faces: 0, dismissed: 1, manual: 0 })]);

        expect(wrapper.get('[data-test="blurring"]').text()).toBe('Sin zonas difuminadas en el celular');
        expect(wrapper.get('[data-test="dismissed-blur"]').text()).toBe('⚠ Quien la envió quitó 1 difuminado que el detector propuso: revise que no se vea un rostro.');
    });

    it('says nothing of the blurring of a photo sent before it existed', async () => {
        const wrapper = await openInbox([photoWith(null)]);

        expect(wrapper.find('[data-test="blurring"]').exists()).toBe(false);
    });
});
