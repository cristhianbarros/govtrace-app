// Iteración 18 — US-035 (UI): corregir la ubicación oficial de una obra,
// arrastrando el pin en el mapa o escribiendo la latitud y la longitud.
// El mapa (Leaflet, D8) se prueba aparte, en LocationMap.test.js.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Worksites from './Worksites.vue';
import { correctWorksiteLocation, fetchWorksites, groupContracts, searchPlaces } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

/** What the server answers (it. 52): the page, how many there are and whether there are more. */
const answer = (rows, extra = {}) => ({ data: rows, total: rows.length, has_more: false, ...extra });

// El pin: un botón que "lo arrastra" hasta el sitio real de la obra.
vi.mock('@/Components/LocationMap.vue', () => ({
    default: defineComponent({
        props: { modelValue: { type: Object, default: null } },
        emits: ['update:modelValue'],
        setup: (_props, { emit }) => () =>
            h('button', { type: 'button', 'data-test': 'drag-pin', onClick: () => emit('update:modelValue', { latitude: 11.2408, longitude: -74.199 }) }, 'pin'),
    }),
}));

const ADJUSTED = 'La ubicación oficial de la obra ha sido ajustada. La nueva geocerca de 500m ya está activa para los veedores.';

const gaira = {
    id: 3,
    latitude: 11.2,
    longitude: -74.23,
    contracts: [
        { secop_contract_id: 'CO1.PCCNTR.1111111', object: 'Acueducto Gaira' },
        { secop_contract_id: 'CO1.PCCNTR.3333333', object: 'Acueducto Gaira, segunda etapa' },
    ],
};

