// Iteración 46g — US-065-SEC (UI): después de la contraseña, el código de la
// app o un código de recuperación. El servidor decide (SuperAdminTwoFactorTest).
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TwoFactorChallenge from './TwoFactorChallenge.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const challenge = () => mount(TwoFactorChallenge, { props: { email: 'ana@govtrace.org' } });
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => resetInertia());

describe('El código de la app', () => {
    it('El Super Administrador entra con su contraseña y el código de su app: sends the code to /two-factor', async () => {
        const wrapper = challenge();

        expect(wrapper.text()).toContain('ana@govtrace.org');
        await wrapper.get('input#code').setValue('654 321');
        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([{ url: '/two-factor', data: { code: '654321', recovery_code: '' } }]);
        expect(wrapper.get('input#code').attributes('autocomplete')).toBe('one-time-code');
    });

    it('Sin su teléfono, entra con un código de recuperación, que sirve una sola vez: the recovery code, in its own field, and back', async () => {
        const wrapper = challenge();

        await button(wrapper, 'Usar un código de recuperación').trigger('click');
        expect(wrapper.find('input#code').exists()).toBe(false);
        await wrapper.get('input#recovery-code').setValue('7kq3m-9xd2p');
        await wrapper.get('form').trigger('submit');
        expect(submissions).toEqual([{ url: '/two-factor', data: { code: '', recovery_code: '7KQ3M-9XD2P' } }]);

        await button(wrapper, 'Usar el código de la app').trigger('click');
        expect(wrapper.find('input#code').exists()).toBe(true);
    });

    it('Un código equivocado, vencido o ya usado no deja entrar: shows why', async () => {
        respondWith({ code: 'El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.' });
        const wrapper = challenge();

        await wrapper.get('input#code').setValue('000000');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.get('[role="alert"]').text()).toBe('El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.');
        expect(wrapper.get('input#code').element.value).toBe('');
    });

    it('asks for the 6 digits before sending', async () => {
        const wrapper = challenge();

        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Escriba los 6 dígitos que muestra su app.');
    });
});
