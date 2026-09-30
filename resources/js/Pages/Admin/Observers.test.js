// Iteración 18 — US-005 (UI): invitar veedores por correo y ver el equipo,
// con el estado de cada invitación.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Observers from './Observers.vue';
import { deactivateObserver, fetchObservers, inviteObserver, reactivateObserver, resendInvitation, revokeInvitation } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const DUPLICATE = 'Ya existe un usuario registrado o una invitación pendiente con este correo electrónico en la organización.';

async function openObservers(team = []) {
    fetchObservers.mockResolvedValue(team);
    const wrapper = mount(Observers);
    await flushPromises();
    return wrapper;
}

async function invite(wrapper, email) {
    await wrapper.get('input#invite-email').setValue(email);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Veedores', () => {
    it('shows that it is loading the team', () => {
        fetchObservers.mockReturnValue(new Promise(() => {}));

        expect(mount(Observers).text()).toContain('Cargando veedores…');
    });

    it('says when it could not load the team, and lets retry', async () => {
        fetchObservers.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([{ email: 'carlos@correo.co', status: 'Activo' }]);
        const wrapper = mount(Observers);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((candidate) => candidate.text() === 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('carlos@correo.co');
    });

    it('says when nobody has been invited yet', async () => {
        const wrapper = await openObservers([]);

        expect(wrapper.text()).toContain('Aún no ha invitado veedores.');
    });

    it('lists the team with the status of each invitation', async () => {
        const wrapper = await openObservers([
            { email: 'carlos@correo.co', status: 'Activo' },
            { email: 'laura@correo.co', status: 'Invitación pendiente' },
        ]);

        const rows = wrapper.get('main').findAll('li').map((row) => row.text());
        expect(rows).toHaveLength(2);
        expect(rows[0]).toContain('carlos@correo.co');
        expect(rows[0]).toContain('Activo');
        expect(rows[1]).toContain('laura@correo.co');
        expect(rows[1]).toContain('Invitación pendiente');
    });

    it('Invitación exitosa: confirms it, clears the field and refreshes the team', async () => {
        const wrapper = await openObservers([]);
        inviteObserver.mockResolvedValue({ message: 'Invitación enviada a laura@correo.co. El enlace vence en 48 horas.' });
        fetchObservers.mockResolvedValue([{ email: 'laura@correo.co', status: 'Invitación pendiente' }]);

        await invite(wrapper, 'laura@correo.co');

        expect(inviteObserver).toHaveBeenCalledWith('laura@correo.co');
        expect(wrapper.get('[role="status"]').text()).toBe('Invitación enviada a laura@correo.co. El enlace vence en 48 horas.');
        expect(wrapper.get('input#invite-email').element.value).toBe('');
        expect(wrapper.text()).toContain('Invitación pendiente');
    });

    it('No se puede invitar un correo ya registrado o con invitación pendiente: shows why', async () => {
        const wrapper = await openObservers([{ email: 'carlos@correo.co', status: 'Activo' }]);
        inviteObserver.mockRejectedValue({ response: { status: 422, data: { message: DUPLICATE, errors: { email: [DUPLICATE] } } } });

        await invite(wrapper, 'carlos@correo.co');

        expect(wrapper.get('form [role="alert"]').text()).toBe(DUPLICATE);
    });

    it('does not send an address that is not an email', async () => {
        const wrapper = await openObservers([]);

        await invite(wrapper, 'carlos@');

        expect(inviteObserver).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Escriba un correo electrónico válido.');
    });
});

