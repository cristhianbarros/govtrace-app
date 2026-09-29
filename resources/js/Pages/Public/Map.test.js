// Iteración 26 — US-027 (UI): el mapa público de la organización, sin
// sesión, con sus pines por color (R-MAP-02) y el aviso de organización
// suspendida (R-AUD-01). El mapa de Leaflet se prueba en PinsMap.test.js.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Map from './Map.vue';
import { fetchPins } from '@/services/api.js';
import { page, router } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
// Cada pin, un botón con su color que "se toca" en el mapa.
vi.mock('@/Components/Public/PinsMap.vue', () => ({
    default: defineComponent({
        props: { pins: { type: Array, required: true } },
        emits: ['select'],
        setup: (props, { emit }) => () =>
            h('div', props.pins.map((pin) => h('button', { type: 'button', 'data-test': 'pin', 'data-color': pin.color_pin, onClick: () => emit('select', pin.id) }, pin.color_pin))),
    }),
}));

const SUSPENDED = '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.';
const NO_WORKSITES = 'No se encontraron obras o evidencias que coincidan con estos filtros en este territorio.';

const pins = [
    { id: 3, lat: 11.241, lng: -74.199, color_pin: 'green' },
    { id: 7, lat: 11.235, lng: -74.21, color_pin: 'yellow' },
    { id: 9, lat: 11.25, lng: -74.19, color_pin: 'red' },
];

async function openMap(answer = pins) {
    fetchPins.mockResolvedValue(answer);
    const wrapper = mount(Map);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', organizationLogo: null, organizationNotice: null };
});

describe('Mapa público', () => {
    it('shows that it is loading the worksites', () => {
        fetchPins.mockReturnValue(new Promise(() => {}));

        expect(mount(Map).text()).toContain('Cargando obras…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchPins.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(pins);
        const wrapper = mount(Map);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('[data-test="pin"]')).toHaveLength(3);
    });

    it('says when the organization has no worksite on its map yet', async () => {
        const wrapper = await openMap([]);

        expect(wrapper.text()).toContain(NO_WORKSITES);
        expect(wrapper.find('[data-test="pin"]').exists()).toBe(false);
    });

    it('draws a pin per worksite with its color, and explains the colors', async () => {
        const wrapper = await openMap();

        expect(wrapper.findAll('[data-test="pin"]').map((pin) => pin.attributes('data-color'))).toEqual(['green', 'yellow', 'red']);
        expect(wrapper.text()).toContain('Normal');
        expect(wrapper.text()).toContain('Alerta');
        expect(wrapper.text()).toContain('En riesgo');
        expect(wrapper.text()).toContain('Veeduría Ciudadana Santa Marta');
    });

    it('opens the view of the worksite when its pin is touched', async () => {
        const wrapper = await openMap();

        await wrapper.findAll('[data-test="pin"]')[1].trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/worksite/7');
    });

    it('leads to the public validator (US-024)', async () => {
        const wrapper = await openMap();

        expect(wrapper.get('a[href="/verify"]').text()).toBe('Validar un archivo');
    });

    it('warns that the organization is suspended, and still shows its map', async () => {
        page.props.organizationNotice = SUSPENDED;

        const wrapper = await openMap();

        expect(wrapper.get('[role="status"]').text()).toBe(SUSPENDED);
        expect(wrapper.findAll('[data-test="pin"]')).toHaveLength(3);
    });
});
