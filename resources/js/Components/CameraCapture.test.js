// It. 62c — US-009: "Tomar foto" abre la cámara trasera dentro de GovTrace. Con la
// app de la cámara del teléfono al frente, un celular con poca memoria cerraba
// GovTrace y, al volver, se reiniciaba sin la foto ni lo escrito.
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CameraCapture from './CameraCapture.vue';
import { grabPhoto, openCamera } from '@/lib/evidence/camera.js';

vi.mock('@/lib/evidence/camera.js', async (importOriginal) => ({ ...(await importOriginal()), openCamera: vi.fn(), grabPhoto: vi.fn() }));

const track = () => ({ stop: vi.fn() });
let tracks;
let back;

// Se abre encima de todo, en el body (Teleport).
function mountCamera() {
    return mount(CameraCapture, { attachTo: document.body });
}
const get = (selector) => new DOMWrapper(document.body.querySelector(selector));
const has = (selector) => document.body.querySelector(selector) !== null;
const shown = () => document.body.textContent;

beforeEach(() => {
    tracks = [track()];
    openCamera.mockReset().mockResolvedValue({ getTracks: () => tracks });
    grabPhoto.mockReset().mockResolvedValue(new File(['jpeg'], 'foto-14-05-33.jpg', { type: 'image/jpeg' }));
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
    history.replaceState({ page: 'obra' }, '', '/worksite/abc');
    // El "atrás" de jsdom llega en otra tarea: aquí, en el acto, como lo oye la página.
    back = vi.spyOn(history, 'back').mockImplementation(() => window.dispatchEvent(new PopStateEvent('popstate', { state: history.state })));
});
afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('La cámara dentro de GovTrace (it. 62c)', () => {
    it('La foto se toma dentro de GovTrace, sin salir de la app: the rear camera shows in the page, and "Tomar la foto" stops it and hands the photo over', async () => {
        const wrapper = mountCamera();
        expect(get('[role="dialog"]').attributes('aria-label')).toBe('Tomar foto');
        expect(shown()).toContain('Abriendo la cámara…');
        expect(get('[data-test="shutter"]').attributes('disabled')).toBeDefined();

        await flushPromises();
        expect(openCamera).toHaveBeenCalledTimes(1);
        expect(get('video').element.srcObject.getTracks()).toBe(tracks);
        expect(shown()).not.toContain('Abriendo la cámara…');
        expect(document.activeElement).toBe(get('[data-test="shutter"]').element);

        await get('[data-test="shutter"]').trigger('click');
        await flushPromises();

        expect(grabPhoto).toHaveBeenCalledWith(get('video').element);
        expect(tracks[0].stop).toHaveBeenCalled();
        expect(wrapper.emitted('photo')[0][0].name).toBe('foto-14-05-33.jpg');
        expect(has('input[type="file"]')).toBe(false);
        wrapper.unmount();
    });

    it('"Cancelar" closes it, and the camera goes off when it closes', async () => {
        const wrapper = mountCamera();
        await flushPromises();

        await get('[data-test="camera-cancel"]').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(1);

        wrapper.unmount();
        expect(tracks[0].stop).toHaveBeenCalled();
    });

    it('a camera that opens after it was closed goes off at once', async () => {
        let open;
        openCamera.mockReturnValue(new Promise((resolve) => (open = resolve)));
        const wrapper = mountCamera();
        wrapper.unmount();

        open({ getTracks: () => tracks });
        await flushPromises();

        expect(tracks[0].stop).toHaveBeenCalled();
    });

    it('el botón "atrás" del teléfono cierra la cámara sin salir del formulario: the page never hears it', async () => {
        const pageListener = vi.fn();
        window.addEventListener('popstate', pageListener);
        const wrapper = mountCamera();
        await flushPromises();
        expect(history.length).toBeGreaterThan(1);
        expect(history.state).toEqual({ page: 'obra' });

        window.dispatchEvent(new PopStateEvent('popstate', { state: { page: 'obra' } }));

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(pageListener).not.toHaveBeenCalled();
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();
        window.removeEventListener('popstate', pageListener);
    });

    it('closed with its buttons, it takes back its step in the history, and the page does not hear that either', async () => {
        const pageListener = vi.fn();
        window.addEventListener('popstate', pageListener);
        const wrapper = mountCamera();
        await flushPromises();

        wrapper.unmount();

        expect(back).toHaveBeenCalledTimes(1);
        expect(pageListener).not.toHaveBeenCalled();
        window.dispatchEvent(new PopStateEvent('popstate', { state: null }));
        expect(pageListener).toHaveBeenCalledTimes(1);
        window.removeEventListener('popstate', pageListener);
    });

    it('si el navegador no da la cámara, puedo abrir la cámara del teléfono, como antes', async () => {
        openCamera.mockRejectedValue(new DOMException('Permission denied', 'NotAllowedError'));
        const wrapper = mountCamera();
        await flushPromises();

        expect(get('[role="alert"]').text()).toContain('No pudimos abrir la cámara aquí.');
        const phoneCamera = get('input[data-test="phone-camera"]');
        expect(phoneCamera.attributes('capture')).toBe('environment');
        expect(phoneCamera.attributes('accept')).toBe('image/*');

        const photo = new File(['jpeg'], 'IMG_0001.jpg', { type: 'image/jpeg' });
        Object.defineProperty(phoneCamera.element, 'files', { value: [photo] });
        await phoneCamera.trigger('change');

        expect(wrapper.emitted('photo')[0][0]).toBe(photo);
        wrapper.unmount();
    });

    it('a frame that cannot be grabbed says so, and lets the person try again', async () => {
        grabPhoto.mockRejectedValueOnce(new Error('La cámara todavía no muestra la imagen. Intente de nuevo.'));
        const wrapper = mountCamera();
        await flushPromises();

        await get('[data-test="shutter"]').trigger('click');
        await flushPromises();

        expect(get('[role="alert"]').text()).toBe('La cámara todavía no muestra la imagen. Intente de nuevo.');
        expect(get('[data-test="shutter"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.emitted('photo')).toBeUndefined();
        wrapper.unmount();
    });
});
