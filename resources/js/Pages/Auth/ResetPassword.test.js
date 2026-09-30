// Iteración 20 — US-039-USR (UI): la pantalla del enlace, donde se elige la
// nueva contraseña. El servidor decide si el enlace vale (PasswordResetTest).
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ResetPassword from './ResetPassword.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const RULES = 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.';
const EXPIRED = 'El enlace de restablecimiento de contraseña ha expirado o ya ha sido utilizado.';
const validLink = { valid: true, email: 'carlos@correo.co', token: 'token-del-correo' };

async function choose(wrapper, password, confirmation = password) {
    await wrapper.get('input#password').setValue(password);
    await wrapper.get('input#password_confirmation').setValue(confirmation);
    await wrapper.get('form').trigger('submit');
}

beforeEach(resetInertia);

describe('Restablecer la contraseña', () => {
    it('Restablecimiento exitoso: sends the new password with the token and email of the link', async () => {
        const wrapper = mount(ResetPassword, { props: validLink });

        expect(wrapper.text()).toContain('carlos@correo.co');
        await choose(wrapper, 'Nueva#2026x');

        expect(submissions).toEqual([
            { url: '/reset-password', data: { token: 'token-del-correo', email: 'carlos@correo.co', password: 'Nueva#2026x', password_confirmation: 'Nueva#2026x' } },
        ]);
        expect(wrapper.get('input#password').attributes('autocomplete')).toBe('new-password');
    });

    it('Enlace vencido o ya usado: no form, only the reason', () => {
        const wrapper = mount(ResetPassword, { props: { valid: false, message: EXPIRED } });

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.get('main a').attributes('href')).toBe('/forgot-password');
    });

    it('La nueva contraseña cumple las reglas mínimas: "corta1#" is not sent', async () => {
        const wrapper = mount(ResetPassword, { props: validLink });

        await choose(wrapper, 'corta1#');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(RULES);
    });

    it('asks for the same password twice', async () => {
        const wrapper = mount(ResetPassword, { props: validLink });

        await choose(wrapper, 'Nueva#2026x', 'Nueva#2026y');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Las contraseñas no coinciden.');
    });

    it('shows the server rejection when the link expired while the form was open', async () => {
        respondWith({ token: EXPIRED });
        const wrapper = mount(ResetPassword, { props: validLink });

        await choose(wrapper, 'Nueva#2026x');

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
    });
});
