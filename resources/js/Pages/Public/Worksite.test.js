// Iteración 26 — US-017 y US-029 (UI): la vista pública de una obra — la
// tarjeta de cada contrato, tal como viene de SECOP, y la línea de tiempo
// de sus evidencias publicadas, con el visor de fotos, las lápidas y el
// sello de cada una.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Worksite from './Worksite.vue';
import { formatDateTime } from '@/lib/format.js';
import { fetchReceipt, fetchWorksite } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const TOMBSTONE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';
const SEAL = { merkle_root: 'ab'.repeat(32), tx_hash: 'cd'.repeat(32), ledger: 61234567 };

const contract = {
    secop_contract_id: 'CO1.PCCNTR.1234567',
    object: 'Pavimentación Calle 30',
    entity_name: 'Alcaldía de Santa Marta',
    contractor_name: 'Constructora Caribe S.A.S.',
    value: '1250000000.00',
    term_months: 8,
    secop_url: 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
    cancelled: false,
    cancelled_notice: null,
};

const abandonment = {
    report_id: 12,
    classification: 'Abandono',
    captured_at: '2026-09-27T15:15:32+00:00',
    comment: 'Obra detenida hace 2 meses',
    approximate_location: { lat: 11.241, lng: -74.199 },
    files: [{ id: 40, kind: 'photo', sha256: 'ef'.repeat(32), photo_url: '/public/evidences/40/photo', download_url: '/public/evidences/40/download', proof_url: '/public/evidences/40/proof' }],
    seal: SEAL,
    receipt_url: '/public/reports/12/receipt',
};
const withdrawn = { report_id: 11, classification: 'Retraso', captured_at: '2026-09-20T13:00:00+00:00', notice: TOMBSTONE, seal: SEAL, receipt_url: '/public/reports/11/receipt' };
const progress = {
    ...abandonment,
    report_id: 10,
    classification: 'Avance',
    captured_at: '2026-09-10T14:00:00+00:00',
    comment: 'Ya fundieron la primera losa',
    files: [{ id: 30, kind: 'pdf', sha256: '12'.repeat(32), download_url: '/public/evidences/30/download', proof_url: '/public/evidences/30/proof' }],
    receipt_url: '/public/reports/10/receipt',
};

const calle30 = (changes = {}) => ({ id: 7, name: 'Pavimentación Calle 30', contracts: [contract], timeline: [abandonment, withdrawn, progress], ...changes });

async function openWorksite(worksite = calle30()) {
    fetchWorksite.mockResolvedValue(worksite);
    const wrapper = mount(Worksite, { props: { worksiteId: 7 }, attachTo: document.body });
    await flushPromises();
    return wrapper;
}

const cards = (wrapper) => wrapper.findAll('[data-test="evidence"]');
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', organizationLogo: null, organizationNotice: null };
});

describe('Vista de obra — el contrato (US-017)', () => {
    it('Tarjeta del contrato en la vista de la obra: entity, contractor, value, term and the SECOP original', async () => {
        const wrapper = await openWorksite();
        const card = wrapper.get('[data-test="contract"]');

        expect(card.text()).toContain('Alcaldía de Santa Marta');
        expect(card.text()).toContain('Constructora Caribe S.A.S.');
        expect(card.text()).toContain('$1.250.000.000');
        expect(card.text()).toContain('8 meses');
        const secop = card.get('a');
        expect(secop.text()).toBe('Ver original en SECOP');
        expect(secop.attributes()).toMatchObject({ href: contract.secop_url, target: '_blank', rel: 'noopener noreferrer' });
        wrapper.unmount();
    });

    it('Los datos se muestran tal como vienen de SECOP II', async () => {
        const wrapper = await openWorksite(calle30({ contracts: [{ ...contract, contractor_name: 'Constructora Caribe SAS.' }] }));

        expect(wrapper.get('[data-test="contract"]').text()).toContain('Constructora Caribe SAS.');
        wrapper.unmount();
    });

    it('Contrato anulado en SECOP que ya tenía evidencias: the page stays, with its evidences and the notice', async () => {
        const wrapper = await openWorksite(calle30({
            contracts: [{ ...contract, cancelled: true, cancelled_notice: '⚠️ Contrato Anulado/Retirado en SECOP' }],
            timeline: [abandonment, progress],
        }));

        expect(wrapper.get('[data-test="contract"]').text()).toContain('⚠️ Contrato Anulado/Retirado en SECOP');
        expect(cards(wrapper)).toHaveLength(2);
        wrapper.unmount();
    });

    it('shows every contract a worksite groups (US-045-INT)', async () => {
        const wrapper = await openWorksite(calle30({ name: 'Acueducto Gaira', contracts: [contract, { ...contract, secop_contract_id: 'CO1.PCCNTR.3333333', object: 'Acueducto Gaira, fase 2' }] }));

        expect(wrapper.find('h2').text()).toBe('Acueducto Gaira');
        expect(wrapper.findAll('[data-test="contract"]')).toHaveLength(2);
        wrapper.unmount();
    });
});

