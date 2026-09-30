// Iteración 17 — US-030 (UI): la pantalla del enlace de invitación, donde el
// veedor (o el Administrador inicial) crea su contraseña. El servidor decide
// si el enlace vale (AcceptInvitationTest, it. 5); aquí, lo que la pantalla
// muestra y envía.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SetPassword from './SetPassword.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => { const inertia = await import('@/testing/inertia.js'); return { Head: { render: () => null }, useForm: inertia.useForm, usePage: inertia.usePage, Link: inertia.Link, router: inertia.router }; });

const RULES = 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.';
const EXPIRED = 'El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.';

const validLink = { valid: true, email: 'carlos@correo.co', token: 'token-del-correo', action: '/set-password/7' };

async function definePassword(wrapper, password, confirmation = password) {
    await wrapper.get('input#password').setValue(password);
    await wrapper.get('input#password_confirmation').setValue(confirmation);
    await wrapper.get('form').trigger('submit');
}

beforeEach(resetInertia);

describe('Crear la contraseña', () => {
    it('Activación exitosa de la cuenta: sends the password, its confirmation and the token of the link', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        expect(wrapper.text()).toContain('carlos@correo.co');
        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions).toEqual([
            { url: '/set-password/7', data: { token: 'token-del-correo', password: 'Veeduria#2026', password_confirmation: 'Veeduria#2026' } },
        ]);
        expect(wrapper.get('input#password').attributes('autocomplete')).toBe('new-password');
    });

    it('Enlace de invitación vencido: the password cannot be created, and the screen says why', () => {
        const wrapper = mount(SetPassword, { props: { valid: false, message: EXPIRED } });

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it.each([
        ['menos de 8 caracteres', 'Ve#2026'],
        ['sin mayúscula', 'veeduria#2026'],
        ['sin minúscula', 'VEEDURIA#2026'],
        ['sin número', 'Veeduria#abc'],
        ['sin símbolo', 'Veeduria2026'],
    ])('La contraseña debe cumplir las reglas mínimas: %s', async (_rule, password) => {
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, password);

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(RULES);
    });

    it('asks for the same password twice', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, 'Veeduria#2026', 'Veeduria#2025');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Las contraseñas no coinciden.');
    });

    it('shows the server rejection when the link expires while the form is open', async () => {
        respondWith({ token: EXPIRED });
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, 'Veeduria#2026');

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
    });
});
