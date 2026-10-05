// Iteración 46g — US-065-SEC (UI): la primera vez, el Super Administrador
// configura su app autenticadora. El servidor decide (SuperAdminTwoFactorTest);
// aquí, los pasos que la pantalla explica y el código que envía.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TwoFactorSetup from './TwoFactorSetup.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const QR = 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=';
const setup = () => mount(TwoFactorSetup, { props: { email: 'ana@govtrace.org', qr: QR, secret: 'JBSW Y3DP EHPK 3PXP' } });

beforeEach(() => resetInertia());

describe('Configurar la verificación en dos pasos', () => {
    it('La primera vez, el Super Administrador configura su app autenticadora: three numbered steps, the QR code and its key', () => {
        const wrapper = setup();

        const steps = wrapper.findAll('ol > li').map((step) => step.text());
        expect(steps).toHaveLength(3);
        expect(steps[0]).toContain('Instale una app autenticadora');
        expect(steps[0]).toContain('Google Authenticator');
        expect(steps[1]).toContain('Escanee este código QR');
        expect(steps[2]).toContain('Escriba el código de 6 dígitos');
        expect(wrapper.get('img').attributes('src')).toBe(QR);
        expect(wrapper.get('img').attributes('alt')).toBe('Código QR para la app autenticadora de ana@govtrace.org');
        expect(wrapper.get('[data-test="secret"]').text()).toBe('JBSW Y3DP EHPK 3PXP');
    });

    it('sends the code, without spaces, to /two-factor/setup; the phone shows numbers and can fill it in', async () => {
        const wrapper = setup();
        const input = wrapper.get('input#code');

        expect(input.attributes('inputmode')).toBe('numeric');
        expect(input.attributes('autocomplete')).toBe('one-time-code');
        await input.setValue('123 456');
        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([{ url: '/two-factor/setup', data: { code: '123456' } }]);
    });

    it('asks for the 6 digits before sending', async () => {
        const wrapper = setup();

        await wrapper.get('input#code').setValue('12');
        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Escriba los 6 dígitos que muestra su app.');
    });

    it('shows why the server refused the code', async () => {
        respondWith({ code: 'El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.' });
        const wrapper = setup();

        await wrapper.get('input#code').setValue('000000');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.get('[role="alert"]').text()).toBe('El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.');
    });
});
