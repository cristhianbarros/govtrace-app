// Iteración 16 — US-009 (UI): adjuntar de 1 a 5 fotos o un PDF. Cada archivo
// se prepara en el teléfono (optimizado o limpio, y con su SHA-256) antes de
// quedar adjunto; aquí la preparación es un doble, probada en lib/evidence.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CameraCapture from './CameraCapture.vue';
import EvidencePicker from './EvidencePicker.vue';
import PhotoReview from './PhotoReview.vue';
import { draftPhoto, finishPhoto, prepareEvidence } from '@/lib/evidence/prepare.js';

vi.mock('@/lib/evidence/prepare.js', async (importOriginal) => ({
    ...(await importOriginal()),
    prepareEvidence: vi.fn(),
    draftPhoto: vi.fn(),
    finishPhoto: vi.fn(),
}));
vi.mock('@/lib/evidence/blur.js', async (importOriginal) => ({ ...(await importOriginal()), renderPreview: vi.fn() }));

/** It. 46e: each photo is reviewed before it is attached; this one is used as the detector left it. */
async function useEachPhoto(wrapper) {
    while (wrapper.findComponent(PhotoReview).exists()) {
        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
    }
}

const photo = (n) => new File([`foto ${n}`], `foto${n}.heic`, { type: 'image/heic' });
const pdf = () => new File(['%PDF'], 'acta.pdf', { type: 'application/pdf' });

function mountPicker() {
    const wrapper = mount(EvidencePicker, {
        props: { modelValue: [], 'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }) },
    });
    return wrapper;
}

async function choose(wrapper, files) {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: files, configurable: true });
    await input.trigger('change');
    await flushPromises();
}

beforeEach(() => {
    draftPhoto.mockReset();
    finishPhoto.mockReset();
    draftPhoto.mockImplementation(async (file) => ({ name: file.name.replace(/\.heic$/, '.jpg'), canvas: {}, faces: [], detector: 'ok' }));
    finishPhoto.mockImplementation(async (draft) => ({
        kind: 'photo',
        file: new File([draft.name], draft.name, { type: 'image/jpeg' }),
        sha256: 'ab'.repeat(32),
        blurs: { faces: 0, dismissed: 0, manual: 0 },
    }));
    prepareEvidence.mockReset();
    prepareEvidence.mockImplementation(async (file) => ({
        kind: file.type === 'application/pdf' ? 'pdf' : 'photo',
        file: new File([file.name], file.name.replace(/\.heic$/, '.jpg'), { type: file.type === 'application/pdf' ? file.type : 'image/jpeg' }),
        sha256: 'ab'.repeat(32),
    }));
});

describe('EvidencePicker', () => {
    it('attaches up to 5 photos, prepared on the phone, and rejects the 6th', async () => {
        const wrapper = mountPicker();

        await choose(wrapper, [1, 2, 3, 4, 5, 6].map(photo));
        await useEachPhoto(wrapper);

        expect(wrapper.props('modelValue')).toHaveLength(5);
        expect(wrapper.props('modelValue')[0]).toMatchObject({ kind: 'photo', sha256: 'ab'.repeat(32) });
        expect(draftPhoto).toHaveBeenCalledTimes(5);
        expect(finishPhoto).toHaveBeenCalledTimes(5);
        expect(wrapper.text()).toContain('Un reporte admite máximo 5 fotos.');
    });

    it('La app no permite mezclar fotos y PDF: after 2 photos, a PDF is not added', async () => {
        const wrapper = mountPicker();
        await choose(wrapper, [photo(1), photo(2)]);
        await useEachPhoto(wrapper);

        expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('image/*');

        await choose(wrapper, [pdf()]);

        expect(wrapper.props('modelValue').map((evidence) => evidence.kind)).toEqual(['photo', 'photo']);
        expect(wrapper.text()).toContain('Un reporte lleva de 1 a 5 fotos o un único PDF; no se pueden mezclar.');
    });

    it('takes no more files once a PDF is attached', async () => {
        const wrapper = mountPicker();
        expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('image/*,application/pdf');

        await choose(wrapper, [pdf()]);

        expect(wrapper.props('modelValue')).toHaveLength(1);
        expect(wrapper.get('input[type="file"]').attributes('disabled')).toBeDefined();
    });

    it('No se aceptan videos: a video is rejected without processing it', async () => {
        const wrapper = mountPicker();

        await choose(wrapper, [new File(['video'], 'obra.mp4', { type: 'video/mp4' })]);

        expect(wrapper.props('modelValue')).toEqual([]);
        expect(prepareEvidence).not.toHaveBeenCalled();
        expect(draftPhoto).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Solo se aceptan fotos en JPEG o un documento PDF; los videos y otros archivos no están permitidos.');
    });

    it('rejects a file that still weighs more than 10 MB once prepared', async () => {
        prepareEvidence.mockResolvedValueOnce({ kind: 'pdf', file: { name: 'acta.pdf', size: Math.round(10.1 * 1024 * 1024) }, sha256: 'cd'.repeat(32) });
        const wrapper = mountPicker();

        await choose(wrapper, [pdf()]);

        expect(wrapper.props('modelValue')).toEqual([]);
        expect(wrapper.text()).toContain('Cada archivo puede pesar máximo 10 MB.');
    });

    it('lets the veedor remove an attached file', async () => {
        const wrapper = mountPicker();
        await choose(wrapper, [photo(1), photo(2)]);
        await useEachPhoto(wrapper);

        await wrapper.findAll('[data-test="remove-evidence"]')[0].trigger('click');

        expect(wrapper.props('modelValue').map((evidence) => evidence.file.name)).toEqual(['foto2.jpg']);
    });
});

