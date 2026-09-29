// Iteración 28 — US-010 y US-023 (UI): "Mis Reportes" en la app del veedor —
// cada reporte con su estado técnico y su estado editorial por separado —, y
// el Recibo de Inmutabilidad de cada uno.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MyReports from './MyReports.vue';
import { formatDateTime } from '@/lib/format.js';
import { fetchMyReports, fetchReceipt } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const PENDING = '⏳ Su evidencia está en proceso de sellado en la red Stellar. Este proceso toma unos minutos. El recibo criptográfico aparecerá aquí en breve.';

const published = {
    id: 12,
    captured_at: '2026-09-27T15:15:32+00:00',
    classification: 'Abandono',
    worksite: 'Pavimentación Calle 30',
    technical_status: 'Sellado',
    editorial_status: 'Publicado',
    rejection_reason: null,
    receipt_url: '/reports/12/receipt',
};
const rejected = { ...published, id: 11, technical_status: 'Sellado', editorial_status: 'Rechazada', rejection_reason: 'La foto no corresponde a la obra', receipt_url: '/reports/11/receipt' };
const queued = { ...published, id: 10, technical_status: 'En Cola', editorial_status: 'En Revisión', receipt_url: '/reports/10/receipt' };

async function openMyReports(reports = [published, rejected, queued]) {
    fetchMyReports.mockResolvedValue(reports);
    const wrapper = mount(MyReports);
    await flushPromises();
    return wrapper;
}

const items = (wrapper) => wrapper.findAll('[data-test="report"]');

async function openReceipt(item) {
    await item.findAll('button').find((button) => button.text() === 'Ver recibo').trigger('click');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', organizationLogo: null, organizationNotice: null };
});

describe('Mis Reportes (US-010)', () => {
    it('Estado técnico y estado editorial por separado', async () => {
        const wrapper = await openMyReports();
        const first = items(wrapper)[0];

        expect(first.get('[data-test="technical"]').text()).toBe('Sellado');
        expect(first.get('[data-test="editorial"]').text()).toBe('Publicado');
        expect(first.text()).toContain('Estado técnico');
        expect(first.text()).toContain('Estado editorial');
        expect(first.text()).toContain(formatDateTime(published.captured_at));
        expect(first.text()).toContain('Pavimentación Calle 30');
        expect(first.text()).toContain('Abandono');
    });

    it('Evidencia rechazada con motivo', async () => {
        const wrapper = await openMyReports();

        expect(items(wrapper)[1].get('[data-test="editorial"]').text()).toBe('Rechazada');
        expect(items(wrapper)[1].text()).toContain('Motivo: La foto no corresponde a la obra');
        expect(items(wrapper)[0].text()).not.toContain('Motivo:');
    });

    it('Evidencia aún no revisada, still in the queue', async () => {
        const wrapper = await openMyReports();

        expect(items(wrapper)[2].get('[data-test="technical"]').text()).toBe('En Cola');
        expect(items(wrapper)[2].get('[data-test="editorial"]').text()).toBe('En Revisión');
    });

    it('shows that it is loading, says when it could not, and lets retry', async () => {
        fetchMyReports.mockReturnValue(new Promise(() => {}));
        expect(mount(MyReports).text()).toContain('Cargando sus reportes…');

        fetchMyReports.mockReset();
        fetchMyReports.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([published]);
        const wrapper = mount(MyReports);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();
        expect(items(wrapper)).toHaveLength(1);
    });

    it('says when the veedor has sent no report yet', async () => {
        const wrapper = await openMyReports([]);

        expect(wrapper.text()).toContain('Aún no ha enviado reportes. Los que envíe aparecerán aquí, con su estado.');
    });

    it('moves between "Nuevo Reporte" and "Mis Reportes"', async () => {
        const wrapper = await openMyReports();

        expect(wrapper.get('nav a[href="/reports/new"]').text()).toBe('Nuevo Reporte');
        expect(wrapper.get('nav a[href="/my-reports"]').attributes('aria-current')).toBe('page');
    });
});

describe('El Recibo de Inmutabilidad en la app (US-023)', () => {
    it('Recibo de una evidencia sellada: root, transaction, ledger, exact time and "Ver en Stellar Expert"', async () => {
        fetchReceipt.mockResolvedValue({
            sealed: true,
            merkle_root: '9f2c'.repeat(16),
            tx_hash: 'def0'.repeat(16),
            ledger: 61234567,
            sealed_at: '2026-09-27T15:15:32+00:00',
            contract_id: 'CABXHM74HFSAZD4FDFDONSIDJOVJBU7ZJXYBCHY3JJDCQUDFTHBT2WUI',
            explorer: { label: 'Ver en Stellar Expert', url: `https://stellar.expert/explorer/testnet/tx/${'def0'.repeat(16)}` },
        });
        const wrapper = await openMyReports();

        await openReceipt(items(wrapper)[0]);

        const receipt = items(wrapper)[0].get('[data-test="receipt"]');
        expect(fetchReceipt).toHaveBeenCalledWith('/reports/12/receipt');
        expect(receipt.text()).toContain('9f2c'.repeat(16));
        expect(receipt.text()).toContain('def0'.repeat(16));
        expect(receipt.text()).toContain('61234567');
        expect(receipt.text()).toContain(formatDateTime('2026-09-27T15:15:32+00:00'));
        expect(receipt.get('a').text()).toBe('Ver en Stellar Expert');
        expect(receipt.get('a').attributes()).toMatchObject({ target: '_blank', rel: 'noopener noreferrer' });
    });

    it('La evidencia aún no está sellada: the message', async () => {
        fetchReceipt.mockResolvedValue({ sealed: false, message: PENDING });
        const wrapper = await openMyReports();

        await openReceipt(items(wrapper)[2]);

        expect(items(wrapper)[2].get('[data-test="receipt"]').text()).toBe(PENDING);
    });

    it('Evidencia reenviada tras una transacción que no se incluyó: only the transaction the receipt gives', async () => {
        fetchReceipt.mockResolvedValue({ sealed: true, merkle_root: 'ab'.repeat(32), tx_hash: 'de'.repeat(32), ledger: 61234570, sealed_at: '2026-09-27T15:21:00+00:00', contract_id: 'C', explorer: null });
        const wrapper = await openMyReports();

        await openReceipt(items(wrapper)[0]);

        const receipt = items(wrapper)[0].get('[data-test="receipt"]');
        expect(receipt.text()).toContain('de'.repeat(32));
        expect(receipt.find('a').exists()).toBe(false); // la red local no tiene explorador
    });
});
