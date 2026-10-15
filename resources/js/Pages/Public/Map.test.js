// Iteración 26 — US-027 (UI): el mapa público de la organización, sin
// sesión, con sus pines por color (R-MAP-02) y el aviso de organización
// suspendida (R-AUD-01). El mapa de Leaflet se prueba en PinsMap.test.js.
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Map from './Map.vue';
import { fetchMapFilters, fetchPins, fetchUnlocatedWorksites, fetchWorksiteCard, fetchWorksiteList } from '@/services/api.js';
import { page, router } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
// Cada pin, un botón con su color que "se toca" en el mapa.
// It. 60: al cerrar la tarjeta, el foco vuelve al pin (focusPin).
const focusPin = vi.fn();
vi.mock('@/Components/Public/PinsMap.vue', () => ({
    default: defineComponent({
        props: { pins: { type: Array, required: true } },
        emits: ['select'],
        setup: (props, { emit, expose }) => {
            expose({ focusPin });
            return () => h('div', props.pins.map((pin) => h('button', { type: 'button', 'data-test': 'pin', 'data-color': pin.color_pin, onClick: () => emit('select', pin.id) }, pin.color_pin)));
        },
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

// It. 58: cada mapa se desmonta al terminar su prueba, y con él la búsqueda que esperaba.
enableAutoUnmount(afterEach);

beforeEach(() => {
    vi.resetAllMocks();
    fetchMapFilters.mockResolvedValue({ municipalities: [{ code: '47189', name: 'Ciénaga' }, { code: '47001', name: 'Santa Marta' }] });
    fetchUnlocatedWorksites.mockResolvedValue({ data: [], has_more: false });
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

    // It. 60: tocar un pin ya no saca del mapa: abre la tarjeta de la obra.
    it('La tarjeta de una obra se pide al tocar su pin: not with the map, only that one, and closing it gives the focus back to the pin', async () => {
        fetchWorksiteCard.mockResolvedValue({ id: 7, name: 'Pavimentación Calle 30', municipality: 'Santa Marta', value: null, condition: { color: 'yellow', label: 'Alerta', reason: 'Retraso.' }, last_evidence_at: null, photos: [] });
        const wrapper = await openMap();
        expect(fetchWorksiteCard).not.toHaveBeenCalled();

        await wrapper.findAll('[data-test="pin"]')[1].trigger('click');
        await flushPromises();

        expect(fetchWorksiteCard).toHaveBeenCalledWith(7);
        expect(router.visit).not.toHaveBeenCalled();
        expect(wrapper.get('[role="dialog"] h2').text()).toBe('Pavimentación Calle 30');
        expect(wrapper.find('[data-test="pin"]').exists()).toBe(true);

        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        await flushPromises();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(focusPin).toHaveBeenCalledWith(7);
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

    it('El mapa dice que cada punto es aproximado: a note under the map, in words and not only in the circle', async () => {
        const wrapper = await openMap();

        expect(wrapper.get('[data-test="approximate-note"]').text()).toBe('Cada punto es aproximado: la obra está a unos 100 m de él. Es para proteger a quien tomó la foto.');
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

// It. 58 (US-059-LEG): las obras que aún no están en el mapa, para informar de cualquiera.
describe('Las obras sin ubicación todavía (it. 58)', () => {
    const MINCA = { contract_id: 'CO1.PCCNTR.7654321', worksite_id: null, name: 'Construcción de la vía a Minca', municipality: 'Santa Marta', entity_name: 'Gobernación del Magdalena' };
    const BONDA = { contract_id: 'CO1.PCCNTR.7654322', worksite_id: '01j9xq3m7v8k2d4f6g8h0jkmnp', name: 'Andén de Bonda', municipality: 'Santa Marta', entity_name: null };

    async function openList(answer = { data: [MINCA, BONDA], has_more: false }) {
        const wrapper = await openMap();
        fetchWorksiteList.mockResolvedValue([]);
        fetchUnlocatedWorksites.mockResolvedValue(answer);
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();
        return wrapper;
    }

    it('El ciudadano encuentra una obra sin ubicación y le informa a la veeduría: the list also shows the works not on the map, each to where to inform about it', async () => {
        const wrapper = await openMap();
        expect(fetchUnlocatedWorksites).not.toHaveBeenCalled();

        fetchWorksiteList.mockResolvedValue([]);
        fetchUnlocatedWorksites.mockResolvedValue({ data: [MINCA, BONDA], has_more: false });
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();

        expect(fetchUnlocatedWorksites).toHaveBeenCalledWith({});
        expect(wrapper.get('[data-test="unlocated-section"] h2').text()).toBe('Obras sin ubicación todavía');
        const items = wrapper.findAll('[data-test="unlocated"]');
        expect(items.map((item) => item.text())).toEqual(['Construcción de la vía a Minca Santa Marta · Gobernación del Magdalena', 'Andén de Bonda Santa Marta']);
        // Sin ficha, la página del contrato; con su ficha sin ubicación, la de la obra.
        expect(items.map((item) => item.get('a').attributes('href'))).toEqual(['/contract/CO1.PCCNTR.7654321', '/worksite/01j9xq3m7v8k2d4f6g8h0jkmnp']);
    });

    it('asks the server for them by name once the person stops writing, by municipality, and 20 more at a time', async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        try {
            const wrapper = await openList({ data: [MINCA], has_more: true });

            fetchUnlocatedWorksites.mockResolvedValue({ data: [BONDA], has_more: false });
            await wrapper.get('[data-test="unlocated-section"] [data-test="more"]').trigger('click');
            await flushPromises();
            expect(fetchUnlocatedWorksites).toHaveBeenLastCalledWith({ page: 2 });
            expect(wrapper.findAll('[data-test="unlocated"]')).toHaveLength(2);
            expect(wrapper.find('[data-test="unlocated-section"] [data-test="more"]').exists()).toBe(false);

            // Dos letras no bastan para buscar.
            fetchUnlocatedWorksites.mockResolvedValue({ data: [], has_more: false });
            await wrapper.get('input[type="search"]').setValue('mi');
            vi.advanceTimersByTime(400);
            await flushPromises();
            expect(fetchUnlocatedWorksites).toHaveBeenCalledTimes(2);

            await wrapper.get('input[type="search"]').setValue('minca');
            vi.advanceTimersByTime(399);
            await flushPromises();
            expect(fetchUnlocatedWorksites).toHaveBeenCalledTimes(2);
            vi.advanceTimersByTime(1);
            await flushPromises();
            expect(fetchUnlocatedWorksites).toHaveBeenLastCalledWith({ q: 'minca' });
            expect(wrapper.get('[data-test="unlocated-empty"]').text()).toBe('Ninguna obra sin ubicación se llama así.');

            await wrapper.get('#filter-municipality').setValue('47001');
            await wrapper.findAll('button').find((button) => button.text() === 'Aplicar').trigger('click');
            await flushPromises();
            expect(fetchUnlocatedWorksites).toHaveBeenLastCalledWith({ q: 'minca', municipality: '47001' });
        } finally {
            vi.useRealTimers();
        }
    });

    it('says when every work of the territory is already on the map, and when they could not be loaded', async () => {
        const wrapper = await openList({ data: [], has_more: false });
        expect(wrapper.get('[data-test="unlocated-empty"]').text()).toBe('Todas las obras del territorio ya están en el mapa.');

        fetchUnlocatedWorksites.mockRejectedValue(new Error('Network Error'));
        await wrapper.findAll('button').find((button) => button.text() === 'Mapa').trigger('click');
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-test="unlocated-section"] [role="alert"]').text()).toBe('No se pudieron cargar las obras sin ubicación. Revise su conexión e intente de nuevo.');
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

describe('Las pantallas vacías, ilustradas (it. 40f)', () => {
    it('draws the magnifier over the map when no worksite matches, on the map and on the list', async () => {
        const wrapper = await openMap([]);
        expect(wrapper.get('[data-test="empty"] svg').attributes('data-illustration')).toBe('search');

        fetchWorksiteList.mockResolvedValue([{ id: 3, name: 'Pavimentación de la Calle 30', municipality: 'Santa Marta', color_pin: 'green' }]);
        await wrapper.findAll('button').find((button) => button.text() === 'Lista').trigger('click');
        await flushPromises();
        await wrapper.get('input[type="search"]').setValue('parque');

        expect(wrapper.get('[data-test="empty"]').text()).toBe('Ninguna obra se llama así. Pruebe con otra palabra.');
        expect(wrapper.get('[data-test="empty"] svg').attributes('data-illustration')).toBe('search');
    });
});