// It. 62c: donde el navegador da la cámara, "Tomar foto" la abre en la página.
describe('La foto se toma dentro de GovTrace (it. 62c)', () => {
    function withCameraInPage() {
        vi.stubGlobal('isSecureContext', true);
        Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia: vi.fn() }, configurable: true });
    }
    afterEach(() => {
        vi.unstubAllGlobals();
        delete navigator.mediaDevices;
    });

    it('La foto se toma dentro de GovTrace, sin salir de la app: "Tomar foto" opens the camera in the page, and its photo goes to review like one from the gallery', async () => {
        withCameraInPage();
        const wrapper = mount(EvidencePicker, {
            props: { modelValue: [], photosOnly: true, max: 3 },
            global: { stubs: { CameraCapture: { template: '<div data-test="camera-view" />', emits: ['photo', 'close'] } } },
        });

        expect(wrapper.find('input[data-test="camera"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="camera-view"]').exists()).toBe(false);
        await wrapper.get('button[data-test="take-photo"]').trigger('click');
        expect(wrapper.find('[data-test="camera-view"]').exists()).toBe(true);

        const taken = new File(['jpeg'], 'foto-14-05-33.jpg', { type: 'image/jpeg' });
        wrapper.findComponent(CameraCapture).vm.$emit('photo', taken);
        await flushPromises();

        expect(wrapper.find('[data-test="camera-view"]').exists()).toBe(false);
        expect(draftPhoto).toHaveBeenCalledWith(taken);
        expect(wrapper.findComponent(PhotoReview).exists()).toBe(true);
    });

    it('closes the camera without a photo, and keeps "Tomar foto" off while a photo is being reviewed', async () => {
        withCameraInPage();
        const wrapper = mount(EvidencePicker, {
            props: { modelValue: [] },
            global: { stubs: { CameraCapture: { template: '<div data-test="camera-view" />', emits: ['photo', 'close'] } } },
        });

        await wrapper.get('button[data-test="take-photo"]').trigger('click');
        wrapper.findComponent(CameraCapture).vm.$emit('close');
        await flushPromises();
        expect(wrapper.find('[data-test="camera-view"]').exists()).toBe(false);

        await choose(wrapper, [photo(1)]);
        expect(wrapper.get('button[data-test="take-photo"]').attributes('disabled')).toBeDefined();
    });
});

describe('Tomar la foto o elegirla (it. 40d)', () => {
    it('Tomar la foto con la cámara o elegirla de la galería: two big buttons, the camera one opens the rear camera', async () => {
        const wrapper = mount(EvidencePicker, { props: { modelValue: [] } });

        const camera = wrapper.get('input[data-test="camera"]');
        expect(camera.attributes('capture')).toBe('environment');
        expect(camera.attributes('accept')).toBe('image/*');
        expect(wrapper.text()).toContain('Tomar foto');
        expect(wrapper.text()).toContain('Elegir de la galería o un PDF');
        expect(wrapper.get('input[type="file"]').attributes('capture')).toBeUndefined();
    });
});

