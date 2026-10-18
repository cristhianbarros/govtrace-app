// Iteración 18 — US-015 (UI): los contratos del territorio, de 20 en 20,
// por fecha de firma o por valor. El objeto largo se corta en 50 caracteres
// y se lee completo al pasar sobre él.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Contracts from './Contracts.vue';
import { fetchContracts, fetchContractSync, syncContractsNow } from '@/services/api.js';

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

describe('Buscar un contrato (it. 40c)', () => {
    it('Buscar un contrato del territorio: asks for the words, from the first page, and says when nothing matches', async () => {
        const wrapper = await openContracts(pageOf([contract()]));
        fetchContracts.mockResolvedValue(pageOf([contract({ object: 'Construcción del parque Los Trupillos' })], { current_page: 1, last_page: 1, total: 1 }));

        await wrapper.get('input[type="search"]').setValue('trupillos');
        await wrapper.get('form[role="search"]').trigger('submit');
        await flushPromises();

        expect(fetchContracts).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'trupillos', page: 1 }));
        expect(wrapper.text()).toContain('Construcción del parque Los Trupillos');

        fetchContracts.mockResolvedValue(pageOf([], { current_page: 1, last_page: 1, total: 0 }));
        await wrapper.get('input[type="search"]').setValue('no existe');
        await wrapper.get('form[role="search"]').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Ningún contrato del territorio coincide con «no existe».');
    });
});

// It. 63 (US-070): sincronizar el territorio con el SECOP II sin esperar a la noche.
describe('Sincronizar el SECOP desde la veeduría (it. 63)', () => {
    afterEach(() => vi.useRealTimers());

    it('La veeduría pide la sincronización de su territorio sin esperar a la noche: says it is on its way, and why a contract may still be missing', async () => {
        fetchContractSync.mockResolvedValue(null);
        syncContractsNow.mockResolvedValue({ message: 'Buscamos los contratos de su territorio en el SECOP II. En unos minutos verá el resultado aquí.' });
        const wrapper = await openContracts(pageOf([contract()]));

        expect(wrapper.get('[data-test="sync"]').text()).toContain('Aún no se ha sincronizado.');
        expect(wrapper.text()).toContain('GovTrace lee los contratos de los datos abiertos del SECOP II en datos.gov.co. Uno que el SECOP aún no publica allí llega cuando lo publique.');
        await button(wrapper, 'Sincronizar ahora').trigger('click');
        await flushPromises();

        expect(syncContractsNow).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-test="sync"] [role="status"]').text()).toBe('Buscamos los contratos de su territorio en el SECOP II. En unos minutos verá el resultado aquí.');
    });

    it('Una sincronización cada 5 minutos por veeduría: says why it has to wait', async () => {
        fetchContractSync.mockResolvedValue(null);
        syncContractsNow.mockRejectedValue({ response: { status: 429, data: { message: 'Ya pidió una sincronización hace menos de 5 minutos. Espere su resultado.' } } });
        const wrapper = await openContracts(pageOf([contract()]));

        await button(wrapper, 'Sincronizar ahora').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="sync"] [role="alert"]').text()).toBe('Ya pidió una sincronización hace menos de 5 minutos. Espere su resultado.');
    });

    it('La pantalla dice cómo le fue a la última sincronización: when it ended and what it brought; while one runs, it looks again by itself', async () => {
        vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
        fetchContractSync.mockResolvedValue({ status: 'running', started_at: '2026-10-17T13:30:00+00:00', finished_at: null, inserted: 0, updated: 0 });
        const wrapper = await openContracts(pageOf([contract()]));
        expect(wrapper.get('[data-test="sync-last"]').text()).toBe('Sincronizando…');

        fetchContractSync.mockResolvedValue({ status: 'success', started_at: '2026-10-17T13:30:00+00:00', finished_at: '2026-10-17T13:41:00+00:00', inserted: 3, updated: 12 });
        vi.advanceTimersByTime(10_000);
        await flushPromises();

        expect(fetchContractSync).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-test="sync-last"]').text()).toMatch(/^Última sincronización: 17\/10\/2026, 08:41 .*· 3 contratos nuevos y 12 actualizados$/);
    });

    it('says when the last one failed, and that it tries again by itself', async () => {
        fetchContractSync.mockResolvedValue({ status: 'failed', started_at: '2026-10-17T13:30:00+00:00', finished_at: '2026-10-17T13:31:00+00:00', inserted: 0, updated: 0 });
        const wrapper = await openContracts(pageOf([contract()]));

        expect(wrapper.get('[data-test="sync-last"]').text()).toBe('La última sincronización falló. GovTrace la vuelve a intentar sola.');
    });
});
