// Iteración 26 — US-027 (UI): el mapa público de la organización, sin
// sesión, con sus pines por color (R-MAP-02) y el aviso de organización
// suspendida (R-AUD-01). El mapa de Leaflet se prueba en PinsMap.test.js.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Map from './Map.vue';
import { fetchMapFilters, fetchPins, fetchWorksiteList } from '@/services/api.js';
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
    fetchMapFilters.mockResolvedValue({ municipalities: [{ code: '47189', name: 'Ciénaga' }, { code: '47001', name: 'Santa Marta' }] });
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

    it('leads to the statistics of the territory and to the public validator, by its tabs (US-051-RPT, US-024, it. 40e)', async () => {
        const wrapper = await openMap();
        const sections = wrapper.get('nav[aria-label="Secciones de la veeduría"]');

        expect(sections.get('a[href="/stats"]').text()).toBe('Estadísticas');
        expect(sections.get('a[href="/verify"]').text()).toBe('Validar');
        // Ya no hay botones sueltos al pie del mapa.
        expect(wrapper.get('main').find('a[href="/stats"]').exists()).toBe(false);
        expect(wrapper.get('main').find('a[href="/verify"]').exists()).toBe(false);
    });

    it('warns that the organization is suspended, and still shows its map', async () => {
        page.props.organizationNotice = SUSPENDED;

        const wrapper = await openMap();

        expect(wrapper.get('[role="status"]').text()).toBe(SUSPENDED);
        expect(wrapper.findAll('[data-test="pin"]')).toHaveLength(3);
    });
});

