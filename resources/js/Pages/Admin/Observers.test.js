// Iteración 18 — US-005 (UI): invitar veedores por correo y ver el equipo,
// con el estado de cada invitación.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Observers from './Observers.vue';
import { fetchObservers, inviteObserver } from '@/services/api.js';

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

        expect(wrapper.findAll('li').map((row) => row.text())).toEqual(['carlos@correo.coActivo', 'laura@correo.coInvitación pendiente']);
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
