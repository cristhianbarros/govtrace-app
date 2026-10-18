// It. 62c — US-009: la foto se toma en la página, con la cámara del navegador,
// sin abrir la app de la cámara del teléfono (que dejaba a GovTrace detrás, y un
// celular con poca memoria la cerraba).
import { describe, expect, it, vi } from 'vitest';
import { CAMERA_CONSTRAINTS, cameraInPage, grabPhoto, openCamera, stopCamera } from './camera.js';

function fakeCanvas() {
    const context = { drawImage: vi.fn() };
    return {
        width: 0,
        height: 0,
        context,
        sizes: [],
        getContext: () => context,
        toBlob(done, type, quality) {
            this.sizes.push([this.width, this.height, type, quality]);
            done(new Blob(['jpeg'], { type }));
        },
    };
}

describe('La cámara en la página (it. 62c)', () => {
    it('is used only where the browser gives the camera: with HTTPS and getUserMedia', () => {
        const withCamera = { mediaDevices: { getUserMedia: vi.fn() } };

        expect(cameraInPage(withCamera, true)).toBe(true);
        expect(cameraInPage(withCamera, false)).toBe(false);
        expect(cameraInPage({}, true)).toBe(false);
        expect(cameraInPage(undefined, true)).toBe(false);
    });

    it('opens the rear camera, without the microphone, near the 1920 px that are kept', async () => {
        const stream = { getTracks: () => [] };
        const getUserMedia = vi.fn().mockResolvedValue(stream);

        expect(await openCamera({ mediaDevices: { getUserMedia } })).toBe(stream);
        expect(getUserMedia).toHaveBeenCalledWith(CAMERA_CONSTRAINTS);
        expect(CAMERA_CONSTRAINTS).toEqual({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1440 } } });
    });

    it('stops every track of the camera, and does nothing without one', () => {
        const tracks = [{ stop: vi.fn() }, { stop: vi.fn() }];

        stopCamera({ getTracks: () => tracks });
        stopCamera(null);

        expect(tracks.every((track) => track.stop.mock.calls.length === 1)).toBe(true);
    });

    it('grabs the frame of the video as a JPEG, at its size and never over 1920 px, and releases the canvas', async () => {
        const portrait = fakeCanvas();
        const photo = await grabPhoto({ videoWidth: 1440, videoHeight: 1920 }, { createCanvas: () => portrait, now: () => new Date('2026-10-18T14:05:33').getTime() });

        expect(photo).toBeInstanceOf(File);
        expect(photo.type).toBe('image/jpeg');
        expect(photo.name).toBe('foto-14-05-33.jpg');
        expect(portrait.sizes).toEqual([[1440, 1920, 'image/jpeg', 0.92]]);
        expect(portrait.context.drawImage).toHaveBeenCalledWith({ videoWidth: 1440, videoHeight: 1920 }, 0, 0, 1440, 1920);
        expect([portrait.width, portrait.height]).toEqual([0, 0]);

        const wide = fakeCanvas();
        await grabPhoto({ videoWidth: 3840, videoHeight: 2160 }, { createCanvas: () => wide });
        expect(wide.sizes[0].slice(0, 2)).toEqual([1920, 1080]);
    });

    it('refuses a video that has no frame yet', async () => {
        await expect(grabPhoto({ videoWidth: 0, videoHeight: 0 }, { createCanvas: fakeCanvas })).rejects.toThrow('La cámara todavía no muestra la imagen. Intente de nuevo.');
    });
});
