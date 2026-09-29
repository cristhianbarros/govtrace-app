// Iteración 29 — US-049-RPT (UI): el resumen del territorio para el
// Administrador — obras por color, evidencias por clasificación y por mes,
// y veedores activos.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Summary from './Summary.vue';
import { fetchSummary } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const summary = {
    worksites_by_color: { green: 10, yellow: 4, red: 3 },
    evidences_by_classification: { Avance: 25, Retraso: 8, Abandono: 3 },
    evidences_by_month: [
        { month: '2026-08', Avance: 25, Retraso: 0, Abandono: 1 },
        { month: '2026-09', Avance: 0, Retraso: 8, Abandono: 2 },
    ],
    active_observers: 6,
};

async function openSummary(answer = summary) {
    fetchSummary.mockResolvedValue(answer);
    const wrapper = mount(Summary);
    await flushPromises();
    return wrapper;
}

const figure = (wrapper, name) => wrapper.get(`[data-test="${name}"]`).text();

beforeEach(() => vi.resetAllMocks());

describe('Resumen del territorio', () => {
    it('Resumen de la organización: the worksites by color, the evidences by classification and by month, the active veedores', async () => {
        const wrapper = await openSummary();

        expect(figure(wrapper, 'green')).toContain('10');
        expect(figure(wrapper, 'green')).toContain('Verdes');
        expect(figure(wrapper, 'yellow')).toContain('4');
        expect(figure(wrapper, 'red')).toContain('3');
        expect(figure(wrapper, 'Avance')).toContain('25');
        expect(figure(wrapper, 'Retraso')).toContain('8');
        expect(figure(wrapper, 'Abandono')).toContain('3');
        expect(figure(wrapper, 'observers')).toContain('6');
        const months = wrapper.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()));
        expect(months).toEqual([
            ['agosto de 2026', '25', '0', '1'],
            ['septiembre de 2026', '0', '8', '2'],
        ]);
    });

    it('says when there are no evidences yet', async () => {
        const wrapper = await openSummary({ ...summary, evidences_by_month: [] });

        expect(wrapper.text()).toContain('Aún no hay evidencias en el territorio.');
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('shows that it is loading, says when it could not, and lets retry', async () => {
        fetchSummary.mockReturnValue(new Promise(() => {}));
        expect(mount(Summary).text()).toContain('Cargando el resumen…');

        fetchSummary.mockReset();
        fetchSummary.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(summary);
        const wrapper = mount(Summary);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();
        expect(figure(wrapper, 'observers')).toContain('6');
    });
});
