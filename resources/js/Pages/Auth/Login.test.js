// Iteración 17 — US-031 (UI): la pantalla de inicio de sesión, la misma en el
// subdominio de cada organización y en el panel global. El servidor decide
// (LoginTest, it. 4); aquí, lo que la pantalla envía y cómo muestra cada
// respuesta.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from './Login.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => ({ Head: { render: () => null }, useForm: (await import('@/testing/inertia.js')).useForm }));

function loginAs(email, password) {
    const wrapper = mount(Login, { props: { context: 'Veeduría Ciudadana Santa Marta' } });
    return {
        wrapper,
        async submit() {
            await wrapper.get('input#email').setValue(email);
            await wrapper.get('input#password').setValue(password);
            await wrapper.get('form').trigger('submit');
        },
    };
}

beforeEach(resetInertia);

describe('Iniciar sesión', () => {
    it('shows where the user is logging in, and sends email and password to /login', async () => {
        const { wrapper, submit } = loginAs('carlos@correo.co', 'Veeduria#2026');

        expect(wrapper.text()).toContain('Veeduría Ciudadana Santa Marta');
        await submit();

        expect(submissions).toEqual([{ url: '/login', data: { email: 'carlos@correo.co', password: 'Veeduria#2026' } }]);
        expect(wrapper.get('input#password').attributes('type')).toBe('password');
        expect(wrapper.get('input#email').attributes('autocomplete')).toBe('username');
    });

    it.each([
        ['Credenciales incorrectas', 'Otra#2026', 'Credenciales incorrectas. Verifique su correo electrónico y contraseña.'],
        ['Bloqueo temporal tras 5 intentos fallidos', 'Veeduria#2026', 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.'],
        ['Cuenta desactivada', 'Veeduria#2026', 'Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.'],
    ])('%s: shows the reason and asks for the password again', async (_scenario, password, message) => {
        respondWith({ email: message });
        const { wrapper, submit } = loginAs('carlos@correo.co', password);

        await submit();

        expect(wrapper.get('[role="alert"]').text()).toBe(message);
        expect(wrapper.get('input#password').element.value).toBe('');
        expect(wrapper.get('input#email').element.value).toBe('carlos@correo.co');
    });

    it.each([
        ['carlos@', 'Veeduria#2026', 'Escriba un correo electrónico válido.'],
        ['carlos@correo.co', '', 'Escriba su contraseña.'],
    ])('Validaciones del formulario de acceso: "%s" / "%s" does not log in', async (email, password, message) => {
        const { wrapper, submit } = loginAs(email, password);

        await submit();

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(message);
    });
});