// It. 46e — R-PRIV-05 reescrita: los rostros se difuminan en el celular, antes de la huella.
describe('Los rostros, difuminados antes de adjuntar (it. 46e)', () => {
    it('warns, next to the buttons, that the faces are blurred before sending', () => {
        const wrapper = mountPicker();

        expect(wrapper.get('[data-test="faces-warning"]').text()).toBe('Si en la foto aparecen personas, sobre todo niños, sus rostros se difuminan antes de enviarla.');
    });

    it('attaches each photo only after its review, one at a time, with what was blurred', async () => {
        finishPhoto.mockImplementation(async (draft, review) => ({
            kind: 'photo',
            file: new File([draft.name], draft.name, { type: 'image/jpeg' }),
            sha256: 'ab'.repeat(32),
            blurs: { faces: 1, dismissed: review.dismissed.length, manual: review.manual.length },
        }));
        const wrapper = mountPicker();

        await choose(wrapper, [photo(1), photo(2)]);
        expect(wrapper.props('modelValue')).toEqual([]);
        expect(wrapper.findComponent(PhotoReview).props()).toMatchObject({ position: 1, total: 2 });

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [0], manual: [] });
        await flushPromises();
        expect(wrapper.findComponent(PhotoReview).props()).toMatchObject({ position: 2, total: 2 });
        expect(finishPhoto).toHaveBeenCalledWith(expect.objectContaining({ name: 'foto1.jpg' }), { dismissed: [0], manual: [] });

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        expect(wrapper.findComponent(PhotoReview).exists()).toBe(false);
        expect(wrapper.props('modelValue').map((evidence) => evidence.blurs)).toEqual([
            { faces: 1, dismissed: 1, manual: 0 },
            { faces: 1, dismissed: 0, manual: 0 },
        ]);
    });

    it('does not attach a photo the veedor chose not to use', async () => {
        const wrapper = mountPicker();

        await choose(wrapper, [photo(1)]);
        wrapper.findComponent(PhotoReview).vm.$emit('discard');
        await flushPromises();

        expect(wrapper.props('modelValue')).toEqual([]);
        expect(finishPhoto).not.toHaveBeenCalled();
    });

    it('attaches a photo once, even with a double tap on "Usar esta foto", and still reviews the next one', async () => {
        let finish;
        finishPhoto.mockImplementation((draft) => new Promise((resolve) => (finish = () => resolve({ kind: 'photo', file: new File([draft.name], draft.name), sha256: 'ab'.repeat(32), blurs: null }))));
        const wrapper = mountPicker();
        await choose(wrapper, [photo(1), photo(2)]);

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        finish();
        await flushPromises();

        expect(finishPhoto).toHaveBeenCalledTimes(1);
        expect(wrapper.props('modelValue')).toHaveLength(1);
        expect(wrapper.findComponent(PhotoReview).props()).toMatchObject({ position: 2, total: 2 });
    });

    // It. 62b: cada foto revisada ocupa unos 11 MB en su canvas. Se suelta apenas se usa
    // o se descarta: en el iPhone, Safari no libera un canvas hasta que se encoge.
    it('lets the canvas of each reviewed photo go, once used or discarded', async () => {
        const canvases = [];
        draftPhoto.mockImplementation(async (file) => {
            canvases.push({ width: 1920, height: 1440 });
            return { name: file.name, canvas: canvases.at(-1), faces: [], detector: 'ok' };
        });
        const wrapper = mountPicker();
        await choose(wrapper, [photo(1), photo(2)]);

        wrapper.findComponent(PhotoReview).vm.$emit('use', { dismissed: [], manual: [] });
        await flushPromises();
        expect(canvases[0]).toEqual({ width: 0, height: 0 });
        expect(canvases[1]).toEqual({ width: 1920, height: 1440 });

        wrapper.findComponent(PhotoReview).vm.$emit('discard');
        await flushPromises();
        expect(canvases[1]).toEqual({ width: 0, height: 0 });
    });
});

// It. 46h (US-059-LEG): el mismo selector para el ciudadano — de 1 a 3 fotos, sin PDF.
describe('El selector del ciudadano (it. 46h)', () => {
    const citizenPicker = () => {
        const wrapper = mount(EvidencePicker, {
            props: { modelValue: [], max: 3, photosOnly: true, label: 'Fotos (opcional): hasta 3', 'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }) },
        });
        return wrapper;
    };

    it('El ciudadano tiene las mismas opciones del veedor: the camera and the gallery, photos only, with its own label', () => {
        const wrapper = citizenPicker();

        expect(wrapper.text()).toContain('Fotos (opcional): hasta 3');
        expect(wrapper.text()).toContain('Tomar foto');
        expect(wrapper.text()).toContain('Elegir de la galería');
        expect(wrapper.text()).not.toContain('PDF');
        expect(wrapper.get('input[data-test="camera"]').attributes('capture')).toBe('environment');
        expect(wrapper.get('input[type="file"]:not([data-test])').attributes('accept')).toBe('image/*');
        expect(wrapper.find('[data-test="faces-warning"]').exists()).toBe(true);
    });

    it('attaches up to 3 reviewed photos, and refuses the 4th', async () => {
        const wrapper = citizenPicker();

        await choose(wrapper, [1, 2, 3, 4].map(photo));
        await useEachPhoto(wrapper);

        expect(wrapper.props('modelValue')).toHaveLength(3);
        expect(draftPhoto).toHaveBeenCalledTimes(3);
        expect(wrapper.text()).toContain('Un informe admite máximo 3 fotos.');
        expect(wrapper.get('input[data-test="camera"]').attributes('disabled')).toBeDefined();
    });

    it('refuses a PDF', async () => {
        const wrapper = citizenPicker();

        await choose(wrapper, [pdf()]);

        expect(wrapper.props('modelValue')).toEqual([]);
        expect(prepareEvidence).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Solo se aceptan fotos en JPEG; los PDF, los videos y otros archivos no están permitidos aquí.');
    });
});

