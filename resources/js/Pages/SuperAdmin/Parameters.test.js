// Iteración 21 — US-038-CFG (UI): los parámetros globales. Los configurables
// se editan de a uno; los fijos se muestran, sin editar.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Parameters from './Parameters.vue';
import { fetchParameters, updateParameter } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const panel = {
    configurable: [
        { key: 'geofence_radius_meters', label: 'Radio de geocerca', unit: 'm', value: '500' },
        { key: 'secop_sync_hour', label: 'Hora de sincronización SECOP', unit: 'HH:MM', value: '02:00' },
    ],
    fixed: [{ key: 'gps_max_accuracy_meters', label: 'Precisión mínima del GPS', value: '50 m' }],
};
const UPDATED = 'Parámetro actualizado. Rige desde este momento, sin un nuevo despliegue.';

async function openParameters() {
    fetchParameters.mockResolvedValue(panel);
    const wrapper = mount(Parameters);
    await flushPromises();
    return wrapper;
}

const row = (wrapper, key) => wrapper.get(`[data-test="parameter-${key}"]`);

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Parámetros', () => {
    it('shows that it is loading, and says when it could not load', async () => {
        fetchParameters.mockReturnValue(new Promise(() => {}));
        expect(mount(Parameters).text()).toContain('Cargando parámetros…');

        fetchParameters.mockReset();
        fetchParameters.mockRejectedValue(new Error('Network Error'));
        const wrapper = mount(Parameters);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
    });

    it('Ajuste de un parámetro configurable: changes one value and confirms it rules from now', async () => {
        updateParameter.mockResolvedValue({ message: UPDATED });
        const wrapper = await openParameters();
        fetchParameters.mockResolvedValue({ ...panel, configurable: [{ ...panel.configurable[0], value: '300' }, panel.configurable[1]] });

        await row(wrapper, 'geofence_radius_meters').get('input').setValue('300');
        await row(wrapper, 'geofence_radius_meters').trigger('submit');
        await flushPromises();

        expect(updateParameter).toHaveBeenCalledWith('geofence_radius_meters', '300');
        expect(wrapper.get('[role="status"]').text()).toBe(UPDATED);
        expect(row(wrapper, 'geofence_radius_meters').get('input').element.value).toBe('300');
    });

    it('shows why the server refused the value, on that parameter', async () => {
        const message = 'La hora de sincronización SECOP debe tener el formato HH:MM, entre 00:00 y 23:59.';
        updateParameter.mockRejectedValue({ response: { status: 422, data: { message, errors: { value: [message] } } } });
        const wrapper = await openParameters();

        await row(wrapper, 'secop_sync_hour').get('input').setValue('25:00');
        await row(wrapper, 'secop_sync_hour').trigger('submit');
        await flushPromises();

        expect(row(wrapper, 'secop_sync_hour').get('[role="alert"]').text()).toBe(message);
    });

    it('Los parámetros fijos no se pueden configurar: shown without a field', async () => {
        const wrapper = await openParameters();

        const fixed = wrapper.get('[data-test="fixed-gps_max_accuracy_meters"]');
        expect(fixed.text()).toContain('Precisión mínima del GPS');
        expect(fixed.text()).toContain('50 m');
        expect(fixed.find('input').exists()).toBe(false);
    });
});
