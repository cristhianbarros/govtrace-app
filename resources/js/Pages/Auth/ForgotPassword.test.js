// Iteración 20 — US-039-USR (UI): pedir el enlace para restablecer la
// contraseña. La respuesta es la misma exista o no el correo.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ForgotPassword from './ForgotPassword.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const NEUTRAL = 'Si el correo existe, recibirás un enlace';

async function ask(email) {
    const wrapper = mount(ForgotPassword, { props: { context: 'Veeduría Ciudadana Santa Marta' } });
    await wrapper.get('input#email').setValue(email);
    await wrapper.get('form').trigger('submit');
    return wrapper;
}

beforeEach(resetInertia);

describe('¿Olvidó su contraseña?', () => {
    it('sends the email to /forgot-password and answers the same whether it exists or not', async () => {
        respondWith();
        const wrapper = await ask('nadie@correo.co');

        expect(submissions).toEqual([{ url: '/forgot-password', data: { email: 'nadie@correo.co' } }]);
        expect(wrapper.get('[role="status"]').text()).toBe(NEUTRAL);
        expect(wrapper.text()).toContain('Veeduría Ciudadana Santa Marta');
    });

    it('does not send an address that is not an email', async () => {
        const wrapper = await ask('carlos@');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Escriba un correo electrónico válido.');
    });

    it('goes back to the login', async () => {
        const wrapper = await ask('carlos@correo.co');

        expect(wrapper.get('a').attributes('href')).toBe('/login');
    });
});
