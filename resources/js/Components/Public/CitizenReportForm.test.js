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
vi.mock('@/lib/evidence/prepare.js', async (importOriginal) => ({ ...(await importOriginal()), draftPhoto: vi.fn(), finishPhoto: vi.fn(), prepareEvidence: vi.fn() }));
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

/** Elegir fotos de la galería, en el selector que usa también el veedor (it. 46h). */
async function attach(wrapper, files) {
    const input = wrapper.get('input[type="file"]:not([data-test])');
    Object.defineProperty(input.element, 'files', { value: files, configurable: true });
    await input.trigger('change');
    await flushPromises();
}

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
        await attach(wrapper, [new File(['con-exif'], 'foto.jpg', { type: 'image/jpeg' })]);
        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        const form = sendCitizenReport.mock.calls[0][0];
        expect(Object.fromEntries(['email', 'code', 'worksite_id', 'message'].map((field) => [field, form.get(field)]))).toEqual({ email: 'vecina@correo.co', code: '123456', worksite_id: WORKSITE, message: MESSAGE });
        expect(form.getAll('photos[]').map((photo) => photo.name)).toEqual(['obra.jpg']);
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
    const attachOne = (wrapper) => attach(wrapper, [new File(['con-rostro'], 'vecinos.jpg', { type: 'image/jpeg' })]);

    it('Los rostros de la foto del informe ciudadano se difuminan en el celular: the citizen reviews it, already blurred, before sending', async () => {
        const blurred = new File(['difuminada'], 'vecinos.jpg', { type: 'image/jpeg' });
        draftPhoto.mockResolvedValue({ name: 'vecinos.jpg', canvas: {}, faces: [{ x: 1, y: 1, width: 100, height: 100 }], detector: 'ok' });
        finishPhoto.mockResolvedValue({ kind: 'photo', file: blurred, sha256: 'ab'.repeat(32), blurs: { faces: 1, dismissed: 0, manual: 0 } });
        sendCitizenReport.mockResolvedValue({ message: 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', number: '7KQ3-M9XD' });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);
        await wrapper.get('input#citizen-code').setValue('123456');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);

        await attachOne(wrapper);
        expect(wrapper.findComponent(PhotoReview).props('draft').faces).toHaveLength(1);
        expect(button(wrapper, 'Enviar a la veeduría').attributes('disabled')).toBeDefined();

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        expect(wrapper.findComponent(PhotoReview).exists()).toBe(false);
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(sendCitizenReport.mock.calls[0][0].getAll('photos[]')).toEqual([blurred]);
    });

    it('sends no photo when the citizen chose not to use it', async () => {
        draftPhoto.mockResolvedValue({ name: 'vecinos.jpg', canvas: {}, faces: [], detector: 'ok' });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);

        await attachOne(wrapper);
        wrapper.findComponent(PhotoReview).vm.$emit('discard');
        await flushPromises();

        expect(wrapper.findComponent(PhotoReview).exists()).toBe(false);
        expect(finishPhoto).not.toHaveBeenCalled();
    });
});

// It. 46h (US-059-LEG): de 1 a 3 fotos, con las mismas opciones del veedor.
describe('Las fotos del informe, de 1 a 3 (it. 46h)', () => {
    const named = (n) => new File([`foto ${n}`], `obra${n}.jpg`, { type: 'image/jpeg' });

    async function onTheCodeStep() {
        draftPhoto.mockImplementation(async (file) => ({ name: file.name, canvas: {}, faces: [], detector: 'ok' }));
        finishPhoto.mockImplementation(async (draft) => ({ kind: 'photo', file: new File([draft.name], draft.name, { type: 'image/jpeg' }), sha256: 'ab'.repeat(32), blurs: null }));
        sendCitizenReport.mockResolvedValue({ message: 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', number: '7KQ3-M9XD' });
        const wrapper = mount(CitizenReportForm, { props: { worksiteId: WORKSITE } });
        await askForTheCode(wrapper);
        await wrapper.get('input#citizen-code').setValue('123456');
        await wrapper.get('textarea#citizen-message').setValue(MESSAGE);

        return wrapper;
    }

    it('El ciudadano tiene las mismas opciones del veedor: the camera and the gallery, up to 3 photos, no PDF', async () => {
        const wrapper = await onTheCodeStep();

        expect(wrapper.text()).toContain('Fotos (opcional): hasta 3');
        expect(wrapper.text()).toContain('Tomar foto');
        expect(wrapper.text()).toContain('Elegir de la galería');
        expect(wrapper.get('input[data-test="camera"]').attributes('capture')).toBe('environment');
        expect(wrapper.text()).not.toContain('PDF');
    });

    it('El ciudadano adjunta de 1 a 3 fotos: reviews each one, and sends all three in order', async () => {
        const wrapper = await onTheCodeStep();

        await attach(wrapper, [named(1), named(2), named(3), named(4)]);
        for (const position of [1, 2, 3]) {
            expect(wrapper.findComponent(PhotoReview).props()).toMatchObject({ position, total: 3 });
            expect(button(wrapper, 'Enviar a la veeduría').attributes('disabled')).toBeDefined();
            wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
            await flushPromises();
        }
        expect(wrapper.text()).toContain('Un informe admite máximo 3 fotos.');
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(sendCitizenReport.mock.calls[0][0].getAll('photos[]').map((photo) => photo.name)).toEqual(['obra1.jpg', 'obra2.jpg', 'obra3.jpg']);
    });

    it('sends the report without photos when none was attached', async () => {
        const wrapper = await onTheCodeStep();

        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(sendCitizenReport.mock.calls[0][0].getAll('photos[]')).toEqual([]);
    });

    it('lets the citizen remove an attached photo before sending', async () => {
        const wrapper = await onTheCodeStep();
        await attach(wrapper, [named(1), named(2)]);
        for (const ignored of [1, 2]) {
            wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
            await flushPromises();
        }

        await wrapper.findAll('[data-test="remove-evidence"]')[0].trigger('click');
        await button(wrapper, 'Enviar a la veeduría').trigger('click');
        await flushPromises();

        expect(sendCitizenReport.mock.calls[0][0].getAll('photos[]').map((photo) => photo.name)).toEqual(['obra2.jpg']);
    });
});

