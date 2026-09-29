// Iteración 29 — US-042-SEC (UI): el Administrador autoriza al Super
// Administrador a crear reportes en nombre de la organización (30 días), o
// revoca la autorización.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SuperAdminAuthorization from './SuperAdminAuthorization.vue';
import { authorizeSuperAdmin, fetchSuperAdminAuthorization, revokeSuperAdmin } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const NONE = { active: false, granted_at: null, expires_at: null, granted_by: null };
const ACTIVE = { active: true, granted_at: '2026-09-24T15:00:00+00:00', expires_at: '2026-10-24T15:00:00+00:00', granted_by: 'Ana Pérez' };

async function openAuthorization(...answers) {
    answers.forEach((answer) => fetchSuperAdminAuthorization.mockResolvedValueOnce(answer));
    const wrapper = mount(SuperAdminAuthorization);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => vi.resetAllMocks());

describe('Autorización al Super Administrador', () => {
    it('explains what it allows, and grants it for 30 days', async () => {
        authorizeSuperAdmin.mockResolvedValue({ message: 'El Super Administrador puede crear reportes en nombre de la organización hasta el 29/10/2026.' });
        const wrapper = await openAuthorization(NONE, { ...ACTIVE, expires_at: '2026-10-29T15:00:00+00:00' });

        expect(wrapper.text()).toContain('No hay una autorización vigente.');
        expect(wrapper.text()).toContain('Mientras esté vigente, el Super Administrador puede crear reportes en nombre de la organización. Cada reporte queda en el registro de auditoría.');
        await button(wrapper, 'Autorizar por 30 días').trigger('click');
        await flushPromises();

        expect(authorizeSuperAdmin).toHaveBeenCalledOnce();
        expect(wrapper.get('[role="status"]').text()).toBe('El Super Administrador puede crear reportes en nombre de la organización hasta el 29/10/2026.');
        expect(wrapper.text()).toContain('Vigente hasta el 29/10/2026');
    });

    it('shows the one in force, who granted it, and revokes it', async () => {
        revokeSuperAdmin.mockResolvedValue({ message: 'Autorización revocada. El Super Administrador ya no puede crear reportes en nombre de la organización.' });
        const wrapper = await openAuthorization(ACTIVE, NONE);

        expect(wrapper.text()).toContain('Vigente hasta el 24/10/2026, otorgada por Ana Pérez.');
        expect(button(wrapper, 'Autorizar por 30 días')).toBeUndefined();
        await button(wrapper, 'Revocar autorización').trigger('click');
        await flushPromises();

        expect(revokeSuperAdmin).toHaveBeenCalledOnce();
        expect(wrapper.get('[role="status"]').text()).toContain('Autorización revocada.');
        expect(wrapper.text()).toContain('No hay una autorización vigente.');
    });

    it('shows why the server refused it', async () => {
        authorizeSuperAdmin.mockRejectedValue({ response: { status: 422, data: { errors: { authorization: ['Ya hay una autorización vigente, hasta el 24/10/2026. Revóquela antes de otorgar otra.'] } } } });
        const wrapper = await openAuthorization(NONE);

        await button(wrapper, 'Autorizar por 30 días').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Ya hay una autorización vigente, hasta el 24/10/2026. Revóquela antes de otorgar otra.');
    });

    it('shows that it is loading', () => {
        fetchSuperAdminAuthorization.mockReturnValue(new Promise(() => {}));

        expect(mount(SuperAdminAuthorization).text()).toContain('Cargando la autorización…');
    });
});
