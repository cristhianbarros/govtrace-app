// Iteración 18 — US-015 (UI): los contratos del territorio, de 20 en 20,
// por fecha de firma o por valor. El objeto largo se corta en 50 caracteres
// y se lee completo al pasar sobre él.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Contracts from './Contracts.vue';
import { fetchContracts } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const EMPTY =
    'Aún no hay contratos de obra sincronizados para su territorio. La actualización desde SECOP II se ejecuta automáticamente cada madrugada.';

const contract = (overrides = {}) => ({
    secop_contract_id: 'CO1.PCCNTR.1234567',
    process_number: 'SMR-LP-012-2026',
    object: 'Pavimentación Calle 30',
    contractor_name: 'Constructora del Caribe S.A.S.',
    value: 1500000000,
    status: 'En ejecución',
    signed_at: '2026-01-15',
    ...overrides,
});

const pageOf = (data, meta = { current_page: 1, last_page: 3, total: 45 }) => ({ data, meta });

async function openContracts(page) {
    fetchContracts.mockResolvedValue(page);
    const wrapper = mount(Contracts);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text().startsWith(text));

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Contratos', () => {
    it('shows that it is loading the contracts', () => {
        fetchContracts.mockReturnValue(new Promise(() => {}));

        expect(mount(Contracts).text()).toContain('Cargando contratos…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchContracts.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(pageOf([contract()]));
        const wrapper = mount(Contracts);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('SMR-LP-012-2026');
    });

    it('Territorio aún sin contratos: says when the sync will bring them', async () => {
        const wrapper = await openContracts(pageOf([], { current_page: 1, last_page: 1, total: 0 }));

        expect(wrapper.text()).toContain(EMPTY);
    });

    it('Tabla paginada ordenada por fecha de firma: the first 20, newest first, with the 6 fields', async () => {
        const wrapper = await openContracts(pageOf([contract()]));

        expect(fetchContracts).toHaveBeenCalledWith({ sort: 'signed_at', direction: 'desc', page: 1 });
        const row = wrapper.get('[data-test="contract-row"]').text();
        for (const field of ['SMR-LP-012-2026', 'Pavimentación Calle 30', 'Constructora del Caribe S.A.S.', 'En ejecución', '15/01/2026']) {
            expect(row).toContain(field);
        }
        // El mismo formato de pesos que pide US-017: "$1.250.000.000".
        expect(row).toContain('$1.500.000.000');
        expect(wrapper.text()).toContain('Página 1 de 3 · 45 contratos');
    });

    it('Invertir el orden por valor: sorting by value twice reverses the order', async () => {
        const wrapper = await openContracts(pageOf([contract()]));

        await button(wrapper, 'Valor').trigger('click');
        await flushPromises();
        expect(fetchContracts).toHaveBeenLastCalledWith({ sort: 'value', direction: 'desc', page: 1 });

        await button(wrapper, 'Valor').trigger('click');
        await flushPromises();
        expect(fetchContracts).toHaveBeenLastCalledWith({ sort: 'value', direction: 'asc', page: 1 });
    });

    it('El objeto largo se trunca con tooltip: shows its first 50 characters, and all of it on hover', async () => {
        const object = 'Construcción de la segunda etapa del acueducto regional de Gaira, con obras complementarias de redes de distribución';
        const wrapper = await openContracts(pageOf([contract({ object })]));

        const cell = wrapper.get('[data-test="contract-object"]');
        expect(cell.text()).toBe(`${object.slice(0, 50)}…`);
        expect(cell.attributes('title')).toBe(object);
    });

    it('moves between pages', async () => {
        const wrapper = await openContracts(pageOf([contract()]));

        expect(button(wrapper, 'Anterior').attributes('disabled')).toBeDefined();
        await button(wrapper, 'Siguiente').trigger('click');
        await flushPromises();

        expect(fetchContracts).toHaveBeenLastCalledWith({ sort: 'signed_at', direction: 'desc', page: 2 });
    });
});

describe('Estado de un contrato anulado (it. 40b)', () => {
    it('names an annulled contract as the public card does, not with the internal word "cancelled"', async () => {
        const wrapper = await openContracts(pageOf([contract({ status: 'cancelled' })]));

        expect(wrapper.text()).toContain('Anulado/Retirado en SECOP');
        expect(wrapper.text()).not.toContain('cancelled');
    });
});
