// Iteración 16 — US-016 (UI): "Buscar Obra" busca en el servidor
// (GET /contracts/search) desde 3 caracteres y cuando pasan 300 ms sin que
// el veedor escriba más.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ContractSearch from './ContractSearch.vue';
import { searchContracts } from '@/services/api.js';

vi.mock('@/services/api.js', () => ({ searchContracts: vi.fn() }));

const pavimentacion = {
    secop_contract_id: 'CO1.PCCNTR.1234567',
    object: 'Pavimentación Calle 30',
    entity_name: 'Alcaldía Distrital de Santa Marta',
    contractor_name: 'Constructora del Caribe S.A.S.',
    process_number: 'SMR-LP-012-2026',
    status: 'En ejecución',
};

beforeEach(() => {
    vi.useFakeTimers();
    searchContracts.mockReset();
});

afterEach(() => vi.useRealTimers());

describe('ContractSearch', () => {
    it('Búsqueda y selección de una obra: searches when 300 ms pass without typing, and the chosen obra continues the report', async () => {
        searchContracts.mockResolvedValue([pavimentacion]);
        const wrapper = mount(ContractSearch);

        expect(wrapper.get('label[for="contract-search"]').text()).toBe('Buscar Obra');
        await wrapper.get('#contract-search').setValue('Pavi');
        await vi.advanceTimersByTimeAsync(299);
        expect(searchContracts).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(searchContracts).toHaveBeenCalledExactlyOnceWith('Pavi');

        const result = wrapper.get('[data-test="contract-result"]');
        expect(result.text()).toContain('Pavimentación Calle 30');
        await result.trigger('click');
        expect(wrapper.emitted('select')).toEqual([[pavimentacion]]);
    });

    it('searches only once while the veedor keeps typing, with the last text', async () => {
        searchContracts.mockResolvedValue([pavimentacion]);
        const wrapper = mount(ContractSearch);

        for (const text of ['Pav', 'Pavi', 'Pavim']) {
            await wrapper.get('#contract-search').setValue(text);
            await vi.advanceTimersByTimeAsync(200);
        }
        await vi.advanceTimersByTimeAsync(300);

        expect(searchContracts).toHaveBeenCalledExactlyOnceWith('Pavim');
    });

    it('La búsqueda exige al menos 3 caracteres: "Pa" does not search', async () => {
        const wrapper = mount(ContractSearch);

        await wrapper.get('#contract-search').setValue('Pa');
        await vi.advanceTimersByTimeAsync(1000);
        await wrapper.get('#contract-search').setValue('  Pa  ');
        await vi.advanceTimersByTimeAsync(1000);

        expect(searchContracts).not.toHaveBeenCalled();
    });

    it('Búsqueda sin resultados: tells the veedor what to check', async () => {
        searchContracts.mockResolvedValue([]);
        const wrapper = mount(ContractSearch);

        await wrapper.get('#contract-search').setValue('Estadio Olímpico');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();

        expect(wrapper.text()).toContain(
            'No se encontraron obras activas. Verifique el número de proceso, nombre del contratista o palabras clave de la obra.',
        );
    });
});
