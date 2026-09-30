// It. 40c — V11: cambiar la contraseña con la sesión abierta, desde "Mi cuenta".
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ChangePassword from './ChangePassword.vue';
import { changePassword } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

async function fill(wrapper, current, next, again = next) {
    await wrapper.get('#current_password').setValue(current);
    await wrapper.get('#password').setValue(next);
    await wrapper.get('#password_confirmation').setValue(again);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: { name: 'Carlos', email: 'carlos@correo.co', role: 'Veedor de Campo' } };
});

describe('Cambiar contraseña', () => {
    it('Cambiar la contraseña con la sesión abierta: sends the current one and the new one twice, and says it changed', async () => {
        changePassword.mockResolvedValue({ message: 'Su contraseña fue cambiada. La próxima vez entre con la nueva.' });
        const wrapper = mount(ChangePassword, { props: { home: '/reports/new' } });

        expect(wrapper.get('h1').text()).toBe('Cambiar contraseña');
        await fill(wrapper, 'Veeduria#2026', 'Nueva#Clave2027');

        expect(changePassword).toHaveBeenCalledWith({ current_password: 'Veeduria#2026', password: 'Nueva#Clave2027', password_confirmation: 'Nueva#Clave2027' });
        expect(wrapper.get('[role="status"]').text()).toBe('Su contraseña fue cambiada. La próxima vez entre con la nueva.');
        expect(wrapper.get('a[href="/reports/new"]').text()).toBe('Volver a mi panel');
    });

    it('says what is wrong, next to its field, and does not send two different new ones', async () => {
        const wrapper = mount(ChangePassword, { props: { home: '/reports/new' } });

        await fill(wrapper, 'Veeduria#2026', 'Nueva#Clave2027', 'Otra#Clave2027');
        expect(changePassword).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Las contraseñas no coinciden.');

        changePassword.mockRejectedValue({ response: { status: 422, data: { errors: { current_password: ['La contraseña actual no es correcta.'] } } } });
        await fill(wrapper, 'Mala#2026', 'Nueva#Clave2027');
        expect(wrapper.text()).toContain('La contraseña actual no es correcta.');
    });

    it('lets the person see what they are typing', async () => {
        const wrapper = mount(ChangePassword, { props: { home: '/reports/new' } });

        expect(wrapper.get('#password').attributes('type')).toBe('password');
        await wrapper.get('button[aria-controls="password"]').trigger('click');
        expect(wrapper.get('#password').attributes('type')).toBe('text');
    });
});
