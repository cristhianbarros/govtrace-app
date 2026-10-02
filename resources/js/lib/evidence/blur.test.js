// @vitest-environment node
// Iteración 46e — R-PRIV-05: cada zona se difumina hasta no reconocerse: se
// encoge a unos pocos píxeles y se estira de vuelta. Dibujar es del navegador;
// aquí se prueba lo que se le pide.
import { describe, expect, it } from 'vitest';
import { BLUR_PIXELS, blurZones, pointOnImage, zoneAround } from './blur.js';

function fakeCanvas(width, height) {
    const calls = [];
    const canvas = { width, height, calls };
    canvas.getContext = () => ({
        set imageSmoothingEnabled(value) {
            calls.push(['smoothing', value]);
        },
        drawImage: (...args) => calls.push(['drawImage', ...args]),
    });
    return canvas;
}

describe('blurZones', () => {
    it('shrinks each zone to a few pixels and stretches it back over itself', () => {
        const photo = fakeCanvas(1920, 1440);
        const tiny = [];
        const createCanvas = () => {
            const canvas = fakeCanvas(0, 0);
            tiny.push(canvas);
            return canvas;
        };

        blurZones(photo, [{ x: 330, y: 230, width: 340, height: 170 }], { createCanvas });

        expect(tiny).toHaveLength(1);
        expect([tiny[0].width, tiny[0].height]).toEqual([BLUR_PIXELS, BLUR_PIXELS / 2]);
        expect(tiny[0].calls).toContainEqual(['drawImage', photo, 330, 230, 340, 170, 0, 0, BLUR_PIXELS, BLUR_PIXELS / 2]);
        expect(photo.calls).toContainEqual(['smoothing', true]);
        expect(photo.calls).toContainEqual(['drawImage', tiny[0], 0, 0, BLUR_PIXELS, BLUR_PIXELS / 2, 330, 230, 340, 170]);
    });

    it('keeps each zone inside the photo, and skips an empty one', () => {
        const photo = fakeCanvas(1000, 800);
        const tiny = [];

        blurZones(photo, [{ x: 900, y: 700, width: 300, height: 300 }, { x: 10, y: 10, width: 0, height: 50 }], {
            createCanvas: () => {
                const canvas = fakeCanvas(0, 0);
                tiny.push(canvas);
                return canvas;
            },
        });

        expect(tiny).toHaveLength(1);
        expect(photo.calls.at(-1)).toEqual(['drawImage', tiny[0], 0, 0, BLUR_PIXELS, BLUR_PIXELS, 900, 700, 100, 100]);
    });
});

describe('pointOnImage and zoneAround', () => {
    it('Difumino a mano lo que el detector no vio: takes the touch to the photo, and a zone around it', () => {
        // La foto de 1920 x 1440 se ve en 480 x 360 px, desde (20, 100) de la pantalla.
        const point = pointOnImage(140, 190, { left: 20, top: 100, width: 480, height: 360 }, 1920, 1440);

        expect(point).toEqual({ x: 480, y: 360 });
        expect(zoneAround(point, 1920, 1440)).toEqual({ x: 350, y: 230, width: 259, height: 259 });
        expect(zoneAround({ x: 10, y: 10 }, 1920, 1440)).toEqual({ x: 0, y: 0, width: 259, height: 259 });
    });
});
