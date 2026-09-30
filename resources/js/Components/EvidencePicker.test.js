// Iteración 16 — US-009 (UI): adjuntar de 1 a 5 fotos o un PDF. Cada archivo
// se prepara en el teléfono (optimizado o limpio, y con su SHA-256) antes de
// quedar adjunto; aquí la preparación es un doble, probada en lib/evidence.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import EvidencePicker from './EvidencePicker.vue';
import { prepareEvidence } from '@/lib/evidence/prepare.js';

vi.mock('@/lib/evidence/prepare.js', async (importOriginal) => ({
    ...(await importOriginal()),
    prepareEvidence: vi.fn(),
}));

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

        expect(wrapper.props('modelValue')).toHaveLength(5);
        expect(wrapper.props('modelValue')[0]).toMatchObject({ kind: 'photo', sha256: 'ab'.repeat(32) });
        expect(prepareEvidence).toHaveBeenCalledTimes(5);
        expect(wrapper.text()).toContain('Un reporte admite máximo 5 fotos.');
    });

    it('La app no permite mezclar fotos y PDF: after 2 photos, a PDF is not added', async () => {
        const wrapper = mountPicker();
        await choose(wrapper, [photo(1), photo(2)]);

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

        await wrapper.findAll('[data-test="remove-evidence"]')[0].trigger('click');

        expect(wrapper.props('modelValue').map((evidence) => evidence.file.name)).toEqual(['foto2.jpg']);
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
