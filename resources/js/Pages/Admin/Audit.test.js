// Iteración 21 — US-043-MON: el Administrador ve el log de SU organización.
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Audit from './Audit.vue';
import { fetchAuditLog } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

describe('Registro de auditoría de la organización', () => {
    it('El Administrador de Organización ve solo lo de su organización: asks for its own log', async () => {
        fetchAuditLog.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
        const wrapper = mount(Audit);
        await flushPromises();

        expect(fetchAuditLog).toHaveBeenCalledWith(1, {});
        expect(wrapper.get('h1').text()).toBe('Registro de auditoría');
    });
});