async function openWorksites(worksites = [gaira]) {
    fetchWorksites.mockResolvedValue(answer(worksites));
    const wrapper = mount(Worksites);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function correct(wrapper) {
    await button(wrapper, 'Corregir ubicación').trigger('click');
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Obras', () => {
    it('shows that it is loading the worksites', () => {
        fetchWorksites.mockReturnValue(new Promise(() => {}));

        expect(mount(Worksites).text()).toContain('Cargando obras…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchWorksites.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(answer([gaira]));
        const wrapper = mount(Worksites);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Acueducto Gaira');
    });

    it('says when the organization has no worksites yet', async () => {
        const wrapper = await openWorksites([]);

        expect(wrapper.text()).toContain('Aún no hay obras. Una obra aparece aquí con el primer reporte de uno de sus contratos.');
    });

    it('lists each worksite with its contracts and its official location', async () => {
        const wrapper = await openWorksites([gaira, { id: 4, latitude: null, longitude: null, contracts: [{ secop_contract_id: 'CO1.PCCNTR.2222222', object: 'Parque Bastidas' }] }]);

        const [first, second] = wrapper.findAll('article').map((card) => card.text());
        expect(first).toContain('Acueducto Gaira, segunda etapa');
        expect(first).toContain('11.2000000, -74.2300000');
        expect(second).toContain('Sin ubicación oficial');
    });

    it.each([
        [
            'arrastrando el pin',
            async (wrapper) => {
                await wrapper.get('[data-test="drag-pin"]').trigger('click');
            },
        ],
        [
            'escribiendo latitud y longitud',
            async (wrapper) => {
                await wrapper.get('input#latitude').setValue('11.2408');
                await wrapper.get('input#longitude').setValue('-74.199');
            },
        ],
    ])('Corrección de la ubicación %s', async (_way, moveThePin) => {
        correctWorksiteLocation.mockResolvedValue({ message: ADJUSTED });
        fetchWorksites.mockResolvedValue(answer([gaira]));
        const wrapper = await openWorksites();

        await correct(wrapper);
        expect(wrapper.get('input#latitude').element.value).toBe('11.2');
        await moveThePin(wrapper);
        expect(wrapper.get('input#longitude').element.value).toBe('-74.199');
        await button(wrapper, 'Guardar ubicación').trigger('click');
        await flushPromises();

        expect(correctWorksiteLocation).toHaveBeenCalledWith(3, { latitude: 11.2408, longitude: -74.199 });
        expect(wrapper.get('[role="status"]').text()).toBe(ADJUSTED);
    });

    it('shows why the server refused the coordinates', async () => {
        const message = 'Las coordenadas no son válidas: la latitud va de -90 a 90 y la longitud de -180 a 180.';
        correctWorksiteLocation.mockRejectedValue({ response: { status: 422, data: { message, errors: { location: [message] } } } });
        const wrapper = await openWorksites();

        await correct(wrapper);
        await wrapper.get('input#latitude').setValue('95');
        await button(wrapper, 'Guardar ubicación').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe(message);
    });

    // It. 68: el mismo buscador de lugares del ciudadano.
    it('Busco la dirección para corregir la ubicación: the chosen place moves the pin and fills the latitude and the longitude, without saving', async () => {
        searchPlaces.mockResolvedValue({ data: [{ label: 'Gaira, Santa Marta, Magdalena', latitude: 11.1841, longitude: -74.2207 }] });
        const wrapper = await openWorksites();
        await correct(wrapper);

        await wrapper.get('input#place-search').setValue('Gaira');
        await button(wrapper, 'Buscar').trigger('click');
        await flushPromises();
        expect(searchPlaces).toHaveBeenCalledWith({ q: 'Gaira', worksite_id: 3 });
        await wrapper.get('[data-test="place"]').trigger('click');

        expect(wrapper.get('input#latitude').element.value).toBe('11.1841');
        expect(wrapper.get('input#longitude').element.value).toBe('-74.2207');
        expect(correctWorksiteLocation).not.toHaveBeenCalled();
    });

    it('does not save without a latitude and a longitude', async () => {
        const wrapper = await openWorksites();

        await correct(wrapper);
        await wrapper.get('input#longitude').setValue('');

        expect(button(wrapper, 'Guardar ubicación').attributes('disabled')).toBeDefined();
    });
});

describe('Agrupar contratos en una ficha (US-045-INT)', () => {
    async function group(wrapper, name, ids) {
        await wrapper.get('input#group-name').setValue(name);
        await wrapper.get('textarea#group-contracts').setValue(ids.join('\n'));
        await button(wrapper, 'Agrupar').trigger('click');
        await flushPromises();
    }

    it('Agrupación de dos contratos: groups them under the name, and shows the worksite with it', async () => {
        groupContracts.mockResolvedValue({ data: { id: 3, name: 'Acueducto Gaira', secop_contract_ids: ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'] } });
        const wrapper = await openWorksites();
        fetchWorksites.mockResolvedValue(answer([{ ...gaira, name: 'Acueducto Gaira' }]));

        expect(button(wrapper, 'Agrupar').attributes('disabled')).toBeDefined();
        await group(wrapper, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', ' CO1.PCCNTR.3333333 ', '']);

        expect(groupContracts).toHaveBeenCalledWith('Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333']);
        expect(wrapper.get('[role="status"]').text()).toBe('Contratos agrupados en la ficha «Acueducto Gaira».');
        expect(wrapper.text()).toContain('Acueducto Gaira');
    });

    it('Solo se agrupan contratos del territorio de la organización: shows why it was refused', async () => {
        groupContracts.mockRejectedValue({ response: { status: 422, data: { errors: { secop_contract_ids: ['El contrato CO1.PCCNTR.5555555 no es del territorio de la organización.'] } } } });
        const wrapper = await openWorksites();

        await group(wrapper, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.5555555']);

        expect(wrapper.get('[role="alert"]').text()).toBe('El contrato CO1.PCCNTR.5555555 no es del territorio de la organización.');
    });

    it('adds the contracts of a worksite to the grouping with a touch', async () => {
        const wrapper = await openWorksites();

        await wrapper.findAll('button').filter((candidate) => candidate.text() === 'Unir con otra obra')[1].trigger('click');

        expect(wrapper.get('textarea#group-contracts').element.value).toBe('CO1.PCCNTR.3333333');
    });
});

// It. 44b — US-056-LEG: el expediente de una obra, para el derecho de petición y la denuncia.
describe('El expediente de una obra (it. 44b)', () => {
    it('Descargar el expediente de una obra: each worksite has its own download, and says what it brings', async () => {
        const wrapper = await openWorksites([gaira, { ...gaira, id: 8 }]);
        const downloads = wrapper.findAll('a[data-test="dossier"]');

        expect(downloads.map((link) => link.attributes('href'))).toEqual([`/worksites/${gaira.id}/dossier.zip`, '/worksites/8/dossier.zip']);
        expect(downloads[0].text()).toBe('Descargar expediente');
        expect(wrapper.text()).toContain('Un ZIP con las evidencias publicadas, sus pruebas y las plantillas del derecho de petición y de la denuncia ante la Contraloría.');
    });

    // It. 45f: "Corregir ubicación" desde la Bandeja abre la corrección de esa obra.
    // It. 46c (US-064-SEC): la obra se nombra por su identificador público.
    it('opens the correction of the worksite named in the link from the Bandeja', async () => {
        const named = { ...gaira, id: '01j9xq3m7v8k2d4f6g8h0jkmnp' };
        const other = { ...gaira, id: '01j9xq3m7v8k2d4f6g8h0jkmnq', latitude: 11.3, longitude: -74.1 };
        window.history.replaceState({}, '', `/admin/worksites?corregir=${named.id}`);

        const wrapper = await openWorksites([other, named]);

        expect(wrapper.get('input#latitude').element.value).toBe('11.2');
        expect(wrapper.get('input#longitude').element.value).toBe('-74.23');
        window.history.replaceState({}, '', '/admin/worksites');
    });

    it('opens no correction when the link names no worksite of the organization', async () => {
        window.history.replaceState({}, '', '/admin/worksites?corregir=99');

        const wrapper = await openWorksites();

        expect(wrapper.find('input#latitude').exists()).toBe(false);
        window.history.replaceState({}, '', '/admin/worksites');
    });
});

// It. 52 — de a una página, con búsqueda, y la obra del enlace de la Bandeja pedida por su id.
describe('Las obras de a una página, con búsqueda (it. 52)', () => {
    const field = (wrapper) => wrapper.get('input#search-worksites');

    it('Las obras de la veeduría llegan de a 20 y Ver 20 más trae las siguientes: appends the next page, says how many there are', async () => {
        fetchWorksites.mockResolvedValue(answer([gaira], { total: 21, has_more: true }));
        const wrapper = mount(Worksites);
        await flushPromises();
        fetchWorksites.mockResolvedValue(answer([{ ...gaira, id: 'otra' }], { total: 21 }));

        await wrapper.get('[data-test="more"]').trigger('click');
        await flushPromises();

        expect(fetchWorksites).toHaveBeenLastCalledWith({ page: 2 });
        expect(wrapper.findAll('article')).toHaveLength(2);
        expect(wrapper.find('[data-test="more"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="total"]').text()).toBe('21 obras');
    });

    it('Busco una obra por su nombre o por el objeto de sus contratos: after a pause, and says when nothing matches', async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        const wrapper = await openWorksites([gaira]);
        fetchWorksites.mockResolvedValue(answer([]));

        await field(wrapper).setValue('pavimentación');
        vi.advanceTimersByTime(300);
        await flushPromises();

        expect(fetchWorksites).toHaveBeenLastCalledWith({ q: 'pavimentación', page: 1 });
        expect(wrapper.text()).toContain('Ninguna obra con esa búsqueda.');
        vi.useRealTimers();
    });

    it('Corregir ubicación desde la Bandeja abre la obra aunque no esté en la primera página: asks the server for it, and offers all of them back', async () => {
        const named = { ...gaira, id: '01j9xq3m7v8k2d4f6g8h0jkmnp' };
        window.history.replaceState({}, '', `/admin/worksites?corregir=${named.id}`);

        const wrapper = await openWorksites([named]);

        expect(fetchWorksites).toHaveBeenCalledWith({ worksite: named.id, page: 1 });
        expect(wrapper.text()).toContain('Mostrando la obra que va a corregir.');
        expect(wrapper.find('input#search-worksites').exists()).toBe(false);

        fetchWorksites.mockResolvedValue(answer([named, { ...gaira, id: 'otra' }]));
        await wrapper.get('[data-test="show-all"]').trigger('click');
        await flushPromises();

        expect(fetchWorksites).toHaveBeenLastCalledWith({ page: 1 });
        expect(wrapper.findAll('article')).toHaveLength(2);
        window.history.replaceState({}, '', '/admin/worksites');
    });
});

// It. 69 — US-071 (R-PRIV-07).
describe('Cuántas personas ven cada obra (it. 69)', () => {
    it('La veeduría ve cuántas personas vieron cada obra: today, the last 30 days and all of them, under its official location', async () => {
        const wrapper = await openWorksites([{ ...gaira, visitors: { today: 3, last_30_days: 12, total: 1400 } }]);

        expect(wrapper.get('[data-test="visitors"]').text()).toBe('Personas que vieron su página: 3 hoy · 12 en los últimos 30 días · 1.400 en total');
    });

    it('says when nobody has seen the page of a work yet', async () => {
        const wrapper = await openWorksites([{ ...gaira, visitors: { today: 0, last_30_days: 0, total: 0 } }]);

        expect(wrapper.get('[data-test="visitors"]').text()).toBe('Nadie ha visto su página todavía.');
    });
});
