// Iteración 32 — US-004 (UI): las comisiones de sellado por mes y por
// organización, en XLM y en pesos, con el precio de XLM que se usó y de
// cuándo es — el último conocido si el API de precios no respondió.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SealingCosts from './SealingCosts.vue';
import { fetchSealingCosts } from '@/services/api.js';
import { formatDateTime } from '@/lib/format.js';

vi.mock('@/services/api.js');

const september = {
    month: '2026-09',
    organization: 'Veeduría Ciudadana Santa Marta',
    sealed: 40,
    fee_xlm: '9.7',
    cost_cop: 11640,
    without_fee: 0,
};

async function openCosts(report) {
    fetchSealingCosts.mockResolvedValue(report);
    const wrapper = mount(SealingCosts);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Costos de sellado (US-004)', () => {
    it('shows that it is loading, and says when it could not load', async () => {
        fetchSealingCosts.mockReturnValue(new Promise(() => {}));
        expect(mount(SealingCosts).text()).toContain('Calculando las comisiones…');

        fetchSealingCosts.mockRejectedValue(new Error('Network Error'));
        const wrapper = mount(SealingCosts);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
    });

    it('Reporte mensual por organización con costo en pesos: the row of September 2026', async () => {
        const wrapper = await openCosts({ price: { cop_per_xlm: 1200, quoted_at: '2026-09-29T09:13:20-05:00', live: true }, rows: [september] });

        const row = wrapper.get('[data-test="cost-row"]');
        expect(row.text()).toContain('septiembre de 2026');
        expect(row.text()).toContain('Veeduría Ciudadana Santa Marta');
        expect(row.get('[data-test="sealed"]').text()).toBe('40');
        expect(row.get('[data-test="fee"]').text()).toBe('9,7 XLM');
        expect(row.get('[data-test="cost"]').text()).toBe('$11.640');
        expect(wrapper.get('[data-test="price"]').text()).toBe(`1 XLM = $1.200 · precio de ${formatDateTime('2026-09-29T09:13:20-05:00')}`);
    });

    it('El API de precios no responde: says the cost uses the last known price, and from when', async () => {
        const wrapper = await openCosts({
            price: { cop_per_xlm: 1150, quoted_at: '2026-09-26T10:00:00-05:00', live: false },
            rows: [{ ...september, cost_cop: 11155 }],
        });

        expect(wrapper.get('[data-test="price"]').text()).toBe(
            `El API de precios no respondió: se usa el último precio conocido, 1 XLM = $1.150, del ${formatDateTime('2026-09-26T10:00:00-05:00')}.`,
        );
        expect(wrapper.get('[data-test="cost"]').text()).toBe('$11.155');
    });

    it('shows the fees in XLM without a cost in pesos when no price was ever known', async () => {
        const wrapper = await openCosts({ price: null, rows: [{ ...september, cost_cop: null }] });

        expect(wrapper.get('[data-test="price"]').text()).toBe('Sin precio de XLM: el costo en pesos no se puede estimar todavía.');
        expect(wrapper.get('[data-test="cost"]').text()).toBe('—');
        expect(wrapper.get('[data-test="fee"]').text()).toBe('9,7 XLM');
    });

    it('says how many seals of the month have no known fee', async () => {
        const wrapper = await openCosts({ price: null, rows: [{ ...september, without_fee: 1 }] });

        expect(wrapper.get('[data-test="cost-row"]').text()).toContain('1 sin comisión conocida');
    });

    it('says when nothing was sealed yet', async () => {
        const wrapper = await openCosts({ price: null, rows: [] });

        expect(wrapper.text()).toContain('Aún no hay sellos en la red de Stellar.');
    });
});