describe('Vista de obra — la línea de tiempo (US-029)', () => {
    it('Línea de tiempo cargada al hacer clic en el pin: each card with date, classification, comment, thumbnails and the seal button', async () => {
        const wrapper = await openWorksite();

        expect(fetchWorksite).toHaveBeenCalledWith(7);
        expect(cards(wrapper)).toHaveLength(3);
        const first = cards(wrapper)[0];
        expect(first.text()).toContain(formatDateTime(abandonment.captured_at));
        expect(first.text()).toContain('Abandono');
        expect(first.text()).toContain('Obra detenida hace 2 meses');
        expect(first.get('img').attributes()).toMatchObject({ src: '/public/evidences/40/photo', loading: 'lazy' });
        expect(first.findAll('button').some((candidate) => candidate.text() === 'Verificar Sello Blockchain')).toBe(true);
        wrapper.unmount();
    });

    it('Visor de fotos: touching a thumbnail opens the photo, and it closes', async () => {
        const wrapper = await openWorksite();

        await cards(wrapper)[0].get('[data-test="thumbnail"]').trigger('click');

        const viewer = wrapper.get('[role="dialog"]');
        expect(viewer.attributes('aria-modal')).toBe('true');
        expect(viewer.get('img').attributes('src')).toBe('/public/evidences/40/photo');
        await button(wrapper, 'Cerrar').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await cards(wrapper)[0].get('[data-test="thumbnail"]').trigger('click');
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('Las coordenadas de cada evidencia se muestran aproximadas', async () => {
        const wrapper = await openWorksite();

        expect(cards(wrapper)[0].text()).toContain('Ubicación aproximada (unos 100 m): 11.241, -74.199');
        wrapper.unmount();
    });

    it('Una evidencia retirada queda como lápida: without photos or comment, with its seal', async () => {
        // Aunque una respuesta los trajera por error: una retirada puede mostrar a un menor identificable.
        const leaky = { ...withdrawn, comment: 'Comentario que ya no debe verse', files: abandonment.files, approximate_location: { lat: 11.241, lng: -74.199 } };
        const wrapper = await openWorksite(calle30({ timeline: [abandonment, leaky, progress] }));
        const tombstone = cards(wrapper)[1];

        expect(tombstone.text()).toContain(TOMBSTONE);
        expect(tombstone.find('img').exists()).toBe(false);
        expect(tombstone.text()).not.toContain('Comentario que ya no debe verse');
        expect(tombstone.text()).not.toContain('Ubicación aproximada');
        expect(tombstone.findAll('button').some((candidate) => candidate.text() === 'Verificar Sello Blockchain')).toBe(true);
        wrapper.unmount();
    });

    it('offers the PDF of an evidence for download, as it was sealed', async () => {
        const wrapper = await openWorksite();
        const pdf = cards(wrapper)[2].get('a[data-test="download"]');

        expect(pdf.text()).toBe('Descargar PDF');
        expect(pdf.attributes('href')).toBe('/public/evidences/30/download');
        wrapper.unmount();
    });

    it('shows the seal of an evidence when "Verificar Sello Blockchain" is pressed, with the way to Stellar Expert', async () => {
        fetchReceipt.mockResolvedValue({
            sealed: true,
            ...SEAL,
            sealed_at: '2026-09-27T15:16:02+00:00',
            contract_id: 'CAF2JUMJPPMHLXMO3HV4SOT3PSHXSYMAPRPM67FCT4F5UU3NCGKAT3VE',
            explorer: { label: 'Ver en Stellar Expert', url: `https://stellar.expert/explorer/testnet/tx/${SEAL.tx_hash}` },
        });
        const wrapper = await openWorksite();

        await cards(wrapper)[0].findAll('button').find((candidate) => candidate.text() === 'Verificar Sello Blockchain').trigger('click');
        await flushPromises();

        const seal = cards(wrapper)[0].get('[data-test="seal"]');
        expect(fetchReceipt).toHaveBeenCalledWith('/public/reports/12/receipt');
        expect(seal.text()).toContain(SEAL.merkle_root);
        expect(seal.text()).toContain(SEAL.tx_hash);
        expect(seal.text()).toContain('61234567');
        expect(seal.text()).toContain(formatDateTime('2026-09-27T15:16:02+00:00'));
        expect(seal.get('a').attributes()).toMatchObject({ href: `https://stellar.expert/explorer/testnet/tx/${SEAL.tx_hash}`, target: '_blank', rel: 'noopener noreferrer' });
        expect(seal.get('a').text()).toBe('Ver en Stellar Expert');
        wrapper.unmount();
    });
});

describe('Vista de obra — estados', () => {
    it('shows that it is loading the worksite', () => {
        fetchWorksite.mockReturnValue(new Promise(() => {}));

        expect(mount(Worksite, { props: { worksiteId: 7 } }).text()).toContain('Cargando la obra…');
    });

    it('says when it could not load it, and lets retry', async () => {
        fetchWorksite.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(calle30());
        const wrapper = mount(Worksite, { props: { worksiteId: 7 } });
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(cards(wrapper)).toHaveLength(3);
    });

    it('says when the worksite has no published evidence yet', async () => {
        const wrapper = await openWorksite(calle30({ timeline: [] }));

        expect(wrapper.text()).toContain('Aún no hay evidencias publicadas de esta obra.');
        expect(wrapper.find('[data-test="contract"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('warns that the organization is suspended, and goes back to the map', async () => {
        page.props.organizationNotice = '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.';

        const wrapper = await openWorksite();

        expect(wrapper.get('[role="status"]').text()).toContain('suspendida temporalmente');
        expect(wrapper.get('a[href="/"]').text()).toBe('← Volver al mapa');
        wrapper.unmount();
    });
});
