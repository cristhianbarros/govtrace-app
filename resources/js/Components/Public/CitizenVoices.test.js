// Iteración 66b — US-069: "Yo también lo vi". En la página de la obra, cuántos
// ciudadanos informaron y qué hace la veeduría (solo el número); y, mientras
// la veeduría no la documenta, sumarse con el correo verificado.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CitizenVoices from './CitizenVoices.vue';
import { requestCitizenCode, supportCitizenReport } from '@/services/api.js';

vi.mock('@/services/api.js');

const WORKSITE = '01j9xq3m7v8k2d4f6g8h0jkmnp';
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);
const voices = (changes = {}) => ({ count: 1, status: 'Esperando respuesta de la veeduría', open: true, ...changes });

beforeEach(() => vi.resetAllMocks());

describe('Lo que informan los ciudadanos de la obra (it. 66b)', () => {
    it('El número público es solo eso: sin mensajes, fotos ni nombres — how many, in singular or plural, and the state', () => {
        expect(mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices() } }).get('[data-test="citizens-count"]').text())
            .toBe('1 ciudadano informó sobre esta obra · Esperando respuesta de la veeduría');
        expect(mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices({ count: 3, status: 'Un veedor la está documentando' }) } }).get('[data-test="citizens-count"]').text())
            .toBe('3 ciudadanos informaron sobre esta obra · Un veedor la está documentando');
    });

    it('Otro ciudadano se suma a lo que ya se informó de una obra: the email, the authorization and the code, and the work counts one more', async () => {
        requestCitizenCode.mockResolvedValue({ message: 'Le enviamos un código de 6 dígitos a vecino@correo.co. Vence en 10 minutos.' });
        supportCitizenReport.mockResolvedValue({ message: 'Quedó sumado: le avisamos a su correo cuando la veeduría actúe.' });
        const wrapper = mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices() } });

        expect(wrapper.text()).toContain('¿Usted también lo vio?');
        await button(wrapper, 'Yo también lo vi').trigger('click');
        await wrapper.get('input#support-email').setValue('vecino@correo.co');
        await wrapper.get('input#support-authorization').setChecked(true);
        await button(wrapper, 'Enviarme el código para sumarme').trigger('click');
        await flushPromises();
        expect(requestCitizenCode).toHaveBeenCalledWith({ email: 'vecino@correo.co', worksite_id: WORKSITE, data_authorization: true });

        await wrapper.get('input#support-code').setValue('123456');
        await button(wrapper, 'Confirmar: yo también lo vi').trigger('click');
        await flushPromises();

        expect(supportCitizenReport).toHaveBeenCalledWith({ email: 'vecino@correo.co', code: '123456', worksite_id: WORKSITE });
        expect(wrapper.get('[role="status"]').text()).toBe('Quedó sumado: le avisamos a su correo cuando la veeduría actúe.');
        expect(wrapper.get('[data-test="citizens-count"]').text()).toContain('2 ciudadanos informaron sobre esta obra');
        expect(button(wrapper, 'Yo también lo vi')).toBeUndefined();
    });

    it('Cada persona se suma una sola vez por obra: shows why the server refused it', async () => {
        requestCitizenCode.mockResolvedValue({ message: 'Le enviamos un código.' });
        supportCitizenReport.mockRejectedValue({ response: { status: 422, data: { message: 'x', errors: { email: ['Usted ya informó sobre esta obra: le avisamos a su correo cuando la veeduría actúe.'] } } } });
        const wrapper = mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices() } });

        await button(wrapper, 'Yo también lo vi').trigger('click');
        await wrapper.get('input#support-email').setValue('vecina@correo.co');
        await wrapper.get('input#support-authorization').setChecked(true);
        await button(wrapper, 'Enviarme el código para sumarme').trigger('click');
        await flushPromises();
        await wrapper.get('input#support-code').setValue('123456');
        await button(wrapper, 'Confirmar: yo también lo vi').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Usted ya informó sobre esta obra');
        expect(wrapper.get('[data-test="citizens-count"]').text()).toContain('1 ciudadano informó');
    });

    it('asks for the authorization before sending the code, and offers no way to join once the veeduría documented it', async () => {
        const wrapper = mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices() } });
        await button(wrapper, 'Yo también lo vi').trigger('click');
        await wrapper.get('input#support-email').setValue('vecino@correo.co');
        await button(wrapper, 'Enviarme el código para sumarme').trigger('click');
        expect(requestCitizenCode).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Para sumarse, autorice el tratamiento de sus datos personales.');

        const documented = mount(CitizenVoices, { props: { worksiteId: WORKSITE, citizens: voices({ count: 4, status: 'Documentado con evidencia', open: false }) } });
        expect(documented.text()).not.toContain('¿Usted también lo vio?');
        expect(button(documented, 'Yo también lo vi')).toBeUndefined();
    });
});
