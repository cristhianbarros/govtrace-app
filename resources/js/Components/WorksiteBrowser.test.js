// Iteración 47a — "Encontrar la obra en campo" (US-016, US-019; V18, V19): lo
// que el veedor ve al abrir "Nuevo reporte". Las obras de su municipio, con
// filtros, de 20 en 20, y las cercanas encima. Las reglas (qué municipio, qué
// orden, qué filtra) son del servidor y se prueban con Pest; aquí, la pantalla.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WorksiteBrowser from './WorksiteBrowser.vue';
import { browseContracts } from '@/services/api.js';

vi.mock('@/services/api.js', () => ({ browseContracts: vi.fn(), fetchNearbyWorksites: vi.fn() }));

const SANTA_MARTA = { code: '47001', name: 'Santa Marta' };
const HERE = { latitude: 11.2408, longitude: -74.199, accuracy: 15 };

const work = (n, more = {}) => ({
    secop_contract_id: `CO1.PCCNTR.${n}`,
    object: `Obra ${n}`,
    name: null,
    entity_name: 'Distrito de Santa Marta',
    contractor_name: 'Constructora Caribe S.A.S.',
    process_number: `PROC-${n}`,
    status: 'En ejecución',
    municipality: 'Santa Marta',
    work_type: 'roads',
    work_type_label: 'Vías y puentes',
    situation: 'in_progress',
    end_date: '2026-10-30',
    located: true,
    ...more,
});

const answer = (more = {}) => ({
    municipality: SANTA_MARTA,
    notice: null,
    municipalities: [
        { code: '47189', name: 'Ciénaga', count: 3 },
        { code: '47001', name: 'Santa Marta', count: 2 },
    ],
    entities: [
        { name: 'Distrito de Santa Marta', count: 2 },
        { name: 'Gobernación del Magdalena', count: 1 },
    ],
    work_types: [
        { key: 'roads', label: 'Vías y puentes' },
        { key: 'water', label: 'Agua y saneamiento' },
    ],
    data: [work(1), work(2)],
    has_more: false,
    nearby: null,
    ...more,
});

const lastCall = () => browseContracts.mock.calls.at(-1)[0];

async function opened(location = HERE, first = answer()) {
    browseContracts.mockResolvedValueOnce(first);
    const wrapper = mount(WorksiteBrowser, { props: { location } });
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'], now: new Date('2026-10-10T12:00:00') });
    browseContracts.mockReset();
});
afterEach(() => vi.useRealTimers());

