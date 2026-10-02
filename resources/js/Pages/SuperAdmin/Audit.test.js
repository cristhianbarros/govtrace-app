// Iteración 21 — US-043-MON: el Super Administrador ve todo el log.
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Audit from './Audit.vue';
import { fetchGlobalAuditLog } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

describe('Registro de auditoría global', () => {
    it('El Super Administrador ve todo el log, with the organization of each entry', async () => {
        fetchGlobalAuditLog.mockResolvedValue({
            data: [{ id: 1, created_at: '2026-09-28T15:00:00+00:00', organization: 'Veeduría Ciénaga', actor: 'Root · Super Administrador', action: 'Suspendió la organización', before: { status: 'active' }, after: { status: 'suspended' } }],
            meta: { current_page: 1, last_page: 1, total: 1 },
        });
        const wrapper = mount(Audit);
        await flushPromises();

        expect(fetchGlobalAuditLog).toHaveBeenCalledWith(1, {});
        expect(wrapper.get('[data-test="audit-entry"]').text()).toContain('Veeduría Ciénaga');
    });
});
