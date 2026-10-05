// @vitest-environment node
// Iteración 46e — R-PRIV-05 reescrita: los rostros se buscan en el celular,
// con BlazeFace (TF.js) alojado en GovTrace. El modelo lo prueba un navegador
// de verdad (make e2e, con una pintura de dominio público); aquí, lo que la app
// decide: dónde mirar, cómo juntar lo que ve y cuánto cubrir de la cabeza.
// It. 46f: en vez de los cuatro cuartos, una grilla de 4 × 4 ventanas, para
// los rostros lejanos (medido con el modelo real: desde unos 48 px, no 80).
import { describe, expect, it, vi } from 'vitest';
import { MIN_PROBABILITY, detectFaces, headOf, mergeBoxes, regionsOf } from './faces.js';

const face = (x, y, width, height, probability = 0.95) => ({ topLeft: [x, y], bottomRight: [x + width, y + height], probability: [probability] });

describe('regionsOf', () => {
    it('looks at the whole photo and at a grid of 4 x 4 windows, overlapping, to see far faces', () => {
        const regions = regionsOf(1920, 1440);

        expect(regions[0]).toEqual({ x: 0, y: 0, width: 1920, height: 1440 });
        expect(regions).toHaveLength(17);
        // Cada ventana se solapa con la siguiente, para no cortar un rostro en dos.
        expect(regions[1]).toEqual({ x: 0, y: 0, width: 595, height: 446 });
        expect(regions[2]).toEqual({ x: 442, y: 0, width: 595, height: 446 });
        expect(regions[16]).toEqual({ x: 1325, y: 994, width: 595, height: 446 });
    });

    it('looks only at the whole of a small photo', () => {
        expect(regionsOf(500, 375)).toEqual([{ x: 0, y: 0, width: 500, height: 375 }]);
    });
});

describe('headOf', () => {
    it('grows the face box to the whole head (hair, ears, chin), inside the photo', () => {
        expect(headOf({ x: 100, y: 100, width: 100, height: 100 }, 1000, 1000)).toEqual({ x: 65, y: 65, width: 170, height: 170 });
        expect(headOf({ x: 0, y: 950, width: 100, height: 100 }, 1000, 1000)).toEqual({ x: 0, y: 915, width: 135, height: 85 });
    });
});

describe('mergeBoxes', () => {
    it('joins the same face seen twice (in the photo and in a quarter), and keeps two faces apart', () => {
        const merged = mergeBoxes([
            { x: 100, y: 100, width: 100, height: 100 },
            { x: 110, y: 105, width: 95, height: 100 },
            { x: 600, y: 100, width: 80, height: 80 },
        ]);

        expect(merged).toEqual([
            { x: 100, y: 100, width: 105, height: 105 },
            { x: 600, y: 100, width: 80, height: 80 },
        ]);
    });
});

describe('detectFaces', () => {
    const canvas = { width: 1920, height: 1440 };
    const crop = (source, region) => ({ region });

    const at = (region, x, y) => region.x === x && region.y === y && region.width !== 1920;

    it('Los rostros de una foto se difuminan en el celular antes de calcular su huella: finds each face in photo coordinates, whole heads, once', async () => {
        const model = {
            estimateFaces: vi.fn(async (input) => {
                if (input.region.width === 1920) {
                    return [face(400, 300, 200, 200)];
                }
                // El mismo rostro, visto en la primera ventana; y uno pequeño, que solo ve la última.
                return at(input.region, 0, 0) ? [face(400, 300, 200, 200)] : at(input.region, 1325, 994) ? [face(300, 200, 60, 60)] : [];
            }),
        };

        const faces = await detectFaces(canvas, { model, crop });

        expect(model.estimateFaces).toHaveBeenCalledTimes(17);
        expect(faces).toEqual([
            { x: 330, y: 230, width: 340, height: 340 },
            { x: 1604, y: 1173, width: 102, height: 102 },
        ]);
    });

    it('Un rostro lejano también se difumina: one of about 50 px, in the middle of the photo, that only a window sees', async () => {
        const model = { estimateFaces: vi.fn(async (input) => (at(input.region, 883, 663) ? [face(60, 40, 50, 50)] : [])) };

        expect(await detectFaces(canvas, { model, crop })).toEqual([{ x: 926, y: 686, width: 85, height: 85 }]);
    });

    it('looks in the windows only for small faces: a big one, the whole photo would have seen (it was the hands of a painting)', async () => {
        const model = {
            estimateFaces: vi.fn(async (input) => (at(input.region, 1325, 994) ? [face(100, 100, 240, 340)] : [])),
        };

        expect(await detectFaces(canvas, { model, crop })).toEqual([]);
    });

    it(`ignores what the model is not sure is a face (below ${MIN_PROBABILITY})`, async () => {
        const model = { estimateFaces: vi.fn(async () => [face(10, 10, 100, 100, 0.5)]) };

        expect(await detectFaces({ width: 400, height: 300 }, { model, crop })).toEqual([]);
    });

    it('does not ask the model about a photo too small to hold a face', async () => {
        const model = { estimateFaces: vi.fn() };

        expect(await detectFaces({ width: 1, height: 1 }, { model, crop })).toEqual([]);
        expect(model.estimateFaces).not.toHaveBeenCalled();
    });
});
