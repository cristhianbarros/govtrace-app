// Iteración 46a — US-063-USR: varios Super Administradores, y nunca ninguno.
// La pantalla: quiénes son y en qué va cada uno, invitar a otro, desactivar
// al que se fue (nunca a uno mismo) y reactivarlo, y reenviar o revocar una
// invitación. Las reglas las prueba SuperAdministratorsTest.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SuperAdministrators from './SuperAdministrators.vue';
import {
    deactivateSuperAdministrator,
    fetchSuperAdministrators,
    inviteSuperAdministrator,
    reactivateSuperAdministrator,
    resendSuperAdministratorInvitation,
    revokeSuperAdministratorInvitation,
} from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const ana = { id: 1, name: 'Ana Directora', email: 'ana@govtrace.org', status: 'active', label: 'Activo', is_me: true };
const luis = { id: 2, name: 'Luis Gómez', email: 'luis@govtrace.org', status: 'active', label: 'Activo', is_me: false };
const marta = { id: 3, name: 'Marta Ruiz', email: 'marta@govtrace.org', status: 'pending', label: 'Invitación pendiente', is_me: false };
const pedro = { id: 4, name: 'Pedro Díaz', email: 'pedro@govtrace.org', status: 'inactive', label: 'Inactivo', is_me: false };

async function openScreen(list = [ana, luis, marta, pedro]) {
    fetchSuperAdministrators.mockResolvedValue(list);
    const wrapper = mount(SuperAdministrators);
    await flushPromises();
    return wrapper;
}

const row = (wrapper, email) => wrapper.findAll('[data-test="super-admin"]').find((candidate) => candidate.text().includes(email));
const button = (scope, text) => scope.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => vi.resetAllMocks());

describe('Super Administradores (it. 46a)', () => {
    it('lists each one with their email and state, and marks the own account', async () => {
        const wrapper = await openScreen();

        expect(row(wrapper, 'ana@govtrace.org').text()).toContain('Ana Directora');
        expect(row(wrapper, 'ana@govtrace.org').text()).toContain('Usted');
        expect(row(wrapper, 'marta@govtrace.org').text()).toContain('Invitación pendiente');
        expect(row(wrapper, 'pedro@govtrace.org').text()).toContain('Inactivo');
    });

    it('Un Super Administrador no se desactiva a sí mismo: the own account has no "Desactivar"', async () => {
        const wrapper = await openScreen();

        expect(button(row(wrapper, 'ana@govtrace.org'), 'Desactivar')).toBeUndefined();
        expect(button(row(wrapper, 'luis@govtrace.org'), 'Desactivar')).toBeDefined();
    });

    it('Un Super Administrador invita a otro: sends the name and the email, and shows the list again', async () => {
        inviteSuperAdministrator.mockResolvedValue({ message: 'Invitación enviada a luis@govtrace.org. El enlace vence en 48 horas.' });
        const wrapper = await openScreen([ana]);

        await button(wrapper, 'Invitar a otro Super Administrador').trigger('click');
        await wrapper.get('input#super-admin-name').setValue('Luis Gómez');
        await wrapper.get('input#super-admin-email').setValue('luis@govtrace.org');
        await wrapper.get('form[data-test="invite"]').trigger('submit');
        await flushPromises();

        expect(inviteSuperAdministrator).toHaveBeenCalledWith('Luis Gómez', 'luis@govtrace.org');
        expect(wrapper.get('[role="status"]').text()).toContain('Invitación enviada a luis@govtrace.org.');
        expect(fetchSuperAdministrators).toHaveBeenCalledTimes(2);
    });

    it('El correo de un Super Administrador nuevo no puede estar registrado: shows why the server refused it', async () => {
        inviteSuperAdministrator.mockRejectedValue({
            response: { status: 422, data: { message: 'El correo electrónico ya se encuentra registrado en el sistema.', errors: { email: ['El correo electrónico ya se encuentra registrado en el sistema.'] } } },
        });
        const wrapper = await openScreen([ana]);

        await button(wrapper, 'Invitar a otro Super Administrador').trigger('click');
        await wrapper.get('input#super-admin-name').setValue('Otro Luis');
        await wrapper.get('input#super-admin-email').setValue('luis@govtrace.org');
        await wrapper.get('form[data-test="invite"]').trigger('submit');
        await flushPromises();

        expect(wrapper.get('form[data-test="invite"] [role="alert"]').text()).toBe('El correo electrónico ya se encuentra registrado en el sistema.');
    });

    it('Un Super Administrador desactiva a otro que se fue: asks to confirm first', async () => {
        deactivateSuperAdministrator.mockResolvedValue({ message: 'Luis Gómez ya no puede entrar al panel global.' });
        const wrapper = await openScreen();
        const luisRow = row(wrapper, 'luis@govtrace.org');

        await button(luisRow, 'Desactivar').trigger('click');
        expect(luisRow.text()).toContain('Ya no podrá entrar al panel global.');
        await button(luisRow, 'Confirmar desactivación').trigger('click');
        await flushPromises();

        expect(deactivateSuperAdministrator).toHaveBeenCalledWith(2);
    });

    it('Un Super Administrador reactiva a otro', async () => {
        reactivateSuperAdministrator.mockResolvedValue({ message: 'Pedro Díaz puede entrar otra vez.' });
        const wrapper = await openScreen();

        await button(row(wrapper, 'pedro@govtrace.org'), 'Reactivar').trigger('click');
        await flushPromises();

        expect(reactivateSuperAdministrator).toHaveBeenCalledWith(4);
    });

    it('Reenviar y revocar una invitación pendiente de Super Administrador', async () => {
        resendSuperAdministratorInvitation.mockResolvedValue({ message: 'Nuevo enlace enviado.' });
        revokeSuperAdministratorInvitation.mockResolvedValue({ message: 'Invitación revocada.' });
        const wrapper = await openScreen();
        const martaRow = row(wrapper, 'marta@govtrace.org');

        await button(martaRow, 'Reenviar invitación').trigger('click');
        await flushPromises();
        expect(resendSuperAdministratorInvitation).toHaveBeenCalledWith(3);

        await button(row(wrapper, 'marta@govtrace.org'), 'Revocar invitación').trigger('click');
        await button(row(wrapper, 'marta@govtrace.org'), 'Confirmar revocación').trigger('click');
        await flushPromises();
        expect(revokeSuperAdministratorInvitation).toHaveBeenCalledWith(3);
    });

    it('Nunca quedan cero Super Administradores activos: shows the reason on the row when the server refuses', async () => {
        deactivateSuperAdministrator.mockRejectedValue({
            response: { status: 422, data: { message: 'No se puede desactivar al único Super Administrador activo. Invite a otro y espere a que active su cuenta.' } },
        });
        const wrapper = await openScreen();
        const luisRow = row(wrapper, 'luis@govtrace.org');

        await button(luisRow, 'Desactivar').trigger('click');
        await button(luisRow, 'Confirmar desactivación').trigger('click');
        await flushPromises();

        expect(luisRow.text()).toContain('No se puede desactivar al único Super Administrador activo.');
    });
});
