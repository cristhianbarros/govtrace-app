// Iteración 44f — US-059-LEG: informar a la veeduría, sin cuenta, con el correo
// verificado por un código. El servidor valida y limita (CitizenReportsTest);
// aquí, los dos pasos de la pantalla.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CitizenReportForm from './CitizenReportForm.vue';
import { prepareEvidence } from '@/lib/evidence/prepare.js';
import { requestCitizenCode, sendCitizenReport } from '@/services/api.js';

vi.mock('@/services/api.js');
vi.mock('@/lib/evidence/prepare.js');

const MESSAGE = 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.';
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function askForTheCode(wrapper) {
    await wrapper.get('input#citizen-email').setValue('vecina@correo.co');
    await wrapper.get('input#citizen-authorization').setValue(true);
    await button(wrapper, 'Enviarme el código').trigger('click');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    requestCitizenCode.mockResolvedValue({ message: 'Le enviamos un código de 6 dígitos a vecina@correo.co. Vence en 10 minutos.' });
});

describe('Informar a esta veeduría', () => {
    it('El ciudadano informa a la veeduría con su correo verificado: the code by mail, then the code, the message and a clean photo', async () => {
        const clean = new File(['sin-exif'], 'obra.jpg', { type: 'image/jpeg' });
        prepareEvidence.mockResolvedValue({ kind: 'photo', file: clean, sha256: 'ab'.repeat(32) });
        sendCitizenReport.mockResolvedValue({ message: 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', number: 12 });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: 7 } });

        await askForTheCode(wrapper);
        expect(requestCitizenCode).toHaveBeenCalledWith({ email: 'vecina@correo.co', worksite_id: 7, data_authorization: true });
        expect(wrapper.text()).toContain('Le enviamos un código de 6 dígitos a vecina@correo.co. Vence en 10 minutos.');

        await wrapper.get('input#citizen-code').setValue('123456');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);
        const input = wrapper.get('input#citizen-photo');
        Object.defineProperty(input.element, 'files', { value: [new File(['con-exif'], 'foto.jpg', { type: 'image/jpeg' })] });
        await input.trigger('change');
        await flushPromises();
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        const form = sendCitizenReport.mock.calls[0][0];
        expect(Object.fromEntries(['email', 'code', 'worksite_id', 'message'].map((field) => [field, form.get(field)]))).toEqual({ email: 'vecina@correo.co', code: '123456', worksite_id: '7', message: MESSAGE });
        expect(form.get('photo').name).toBe('obra.jpg');
        expect(wrapper.get('[role="status"]').text()).toContain('Su informe llegó a la veeduría.');
        expect(wrapper.text()).toContain('Su informe es el n.º 12.');
    });

    it('Sin autorizar el tratamiento de datos no se pide el código: nothing is sent, and it says why', async () => {
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: 7 } });

        await wrapper.get('input#citizen-email').setValue('vecina@correo.co');
        await button(wrapper, 'Enviarme el código').trigger('click');

        expect(requestCitizenCode).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Para informar a la veeduría, autorice el tratamiento de sus datos personales.');
        expect(wrapper.get('a[data-test="data-policy"]').attributes('href')).toBe('/privacidad');
    });

    it('asks for a message of 20 characters or more, and a code of 6 digits, before sending', async () => {
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: 7 } });
        await askForTheCode(wrapper);

        await wrapper.get('input#citizen-code').setValue('12');
        await wrapper.get('textarea#citizen-message').setValue('Muy corto');
        await button(wrapper, 'Enviar a la veeduría').trigger('click');

        expect(sendCitizenReport).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Escriba el código de 6 dígitos que le llegó al correo.');
    });

    it('Sin un código válido no se recibe el informe: it shows what the server says', async () => {
        sendCitizenReport.mockRejectedValue({ response: { status: 422, data: { message: 'El código no es válido o ya venció. Pida uno nuevo.', errors: { code: ['El código no es válido o ya venció. Pida uno nuevo.'] } } } });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: 7 } });
        await askForTheCode(wrapper);

        await wrapper.get('input#citizen-code').setValue('000000');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('El código no es válido o ya venció. Pida uno nuevo.');
        expect(button(wrapper, 'Pedir otro código')).toBeTruthy();
    });
});
