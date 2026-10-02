// Iteración 46e — R-PRIV-05 reescrita: cada foto se revisa antes de adjuntarla,
// ya difuminada. El detector y el difuminado se prueban en lib/evidence; aquí,
// lo que la persona ve y decide: lo que encontró el detector, tocar la foto
// para difuminar otra zona, quitar un recuadro que no es un rostro.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PhotoReview from './PhotoReview.vue';
import { renderPreview } from '@/lib/evidence/blur.js';

vi.mock('@/lib/evidence/blur.js', async (importOriginal) => ({ ...(await importOriginal()), renderPreview: vi.fn() }));

const face = { x: 330, y: 230, width: 340, height: 340 };
const draft = (changes = {}) => ({ name: 'IMG_0002.jpg', canvas: { width: 1920, height: 1440 }, faces: [face], detector: 'ok', ...changes });
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

function review(props = {}) {
    const wrapper = mount(PhotoReview, { props: { draft: draft(), position: 1, total: 1, ...props } });
    // La foto de 1920 x 1440 se ve en 480 x 360 px, desde (20, 100).
    wrapper.get('canvas').element.getBoundingClientRect = () => ({ left: 20, top: 100, width: 480, height: 360 });
    return wrapper;
}

beforeEach(() => vi.clearAllMocks());

describe('PhotoReview', () => {
    it('Los rostros de una foto se difuminan en el celular antes de calcular su huella: says what it found, shows it blurred, and uses it as is', async () => {
        const wrapper = review({ draft: draft({ faces: [face, { ...face, x: 900 }] }), position: 2, total: 3 });

        expect(wrapper.text()).toContain('Revise la foto antes de adjuntarla (2 de 3)');
        expect(wrapper.get('[data-test="faces-found"]').text()).toBe('Encontramos 2 rostros y los difuminamos.');
        expect(renderPreview).toHaveBeenLastCalledWith(wrapper.get('canvas').element, draft().canvas, [face, { ...face, x: 900 }]);

        await button(wrapper, 'Usar esta foto').trigger('click');
        expect(wrapper.emitted('use')[0]).toEqual([{ dismissed: [], manual: [] }]);
    });

    it('says it in the singular for one face', () => {
        expect(review().get('[data-test="faces-found"]').text()).toBe('Encontramos 1 rostro y lo difuminamos.');
    });

    it('Una foto sin rostros se envía sin difuminar nada: says so, and invites to blur a person or a plate by hand', () => {
        const wrapper = review({ draft: draft({ faces: [] }) });

        expect(wrapper.get('[data-test="faces-found"]').text()).toBe('No encontramos rostros.');
        expect(wrapper.text()).toContain('Si ve a una persona, sobre todo un niño, o una placa, toque la foto sobre ella para difuminarla.');
    });

    it('Difumino a mano lo que el detector no vio: a touch blurs that zone; a touch on it again removes it', async () => {
        const wrapper = review({ draft: draft({ faces: [] }) });
        const zone = { x: 350, y: 230, width: 259, height: 259, byHand: true };

        await wrapper.get('canvas').trigger('click', { clientX: 140, clientY: 190 });
        expect(renderPreview).toHaveBeenLastCalledWith(expect.anything(), expect.anything(), [zone]);
        expect(wrapper.text()).toContain('Zonas difuminadas a mano: 1');

        await wrapper.get('canvas').trigger('click', { clientX: 145, clientY: 195 });
        expect(renderPreview).toHaveBeenLastCalledWith(expect.anything(), expect.anything(), []);

        await wrapper.get('canvas').trigger('click', { clientX: 140, clientY: 190 });
        await button(wrapper, 'Usar esta foto').trigger('click');
        expect(wrapper.emitted('use')[0]).toEqual([{ dismissed: [], manual: [zone] }]);
    });

    it('blurs a zone without touching the photo, from a grid of buttons', async () => {
        const wrapper = review({ draft: draft({ faces: [] }) });

        await button(wrapper, 'Difuminar el centro').trigger('click');
        await button(wrapper, 'Usar esta foto').trigger('click');

        expect(wrapper.emitted('use')[0][0].manual).toEqual([{ x: 830, y: 590, width: 259, height: 259, byHand: true }]);
    });

    it('Quito un recuadro que no es un rostro, y la Bandeja lo marca: does not blur it, warns that the veeduría will see it, and can undo it', async () => {
        const wrapper = review();

        await button(wrapper, 'No es un rostro').trigger('click');
        expect(renderPreview).toHaveBeenLastCalledWith(expect.anything(), expect.anything(), []);
        expect(wrapper.get('[data-test="detected-face"]').text()).toContain('La veeduría verá que quitó este difuminado.');

        await button(wrapper, 'Volver a difuminar').trigger('click');
        await button(wrapper, 'No es un rostro').trigger('click');
        await button(wrapper, 'Usar esta foto').trigger('click');
        expect(wrapper.emitted('use')[0]).toEqual([{ dismissed: [0], manual: [] }]);
    });

    it('Si el detector no carga, la foto se revisa a mano: asks to look at it and blur each person', () => {
        const wrapper = review({ draft: draft({ faces: [], detector: 'unavailable' }) });

        expect(wrapper.get('[data-test="faces-found"]').text()).toBe('No pudimos revisar si hay rostros. Mire la foto y toque a cada persona que se vea para difuminarla.');
        expect(button(wrapper, 'Usar esta foto').attributes('disabled')).toBeUndefined();
    });

    it('lets the photo go without attaching it', async () => {
        const wrapper = review();

        await button(wrapper, 'No usar esta foto').trigger('click');

        expect(wrapper.emitted('discard')).toHaveLength(1);
    });
});