describe('WorksiteBrowser', () => {
    it('Al abrir Nuevo reporte veo las obras de mi municipio: asks with the location in the body and shows that municipality', async () => {
        const wrapper = await opened();

        expect(browseContracts).toHaveBeenCalledTimes(1);
        expect(lastCall()).toEqual({ latitude: 11.2408, longitude: -74.199, accuracy: 15, page: 1 });
        expect(wrapper.get('select#municipality').element.value).toBe('47001');
        expect(wrapper.findAll('[data-test="work"]').map((item) => item.find('[data-test="work-name"]').text())).toEqual(['Obra 1', 'Obra 2']);
    });

    it('waits for the GPS before asking: while the location is not known, it says so', async () => {
        const wrapper = mount(WorksiteBrowser, { props: { location: undefined } });
        await flushPromises();

        expect(browseContracts).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Buscando su ubicación…');
    });

    it('Puedo cambiar el municipio de la lista: asks again for that municipality, without the location', async () => {
        const wrapper = await opened();
        browseContracts.mockResolvedValueOnce(answer({ municipality: { code: '47189', name: 'Ciénaga' }, data: [work(9, { municipality: 'Ciénaga' })] }));

        await wrapper.get('select#municipality').setValue('47189');
        await flushPromises();

        expect(lastCall()).toEqual({ municipality: '47189', page: 1 });
        expect(wrapper.findAll('[data-test="work-name"]').map((name) => name.text())).toEqual(['Obra 9']);
    });

    it('Sin municipio por el GPS, la lista abre con el primero del territorio: says why, and the notice goes once he chooses', async () => {
        const NOTICE = 'No pudimos saber en qué municipio está. Le mostramos las obras de Santa Marta: elija el suyo.';
        const wrapper = await opened(null, answer({ notice: NOTICE }));

        expect(lastCall()).toEqual({ page: 1 });
        expect(wrapper.get('[data-test="notice"]').text()).toBe(NOTICE);

        browseContracts.mockResolvedValueOnce(answer({ municipality: { code: '47189', name: 'Ciénaga' } }));
        await wrapper.get('select#municipality').setValue('47189');
        await flushPromises();

        expect(wrapper.find('[data-test="notice"]').exists()).toBe(false);
    });

    it('Veo 20 obras y Ver 20 más trae las siguientes: appends the next page, and the button goes when there are no more', async () => {
        const first = Array.from({ length: 20 }, (_, i) => work(i + 1));
        const wrapper = await opened(HERE, answer({ data: first, has_more: true }));
        browseContracts.mockResolvedValueOnce(answer({ data: [work(21), work(22)], has_more: false }));

        await wrapper.get('button[data-test="more"]').trigger('click');
        await flushPromises();

        expect(lastCall()).toEqual({ municipality: '47001', page: 2 });
        expect(wrapper.findAll('[data-test="work"]')).toHaveLength(22);
        expect(wrapper.find('button[data-test="more"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Ver 20 más');
    });

    it('Filtro las obras por su situación, su tipo y la entidad: each filter asks again from the first page', async () => {
        const wrapper = await opened();

        browseContracts.mockResolvedValue(answer());
        await wrapper.get('select#situation').setValue('overdue');
        await flushPromises();
        expect(lastCall()).toEqual({ municipality: '47001', situation: 'overdue', page: 1 });

        await wrapper.get('select#work-type').setValue('water');
        await flushPromises();
        expect(lastCall()).toEqual({ municipality: '47001', situation: 'overdue', work_type: 'water', page: 1 });

        await wrapper.get('select#entity').setValue('Gobernación del Magdalena');
        await flushPromises();
        expect(lastCall()).toEqual({ municipality: '47001', situation: 'overdue', work_type: 'water', entity: 'Gobernación del Magdalena', page: 1 });
    });

    it('La búsqueda no distingue tildes ni mayúsculas: sends what was typed after a 300 ms pause, from 3 characters', async () => {
        const wrapper = await opened();
        browseContracts.mockResolvedValue(answer({ data: [work(5, { object: 'Construcción de la VÍA a Minca' })] }));

        await wrapper.get('input#work-search').setValue('vi');
        vi.advanceTimersByTime(400);
        await flushPromises();
        expect(browseContracts).toHaveBeenCalledTimes(1);

        await wrapper.get('input#work-search').setValue('via a minca');
        vi.advanceTimersByTime(300);
        await flushPromises();
        expect(lastCall()).toEqual({ municipality: '47001', q: 'via a minca', page: 1 });
        expect(wrapper.get('[data-test="work-name"]').text()).toBe('Construcción de la VÍA a Minca');
    });

    it('Sin resultados en mi municipio, puedo buscar en todo el territorio', async () => {
        const wrapper = await opened();
        browseContracts.mockResolvedValueOnce(answer({ data: [] }));
        await wrapper.get('input#work-search').setValue('Muelle del puerto');
        vi.advanceTimersByTime(300);
        await flushPromises();

        expect(wrapper.text()).toContain('No se encontraron obras en Santa Marta con esa búsqueda.');

        browseContracts.mockResolvedValueOnce(answer({ data: [work(7, { object: 'Muelle del puerto de Ciénaga', municipality: 'Ciénaga' })] }));
        await wrapper.get('button[data-test="whole-territory"]').trigger('click');
        await flushPromises();

        expect(lastCall()).toEqual({ municipality: '47001', q: 'Muelle del puerto', scope: 'territory', page: 1 });
        expect(wrapper.get('[data-test="work"]').text()).toContain('Muelle del puerto de Ciénaga');
        expect(wrapper.get('[data-test="work"]').text()).toContain('Ciénaga');
    });

    it('says the old message when nothing is found in the whole territory either', async () => {
        const wrapper = await opened();
        browseContracts.mockResolvedValue(answer({ data: [] }));
        await wrapper.get('input#work-search').setValue('Estadio Olímpico');
        vi.advanceTimersByTime(300);
        await flushPromises();
        await wrapper.get('button[data-test="whole-territory"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('No se encontraron obras activas. Verifique el número de proceso, nombre del contratista o palabras clave de la obra.');
    });

    it('Una obra sin ubicación aparece con Sin ubicación todavía, and each work says its situation and its kind', async () => {
        const wrapper = await opened(HERE, answer({ data: [work(1, { located: false, situation: 'overdue', end_date: '2026-08-31', work_type_label: 'Agua y saneamiento' }), work(2)] }));
        const [unlocated, located] = wrapper.findAll('[data-test="work"]');

        expect(unlocated.text()).toContain('Sin ubicación todavía');
        expect(unlocated.text()).toContain('Plazo vencido hace 40 días');
        expect(unlocated.text()).toContain('Agua y saneamiento');
        expect(unlocated.text()).toContain('PROC-1');
        expect(located.text()).not.toContain('Sin ubicación todavía');
        expect(located.text()).toContain('Vence en 20 días');
    });

    it('marks a work overdue for years like any overdue one, and says SECOP did not close it (it. 47c)', async () => {
        const wrapper = await opened(HERE, answer({ data: [work(1, { situation: 'long_overdue', end_date: '2018-08-10' })] }));
        const badge = wrapper.get('[data-test="work"] [data-test="situation"]');

        expect(badge.text()).toBe('Plazo vencido hace más de 8 años · SECOP no la ha cerrado');
        expect(badge.classes()).toContain('bg-red-50');
    });

    it('Las obras cercanas encabezan la lista, en Cerca de usted: with their distance, and choosing one picks its contract', async () => {
        const nearby = [
            { worksite_id: 'w1', name: 'Obra a 80 m', distance_meters: 80, contract: { secop_contract_id: 'CO1.PCCNTR.80', object: 'Obra a 80 m', entity_name: 'Distrito' } },
            { worksite_id: 'w2', name: 'Obra a 300 m', distance_meters: 300, contract: { secop_contract_id: 'CO1.PCCNTR.300', object: 'Obra a 300 m', entity_name: 'Distrito' } },
        ];
        const wrapper = await opened(HERE, answer({ nearby }));

        const section = wrapper.get('[data-test="nearby-section"]');
        expect(section.text()).toContain('Cerca de usted');
        expect(section.findAll('[data-test="distance"]').map((d) => d.text())).toEqual(['a 80 m', 'a 300 m']);
        expect(wrapper.html().indexOf('Cerca de usted')).toBeLessThan(wrapper.html().indexOf('data-test="work"'));

        await section.findAll('button')[1].trigger('click');
        expect(wrapper.emitted('select')[0][0]).toEqual(nearby[1].contract);
    });

    it('Ninguna obra cercana: with a good reading and none within 500 m, it says so above the list', async () => {
        const wrapper = await opened(HERE, answer({ nearby: [] }));

        expect(wrapper.get('[data-test="nearby-section"]').text()).toContain('📍 No se encontraron obras a menos de 500m. Utilice el buscador para encontrarla por nombre o contrato.');
    });

    it('does not look for nearby works with a reading of more than 50 m, and says how good the signal is', async () => {
        const wrapper = await opened({ ...HERE, accuracy: 120 }, answer({ nearby: null }));

        expect(wrapper.get('[data-test="nearby-section"]').text()).toContain('Buscando señal GPS: 120 m. Se necesitan 50 m o menos.');
    });

    it('chooses a work of the list for the report', async () => {
        const wrapper = await opened();

        await wrapper.findAll('[data-test="work"] button')[0].trigger('click');

        expect(wrapper.emitted('select')[0][0]).toMatchObject({ secop_contract_id: 'CO1.PCCNTR.1', object: 'Obra 1' });
    });

    it('says when the list could not be loaded, and lets him try again', async () => {
        browseContracts.mockRejectedValueOnce(new Error('sin red'));
        const wrapper = mount(WorksiteBrowser, { props: { location: HERE } });
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('No se pudieron cargar las obras. Revise su conexión y vuelva a intentarlo.');

        browseContracts.mockResolvedValueOnce(answer());
        await wrapper.get('button[data-test="retry"]').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('[data-test="work"]')).toHaveLength(2);
    });
});
