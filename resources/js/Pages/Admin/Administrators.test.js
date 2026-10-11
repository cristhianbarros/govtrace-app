// Iteración 54 — los administradores de la organización, en su propia pantalla
// (antes, una sección de "Veedores"): ver quién gestiona la veeduría con su
// estado, e invitar a otro (V3, it. 43j; US-061-USR).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Administrators from './Administrators.vue';
import { fetchAdministrators, fetchObservers, inviteAdministrator } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const marta = { id: 1, name: 'Marta Ospina', email: 'marta@veeduria.org', status: 'active', label: 'Activo' };

async function openAdministrators(administrators = [marta]) {
    fetchAdministrators.mockResolvedValue(administrators);
    const wrapper = mount(Administrators);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Administradores', () => {
    it('shows that it is loading the administrators', () => {
        fetchAdministrators.mockReturnValue(new Promise(() => {}));

        expect(mount(Administrators).text()).toContain('Cargando administradores…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchAdministrators.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([marta]);
        const wrapper = mount(Administrators);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((candidate) => candidate.text() === 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Marta Ospina');
    });

    it('shows the administrators of the organization, with how each one is going', async () => {
        const wrapper = await openAdministrators([marta, { id: 2, name: 'Ana Pérez', email: 'ana@veeduria.org', status: 'pending', label: 'Invitación pendiente' }]);
        const section = wrapper.get('[data-test="administrators"]');

        expect(section.get('h2').text()).toBe('Administradores');
        expect(section.findAll('li').map((item) => item.text())).toEqual([
            expect.stringContaining('Marta Ospina'),
            expect.stringContaining('Invitación pendiente'),
        ]);
    });

    it('explains why there should be more than one, and who can deactivate them', async () => {
        const wrapper = await openAdministrators();

        expect(wrapper.text()).toContain('la veeduría no queda sin quién la gestione');
        expect(wrapper.text()).toContain('Desactivar a un administrador lo hace el Super Administrador.');
    });

    it('Un Administrador invita a otro administrador: with a name and an email, and says so', async () => {
        inviteAdministrator.mockResolvedValue({ message: 'Invitación enviada a ana@veeduria.org. El enlace vence en 48 horas.' });
        const wrapper = await openAdministrators();

        await wrapper.get('input#administrator-name').setValue('Ana Pérez');
        await wrapper.get('input#administrator-email').setValue('ana@veeduria.org');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(inviteAdministrator).toHaveBeenCalledWith({ name: 'Ana Pérez', email: 'ana@veeduria.org' });
        expect(wrapper.get('[role="status"]').text()).toBe('Invitación enviada a ana@veeduria.org. El enlace vence en 48 horas.');
        // La lista se pide de nuevo: la invitación aparece sin recargar.
        expect(fetchAdministrators).toHaveBeenCalledTimes(2);
    });

    it('does not send an invitation without a name and a valid email', async () => {
        const wrapper = await openAdministrators();

        await wrapper.get('input#administrator-email').setValue('ana@');
        await wrapper.get('form').trigger('submit');

        expect(inviteAdministrator).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Escriba el nombre y un correo electrónico válido.');
    });

    it('says why the server refused the invitation', async () => {
        inviteAdministrator.mockRejectedValue({ response: { status: 422, data: { errors: { email: ['El correo electrónico ya se encuentra registrado en el sistema.'] } } } });
        const wrapper = await openAdministrators();

        await wrapper.get('input#administrator-name').setValue('Carlos');
        await wrapper.get('input#administrator-email').setValue('carlos@correo.co');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('El correo electrónico ya se encuentra registrado en el sistema.');
    });

    it('Veedores y Administradores van en pantallas separadas: la de Administradores no trae veedores, ni los pide', async () => {
        const wrapper = await openAdministrators();

        expect(wrapper.text()).not.toContain('Invitar un veedor de campo');
        expect(wrapper.find('input#invite-email').exists()).toBe(false);
        expect(fetchObservers).not.toHaveBeenCalled();
    });
});
