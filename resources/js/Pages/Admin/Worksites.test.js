// Iteración 18 — US-035 (UI): corregir la ubicación oficial de una obra,
// arrastrando el pin en el mapa o escribiendo la latitud y la longitud.
// El mapa (Leaflet, D8) se prueba aparte, en LocationMap.test.js.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import Worksites from './Worksites.vue';
import { correctWorksiteLocation, fetchWorksites, groupContracts } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
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
    fetchWorksites.mockResolvedValue(worksites);
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
        fetchWorksites.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([gaira]);
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
        fetchWorksites.mockResolvedValue([gaira]);
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
        fetchWorksites.mockResolvedValue([{ ...gaira, name: 'Acueducto Gaira' }]);

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
