// Iteración 34 — US-053-RPT (UI): el resumen de uso por organización, para
// el Super Administrador, con su última actividad (US-054-RPT).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Usage from './Usage.vue';
import { fetchUsage } from '@/services/api.js';
import { formatDateTime } from '@/lib/format.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const smr = {
    organization: 'Veeduría Ciudadana Santa Marta',
    status: 'Activa',
    active_observers: 8,
    received: 50,
    published: 30,
    rejected: 5,
    withdrawn: 2,
    last_activity_at: '2026-09-25T10:00:00-05:00',
};

async function openUsage(rows = [smr]) {
    fetchUsage.mockResolvedValue(rows);
    const wrapper = mount(Usage);
    await flushPromises();
    return wrapper;
}

beforeEach(() => vi.resetAllMocks());

describe('Resumen de uso', () => {
    it('Resumen de uso de una organización: its row with the veedores and the evidences', async () => {
        const row = (await openUsage()).get('[data-test="usage-row"]');

        expect(row.text()).toContain('Veeduría Ciudadana Santa Marta');
        const figure = (name) => row.get(`[data-test="${name}"]`).text();
        expect([figure('active_observers'), figure('received'), figure('published'), figure('rejected'), figure('withdrawn')]).toEqual(['8', '50', '30', '5', '2']);
        expect(row.text()).toContain(`Última actividad: ${formatDateTime('2026-09-25T10:00:00-05:00')}`);
    });

    it('says when an organization never had activity, and its status', async () => {
        const row = (await openUsage([{ ...smr, status: 'Suspendida', received: 0, last_activity_at: null }])).get('[data-test="usage-row"]');

        expect(row.text()).toContain('Sin actividad todavía');
        expect(row.text()).toContain('Suspendida');
    });

    it('shows that it is loading, and says when there are no organizations', async () => {
        fetchUsage.mockReturnValue(new Promise(() => {}));
        expect(mount(Usage).text()).toContain('Cargando el resumen de uso…');

        expect((await openUsage([])).text()).toContain('Aún no hay organizaciones registradas.');
    });
});