describe('Desactivar y reactivar veedores', () => {
    const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);
    const carlos = (status) => ({ id: 7, email: 'carlos@correo.co', status });

    it('Desactivación con revocación inmediata de sesiones: asks to confirm, then deactivates and refreshes the team', async () => {
        const wrapper = await openObservers([carlos('Activo')]);
        deactivateObserver.mockResolvedValue({ message: 'Veedor desactivado. Su sesión quedó cerrada y ya no puede enviar reportes.' });
        fetchObservers.mockResolvedValue([carlos('Inactivo')]);

        await button(wrapper, 'Desactivar').trigger('click');
        expect(deactivateObserver).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Su sesión se cerrará de inmediato y no podrá enviar reportes. Sus reportes anteriores se conservan.');

        await button(wrapper, 'Confirmar desactivación').trigger('click');
        await flushPromises();

        expect(deactivateObserver).toHaveBeenCalledWith(7);
        expect(wrapper.get('[role="status"]').text()).toBe('Veedor desactivado. Su sesión quedó cerrada y ya no puede enviar reportes.');
        expect(wrapper.get('main').get('li').text()).toContain('Inactivo');
        expect(button(wrapper, 'Reactivar')).toBeDefined();
    });

    it('Reactivación de un veedor: brings him back with his account', async () => {
        const wrapper = await openObservers([carlos('Inactivo')]);
        reactivateObserver.mockResolvedValue({ message: 'Veedor reactivado. Ya puede volver a iniciar sesión.' });
        fetchObservers.mockResolvedValue([carlos('Activo')]);

        expect(button(wrapper, 'Desactivar')).toBeUndefined();
        await button(wrapper, 'Reactivar').trigger('click');
        await flushPromises();

        expect(reactivateObserver).toHaveBeenCalledWith(7);
        expect(wrapper.get('[role="status"]').text()).toBe('Veedor reactivado. Ya puede volver a iniciar sesión.');
    });

    it('shows why the server refused, on that veedor', async () => {
        const wrapper = await openObservers([carlos('Activo')]);
        deactivateObserver.mockRejectedValue({ response: { status: 422, data: { message: 'El veedor ya está inactivo.', errors: { status: ['El veedor ya está inactivo.'] } } } });

        await button(wrapper, 'Desactivar').trigger('click');
        await button(wrapper, 'Confirmar desactivación').trigger('click');
        await flushPromises();

        expect(wrapper.get('main').get('li [role="alert"]').text()).toBe('El veedor ya está inactivo.');
    });
});

// Iteración 33 — US-040-USR (UI): reenviar o revocar una invitación pendiente.
describe('Reenviar o revocar una invitación', () => {
    const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);
    const carlos = (status) => ({ id: 7, email: 'carlos@correo.co', status });

    it('Reenviar una invitación: sends a new link and says until when', async () => {
        const wrapper = await openObservers([carlos('Invitación pendiente')]);
        resendInvitation.mockResolvedValue({ message: 'Invitación reenviada a carlos@correo.co. El nuevo enlace vence en 48 horas.' });

        await button(wrapper, 'Reenviar invitación').trigger('click');
        await flushPromises();

        expect(resendInvitation).toHaveBeenCalledWith(7);
        expect(wrapper.get('[role="status"]').text()).toBe('Invitación reenviada a carlos@correo.co. El nuevo enlace vence en 48 horas.');
        expect(fetchObservers).toHaveBeenCalledTimes(2);
    });

    it('resends an invitation that expired unanswered', async () => {
        const wrapper = await openObservers([carlos('Invitación vencida')]);

        expect(button(wrapper, 'Reenviar invitación')).toBeDefined();
        expect(button(wrapper, 'Revocar invitación')).toBeDefined();
    });

    it('Un enlace revocado no permite activar la cuenta: asks to confirm, then revokes it and the invitation leaves the team', async () => {
        const wrapper = await openObservers([carlos('Invitación pendiente')]);
        revokeInvitation.mockResolvedValue({ message: 'Invitación revocada. El enlace enviado a carlos@correo.co ya no es válido.' });
        fetchObservers.mockResolvedValue([]);

        await button(wrapper, 'Revocar invitación').trigger('click');
        expect(revokeInvitation).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('El enlace enviado dejará de funcionar. Podrá invitar ese correo de nuevo.');

        await button(wrapper, 'Confirmar revocación').trigger('click');
        await flushPromises();

        expect(revokeInvitation).toHaveBeenCalledWith(7);
        expect(wrapper.get('[role="status"]').text()).toBe('Invitación revocada. El enlace enviado a carlos@correo.co ya no es válido.');
        expect(wrapper.text()).toContain('Aún no ha invitado veedores.');
    });

    it('offers neither to a veedor who already has an account, nor deactivating a pending invitation', async () => {
        const wrapper = await openObservers([carlos('Activo'), { id: 8, email: 'lucia@correo.co', status: 'Invitación pendiente' }]);

        const [active, pending] = wrapper.get('main').findAll('li');
        expect(active.findAll('button').map((candidate) => candidate.text())).toEqual(['Desactivar']);
        expect(pending.findAll('button').map((candidate) => candidate.text())).toEqual(['Reenviar invitación', 'Revocar invitación']);
    });
});
