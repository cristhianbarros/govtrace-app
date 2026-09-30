// Iteración 22 — US-014 (UI): el panel de salud de la sincronización SECOP II.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SecopHealth from './SecopHealth.vue';
import { fetchSecopHealth, syncSecopNow } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const success = {
    status: 'Success',
    healthy: true,
    date: '28/09/2026',
    started_at: '02:00',
    finished_at: '02:07',
    processed: 150,
    inserted: 20,
    updated: 130,
    discarded: 3,
    unmatched_locations: { 'Magdalena / Villa Inexistente': 3 },
    per_organization: [{ organization: 'Veeduría Ciudadana Santa Marta', inserted: 20, updated: 130 }],
    message: null,
};
const TIMEOUT = 'Falla de sincronización con SECOP II: El servicio remoto no respondió (Error HTTP 504 Gateway Timeout). Reintento programado en 30 minutos.';

async function openHealth(data) {
    fetchSecopHealth.mockResolvedValue(data);
    const wrapper = mount(SecopHealth);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Salud de SECOP II', () => {
    it('shows that it is loading, and says when it could not load', async () => {
        fetchSecopHealth.mockReturnValue(new Promise(() => {}));
        expect(mount(SecopHealth).text()).toContain('Cargando la última sincronización…');

        fetchSecopHealth.mockReset();
        fetchSecopHealth.mockRejectedValue(new Error('Network Error'));
        const failed = mount(SecopHealth);
        await flushPromises();
        expect(failed.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
    });

    it('Métricas de la última ejecución exitosa: start, end, status, processed by organization, discarded', async () => {
        const wrapper = await openHealth(success);

        const text = wrapper.text();
        for (const part of ['02:00', '02:07', 'Success', '150 contratos procesados', 'Veeduría Ciudadana Santa Marta', '20 nuevos', '130 actualizados', '3 descartados por no emparejar con DIVIPOLA', 'Magdalena / Villa Inexistente']) {
            expect(text).toContain(part);
        }
        expect(wrapper.get('[data-test="health"]').classes()).toContain('bg-emerald-500');
    });

    it('La última sincronización falló por timeout: red indicator and the message', async () => {
        const wrapper = await openHealth({ ...success, status: 'Failed', healthy: false, message: TIMEOUT });

        expect(wrapper.get('[data-test="health"]').classes()).toContain('bg-red-600');
        expect(wrapper.get('[data-test="failure"]').text()).toBe(TIMEOUT);
    });

    it('says when no sync has run yet', async () => {
        const wrapper = await openHealth(null);

        expect(wrapper.text()).toContain('Aún no ha corrido ninguna sincronización con SECOP II.');
    });
});

describe('Sincronizar ahora (it. 43b, V15)', () => {
    it('Sincronizar SECOP a mano: asks for the sync and says it is on its way, or why not', async () => {
        const wrapper = await openHealth(success);
        syncSecopNow.mockResolvedValueOnce({ message: 'Sincronización con SECOP II en marcha. En unos minutos verá el resultado aquí.' });

        await wrapper.findAll('button').find((button) => button.text() === 'Sincronizar ahora').trigger('click');
        await flushPromises();

        expect(syncSecopNow).toHaveBeenCalledOnce();
        expect(wrapper.get('[role="status"]').text()).toBe('Sincronización con SECOP II en marcha. En unos minutos verá el resultado aquí.');

        syncSecopNow.mockRejectedValueOnce({ response: { status: 429, data: { message: 'Ya se pidió una sincronización hace menos de 5 minutos. Espere su resultado.' } } });
        await wrapper.findAll('button').find((button) => button.text() === 'Sincronizar ahora').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="status"]').text()).toBe('Ya se pidió una sincronización hace menos de 5 minutos. Espere su resultado.');
    });
});
