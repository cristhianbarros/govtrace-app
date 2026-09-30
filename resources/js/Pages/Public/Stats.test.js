// Iteración 34 — US-051-RPT y US-052-RPT (UI): las estadísticas públicas del
// territorio — obras en riesgo, evidencias publicadas por mes y contratos
// anulados con evidencias — y la descarga de los datos abiertos.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Stats from './Stats.vue';
import { fetchPublicStats } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const stats = {
    worksites_at_risk: 3,
    published_by_month: [
        { month: '2026-07', total: 1 },
        { month: '2026-08', total: 1 },
        { month: '2026-09', total: 10 },
    ],
    cancelled_contracts_with_evidence: 2,
};

async function openStats(answer = stats) {
    fetchPublicStats.mockResolvedValue(answer);
    const wrapper = mount(Stats);
    await flushPromises();
    return wrapper;
}

beforeEach(() => vi.resetAllMocks());

describe('Estadísticas del territorio', () => {
    it('Estadísticas del mapa público: the worksites at risk, the evidences by month and the annulled contracts', async () => {
        const wrapper = await openStats();

        expect(wrapper.get('[data-test="at-risk"]').text()).toContain('3');
        expect(wrapper.get('[data-test="at-risk"]').text()).toContain('Obras en riesgo');
        expect(wrapper.get('[data-test="cancelled"]').text()).toContain('2');
        expect(wrapper.get('[data-test="cancelled"]').text()).toContain('Contratos anulados con evidencias');
        expect(wrapper.findAll('[data-test="month"]').map((row) => row.findAll('span').map((cell) => cell.text()))).toEqual([
            ['julio de 2026', '1'],
            ['agosto de 2026', '1'],
            ['septiembre de 2026', '10'],
        ]);
    });

    it('says when nothing was published yet', async () => {
        const wrapper = await openStats({ worksites_at_risk: 0, published_by_month: [], cancelled_contracts_with_evidence: 0 });

        expect(wrapper.text()).toContain('Aún no hay evidencias publicadas en este territorio.');
    });

    it('shows that it is loading, says when it could not, and lets retry', async () => {
        fetchPublicStats.mockReturnValue(new Promise(() => {}));
        expect(mount(Stats).text()).toContain('Cargando las estadísticas…');

        fetchPublicStats.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(stats);
        const wrapper = mount(Stats);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-test="at-risk"]').text()).toContain('3');
    });

    it('Descarga de datos abiertos: offers the published evidences and their seals in CSV and JSON', async () => {
        const wrapper = await openStats();

        expect(wrapper.get('a[href="/open-data.csv"]').text()).toBe('Descargar para Excel (CSV)');
        expect(wrapper.get('a[href="/open-data.json"]').text()).toBe('Datos para programadores (JSON)');
        // It. 40e: al mapa se vuelve por la pestaña "Obras".
        expect(wrapper.get('nav[aria-label="Secciones de la veeduría"] a[href="/"]').text()).toBe('Obras');
    });

    it('Las estadísticas no llaman inconclusas a las obras en riesgo (it. 44a)', async () => {
        const wrapper = await openStats();

        expect(wrapper.text()).toContain('Las obras en riesgo son alertas de GovTrace, no obras inconclusas en el sentido de la Ley 2020 de 2020.');
    });
});
