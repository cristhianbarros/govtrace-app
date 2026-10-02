// Iteración 44f — US-059-LEG: informar a la veeduría, sin cuenta, con el correo
// verificado por un código. El servidor valida y limita (CitizenReportsTest);
// aquí, los dos pasos de la pantalla.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CitizenReportForm from './CitizenReportForm.vue';
import PhotoReview from '@/Components/PhotoReview.vue';
import { draftPhoto, finishPhoto } from '@/lib/evidence/prepare.js';
import { requestCitizenCode, sendCitizenReport } from '@/services/api.js';

vi.mock('@/services/api.js');
vi.mock('@/lib/evidence/prepare.js');
vi.mock('@/lib/evidence/blur.js', async (importOriginal) => ({ ...(await importOriginal()), renderPreview: vi.fn() }));

// It. 46c (US-064-SEC): la obra, por su identificador público.
const WORKSITE = '01j9xq3m7v8k2d4f6g8h0jkmnp';

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
        draftPhoto.mockResolvedValue({ name: 'obra.jpg', canvas: {}, faces: [], detector: 'ok' });
        finishPhoto.mockResolvedValue({ kind: 'photo', file: clean, sha256: 'ab'.repeat(32), blurs: { faces: 0, dismissed: 0, manual: 0 } });
        sendCitizenReport.mockResolvedValue({ message: 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', number: 12 });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });

        await askForTheCode(wrapper);
        expect(requestCitizenCode).toHaveBeenCalledWith({ email: 'vecina@correo.co', worksite_id: WORKSITE, data_authorization: true });
        expect(wrapper.text()).toContain('Le enviamos un código de 6 dígitos a vecina@correo.co. Vence en 10 minutos.');

        await wrapper.get('input#citizen-code').setValue('123456');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);
        const input = wrapper.get('input#citizen-photo');
        Object.defineProperty(input.element, 'files', { value: [new File(['con-exif'], 'foto.jpg', { type: 'image/jpeg' })] });
        await input.trigger('change');
        await flushPromises();
        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        const form = sendCitizenReport.mock.calls[0][0];
        expect(Object.fromEntries(['email', 'code', 'worksite_id', 'message'].map((field) => [field, form.get(field)]))).toEqual({ email: 'vecina@correo.co', code: '123456', worksite_id: WORKSITE, message: MESSAGE });
        expect(form.get('photo').name).toBe('obra.jpg');
        expect(wrapper.get('[role="status"]').text()).toContain('Su informe llegó a la veeduría.');
        expect(wrapper.text()).toContain('Su informe es el n.º 12.');
    });

    it('Sin autorizar el tratamiento de datos no se pide el código: nothing is sent, and it says why', async () => {
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });

        await wrapper.get('input#citizen-email').setValue('vecina@correo.co');
        await button(wrapper, 'Enviarme el código').trigger('click');

        expect(requestCitizenCode).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Para informar a la veeduría, autorice el tratamiento de sus datos personales.');
        expect(wrapper.get('a[data-test="data-policy"]').attributes('href')).toBe('/privacidad');
    });

    it('asks for a message of 20 characters or more, and a code of 6 digits, before sending', async () => {
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);

        await wrapper.get('input#citizen-code').setValue('12');
        await wrapper.get('textarea#citizen-message').setValue('Muy corto');
        await button(wrapper, 'Enviar a la veeduría').trigger('click');

        expect(sendCitizenReport).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Escriba el código de 6 dígitos que le llegó al correo.');
    });

    it('Sin un código válido no se recibe el informe: it shows what the server says', async () => {
        sendCitizenReport.mockRejectedValue({ response: { status: 422, data: { message: 'El código no es válido o ya venció. Pida uno nuevo.', errors: { code: ['El código no es válido o ya venció. Pida uno nuevo.'] } } } });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);

        await wrapper.get('input#citizen-code').setValue('000000');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('El código no es válido o ya venció. Pida uno nuevo.');
        expect(button(wrapper, 'Pedir otro código')).toBeTruthy();
    });
});

// It. 46e — R-PRIV-05 reescrita: la foto del informe pasa por la misma revisión.
describe('La foto del informe, difuminada (it. 46e)', () => {
    async function attach(wrapper) {
        const input = wrapper.get('input#citizen-photo');
        Object.defineProperty(input.element, 'files', { value: [new File(['con-rostro'], 'vecinos.jpg', { type: 'image/jpeg' })] });
        await input.trigger('change');
        await flushPromises();
    }

    it('Los rostros de la foto del informe ciudadano se difuminan en el celular: the citizen reviews it, already blurred, before sending', async () => {
        const blurred = new File(['difuminada'], 'vecinos.jpg', { type: 'image/jpeg' });
        draftPhoto.mockResolvedValue({ name: 'vecinos.jpg', canvas: {}, faces: [{ x: 1, y: 1, width: 100, height: 100 }], detector: 'ok' });
        finishPhoto.mockResolvedValue({ kind: 'photo', file: blurred, sha256: 'ab'.repeat(32), blurs: { faces: 1, dismissed: 0, manual: 0 } });
        sendCitizenReport.mockResolvedValue({ message: 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', number: '7KQ3-M9XD' });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);
        await wrapper.get('input#citizen-code').setValue('123456');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);

        await attach(wrapper);
        expect(wrapper.findComponent(PhotoReview).props('draft').faces).toHaveLength(1);
        expect(button(wrapper, 'Enviar a la veeduría').attributes('disabled')).toBeDefined();

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        expect(wrapper.findComponent(PhotoReview).exists()).toBe(false);
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(sendCitizenReport.mock.calls[0][0].get('photo')).toBe(blurred);
    });

    it('sends no photo when the citizen chose not to use it', async () => {
        draftPhoto.mockResolvedValue({ name: 'vecinos.jpg', canvas: {}, faces: [], detector: 'ok' });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);

        await attach(wrapper);
        wrapper.findComponent(PhotoReview).vm.$emit('discard');
        await flushPromises();

        expect(wrapper.findComponent(PhotoReview).exists()).toBe(false);
        expect(finishPhoto).not.toHaveBeenCalled();
        expect(wrapper.get('input#citizen-photo').element.value).toBe('');
    });
});
