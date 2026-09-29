// Iteración 32 — US-022 y US-047-MNT (UI): la pantalla "Sellado" del panel
// global. La cuenta patrocinadora (saldo, umbral, dirección) y la vigencia
// del contrato, leídas en vivo de Stellar; y las evidencias en "Falla de
// Sellado", para volver a encolar una o varias a la vez.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sealing from './Sealing.vue';
import { fetchSealing, fetchSealingCosts, requeueSeals } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const SPONSOR = 'GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF';
const LAST_ERROR = 'La red de Stellar no respondió (sendTransaction): cURL error 28: Operation timed out';

const failure = (reportId, overrides = {}) => ({
    organization_id: 'veeduria-smr',
    organization: 'Veeduría Ciudadana Santa Marta',
    report_id: reportId,
    failed_at: '2026-09-29T07:00:00-05:00',
    attempts: 5,
    last_error: LAST_ERROR,
    ...overrides,
});

const state = (overrides = {}) => ({
    sponsor: { address: SPONSOR, balance_xlm: '1234.5', threshold_xlm: '50', low: false },
    contract: {
        id: 'CALFTQY2X3YTEHFZORY7FQJUPXB2BXEGBCCHQVWTKB3WAVA65QHMTSFA',
        instance: { days: 150, expires_on: '2027-02-26' },
        code: { days: 20, expires_on: '2026-10-19' },
    },
    network_error: null,
    failures: [],
    ...overrides,
});

async function openSealing(data) {
    fetchSealing.mockResolvedValue(data);
    fetchSealingCosts.mockResolvedValue({ price: null, rows: [] });
    const wrapper = mount(Sealing);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text().startsWith(text));

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Sellado — la cuenta patrocinadora y el contrato (US-022)', () => {
    it('shows that it is loading, and says when it could not load, with a retry', async () => {
        fetchSealing.mockReturnValue(new Promise(() => {}));
        fetchSealingCosts.mockReturnValue(new Promise(() => {}));
        expect(mount(Sealing).text()).toContain('Consultando el estado del sellado…');

        fetchSealing.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(state());
        const wrapper = mount(Sealing);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');

        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('1.234,5 XLM');
    });

    it('shows the balance of the sponsor account, its threshold and the account to top up', async () => {
        const account = (await openSealing(state())).get('[data-test="sponsor"]');

        expect(account.text()).toContain('Saldo: 1.234,5 XLM');
        expect(account.text()).toContain('Umbral de alerta: 50 XLM');
        expect(account.text()).toContain(SPONSOR);
        expect(account.find('[data-test="low-balance"]').exists()).toBe(false);
    });

    it('warns when the balance is under the threshold', async () => {
        const wrapper = await openSealing(state({ sponsor: { address: SPONSOR, balance_xlm: '32.5', threshold_xlm: '50', low: true } }));

        expect(wrapper.get('[data-test="low-balance"]').text()).toBe(
            'Saldo bajo el umbral: recargue la cuenta desde la tesorería para no detener el sellado.',
        );
        expect(wrapper.get('[data-test="sponsor"]').text()).toContain('Saldo: 32,5 XLM');
    });

    it('shows how long the instance and the code of the contract live, and warns under 30 days', async () => {
        const wrapper = await openSealing(state());

        const instance = wrapper.get('[data-test="lifetime-instance"]');
        const code = wrapper.get('[data-test="lifetime-code"]');
        expect(instance.text()).toBe('Instancia: vence en 150 días (26/02/2027)');
        expect(code.text()).toBe('Código: vence en 20 días (19/10/2026)');
        expect(code.classes()).toContain('text-red-700');
        expect(instance.classes()).not.toContain('text-red-700');
    });

    it('says when Stellar did not answer, and still lists the failed seals', async () => {
        const wrapper = await openSealing(state({
            sponsor: null,
            contract: null,
            network_error: 'No se pudo consultar la red de Stellar. Intente de nuevo en unos minutos.',
            failures: [failure(7)],
        }));

        expect(wrapper.get('[data-test="network-error"]').text()).toBe('No se pudo consultar la red de Stellar. Intente de nuevo en unos minutos.');
        expect(wrapper.find('[data-test="sponsor"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="failure"]')).toHaveLength(1);
    });
});

describe('Sellado — las fallas de sellado (US-047-MNT)', () => {
    it('says when no seal is in "Falla de Sellado"', async () => {
        const wrapper = await openSealing(state());

        expect(wrapper.get('[data-test="failures"]').text()).toContain('No hay evidencias en "Falla de Sellado".');
    });

    it('lists each failure with its organization, report, attempts and last error', async () => {
        const row = (await openSealing(state({ failures: [failure(7)] }))).get('[data-test="failure"]');

        expect(row.text()).toContain('Veeduría Ciudadana Santa Marta');
        expect(row.text()).toContain('Reporte #7');
        expect(row.text()).toContain('5 intentos');
        expect(row.text()).toContain(LAST_ERROR);
    });

    it('Re-encolar varias evidencias a la vez: sends the 3 selected of 4, says so and reloads', async () => {
        const failures = [failure(7), failure(8), failure(9, { organization_id: 'veeduria-cienaga', organization: 'Veeduría Ciénaga' }), failure(10)];
        const wrapper = await openSealing(state({ failures }));
        requeueSeals.mockResolvedValue({ requeued: 3, message: '3 evidencias vuelven a la cola de sellado.' });
        fetchSealing.mockResolvedValue(state({ failures: [failure(10)] }));

        const boxes = wrapper.findAll('[data-test="failure"] input[type="checkbox"]');
        for (const box of boxes.slice(0, 3)) {
            await box.setValue(true);
        }
        await button(wrapper, 'Volver a encolar (3)').trigger('click');
        await flushPromises();

        expect(requeueSeals).toHaveBeenCalledWith([
            { organization_id: 'veeduria-smr', report_id: 7 },
            { organization_id: 'veeduria-smr', report_id: 8 },
            { organization_id: 'veeduria-cienaga', report_id: 9 },
        ]);
        expect(wrapper.get('[role="status"]').text()).toBe('3 evidencias vuelven a la cola de sellado.');
        expect(wrapper.findAll('[data-test="failure"]')).toHaveLength(1);
    });

    it('cannot requeue until one is selected, and selects all at once', async () => {
        const wrapper = await openSealing(state({ failures: [failure(7), failure(8)] }));

        expect(button(wrapper, 'Volver a encolar').attributes('disabled')).toBeDefined();

        await button(wrapper, 'Seleccionar todas').trigger('click');

        expect(button(wrapper, 'Volver a encolar (2)').attributes('disabled')).toBeUndefined();
    });

    it('shows why the server rejected the requeue, and keeps the selection', async () => {
        const wrapper = await openSealing(state({ failures: [failure(7)] }));
        requeueSeals.mockRejectedValue({ response: { status: 422, data: { message: 'El campo seals.0.organization_id no es válido.', errors: { 'seals.0.organization_id': ['El campo seals.0.organization_id no es válido.'] } } } });

        await wrapper.get('[data-test="failure"] input[type="checkbox"]').setValue(true);
        await button(wrapper, 'Volver a encolar (1)').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="requeue-error"]').text()).toContain('El campo seals.0.organization_id no es válido.');
        expect(button(wrapper, 'Volver a encolar (1)')).toBeDefined();
    });
});