describe('Filtros del mapa (US-028)', () => {
    const apply = (wrapper) => wrapper.findAll('button').find((button) => button.text() === 'Aplicar');

    it('No se puede aplicar sin elegir ningún filtro', async () => {
        const wrapper = await openMap();

        expect(apply(wrapper).attributes('disabled')).toBeDefined();
        await wrapper.get('select#filter-municipality').setValue('47001');
        expect(apply(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('Filtrar por estado, fechas, presupuesto y municipio: asks for the pins that meet the 4', async () => {
        const wrapper = await openMap();
        fetchPins.mockResolvedValue([pins[2]]);

        await wrapper.get('select#filter-status').setValue('red');
        await wrapper.get('input#filter-from').setValue('2026-09-01');
        await wrapper.get('input#filter-to').setValue('2026-09-30');
        await wrapper.get('input#filter-min-value').setValue('1000000000');
        await wrapper.get('select#filter-municipality').setValue('47001');
        await apply(wrapper).trigger('click');
        await flushPromises();

        expect(fetchPins).toHaveBeenLastCalledWith({ status: 'red', from: '2026-09-01', to: '2026-09-30', min_value: '1000000000', municipality: '47001' });
        expect(wrapper.findAll('[data-test="pin"]').map((pin) => pin.attributes('data-color'))).toEqual(['red']);
    });

    it('Ninguna obra coincide: the message of US-028', async () => {
        const wrapper = await openMap();
        fetchPins.mockResolvedValue([]);

        await wrapper.get('input#filter-min-value').setValue('900000000000');
        await apply(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(NO_WORKSITES);
    });

    it('offers the municipalities of the map, and clears the filters', async () => {
        const wrapper = await openMap();

        expect(wrapper.findAll('select#filter-municipality option').map((option) => option.text())).toEqual(['Todos', 'Ciénaga', 'Santa Marta']);
        await wrapper.get('select#filter-status').setValue('yellow');
        await wrapper.findAll('button').find((button) => button.text() === 'Limpiar').trigger('click');
        await flushPromises();

        expect(fetchPins).toHaveBeenLastCalledWith({});
        expect(wrapper.get('select#filter-status').element.value).toBe('');
    });
});

describe('Los estados, junto al mapa (it. 40b)', () => {
    const states = (wrapper) => wrapper.findAll('[data-test="state"]');

    it('Los estados del mapa se explican junto al mapa, con ícono y palabra: one button per state, with how many worksites, above the map', async () => {
        const wrapper = await openMap([...pins, { id: 11, lat: 11.2, lng: -74.2, color_pin: 'green' }]);

        expect(states(wrapper).map((state) => state.text())).toEqual(['✓ Normal 2', '! Alerta 1', '✕ En riesgo 1']);
        const html = wrapper.html();
        expect(html.indexOf('data-test="state"')).toBeLessThan(html.indexOf('data-test="pin"'));
    });

    it('shows only the worksites of a state when it is touched, and all of them again when touched twice', async () => {
        const wrapper = await openMap();
        fetchPins.mockResolvedValue([pins[2]]);

        await states(wrapper)[2].trigger('click');
        await flushPromises();

        expect(fetchPins).toHaveBeenLastCalledWith({ status: 'red' });
        expect(states(wrapper)[2].attributes('aria-pressed')).toBe('true');
        expect(states(wrapper).map((state) => state.text())).toEqual(['✓ Normal 1', '! Alerta 1', '✕ En riesgo 1']);

        fetchPins.mockResolvedValue(pins);
        await states(wrapper)[2].trigger('click');
        await flushPromises();
        expect(fetchPins).toHaveBeenLastCalledWith({});
        expect(states(wrapper)[2].attributes('aria-pressed')).toBe('false');
    });

    it('names the map and says what to do with it', async () => {
        const wrapper = await openMap();

        expect(wrapper.get('h1').text()).toBe('Obras vigiladas');
        expect(wrapper.text()).toContain('Toque un punto para ver la obra y sus fotos.');
    });
});

describe('El mapa, también como lista (it. 40c)', () => {
    const LIST = [
        { id: 3, name: 'Pavimentación de la Calle 30', municipality: 'Santa Marta', color_pin: 'green' },
        { id: 9, name: 'Mejoramiento de la vía Ciénaga – Sevilla', municipality: 'Ciénaga', color_pin: 'red' },
    ];

    async function openList() {
        const wrapper = await openMap();
        fetchWorksiteList.mockResolvedValue(LIST);
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();
        return wrapper;
    }

    it('shows the worksites as a list, with their state in words, asked for only when the list is opened', async () => {
        const wrapper = await openMap();
        expect(fetchWorksiteList).not.toHaveBeenCalled();

        fetchWorksiteList.mockResolvedValue(LIST);
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();

        const items = wrapper.findAll('[data-test="listed"]');
        expect(items.map((item) => item.text())).toEqual(['✓ Normal Pavimentación de la Calle 30 Santa Marta', '✕ En riesgo Mejoramiento de la vía Ciénaga – Sevilla Ciénaga']);
        expect(items[1].get('a').attributes('href')).toBe('/worksite/9');
        expect(wrapper.find('[data-test="pin"]').exists()).toBe(false);
    });

    it('Buscar una obra por su nombre: filters the list as the person writes, without caring about accents or capitals', async () => {
        const wrapper = await openList();

        await wrapper.get('input[type="search"]').setValue('cienaga');
        expect(wrapper.findAll('[data-test="listed"]').map((item) => item.get('a').attributes('href'))).toEqual(['/worksite/9']);

        await wrapper.get('input[type="search"]').setValue('parque');
        expect(wrapper.find('[data-test="listed"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Ninguna obra se llama así. Pruebe con otra palabra.');
    });
});

describe('Cómo funciona (it. 40d)', () => {
    it('explains, next to the map, how the evidence gets there, for whoever arrives for the first time', async () => {
        const wrapper = await openMap();
        const how = wrapper.get('details[data-test="how"]');

        expect(how.get('summary').text()).toBe('¿Cómo funciona?');
        expect(how.findAll('li')).toHaveLength(3);
        expect(how.text()).toContain('La veeduría los revisa y publica aquí');
    });

    it('Los colores del mapa son alertas de GovTrace: said in "¿Cómo funciona?" (it. 44a)', async () => {
        const wrapper = await openMap();
        const howItWorks = wrapper.findAll('details').find((details) => details.get('summary').text() === '¿Cómo funciona?');

        expect(howItWorks.text()).toContain('Los colores (Normal, Alerta y En riesgo) son alertas de GovTrace, no decisiones de una autoridad. Cada obra explica por qué tiene el suyo.');
    });

    it('La política está enlazada donde se entra: the map of a veeduría (it. 44e)', async () => {
        const wrapper = await openMap();

        expect(wrapper.findAll('a').find((link) => link.text() === 'Política de tratamiento de datos').attributes('href')).toBe('/privacidad');
    });
});
