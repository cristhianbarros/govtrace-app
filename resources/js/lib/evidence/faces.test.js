// @vitest-environment node
// Iteración 46e — R-PRIV-05 reescrita: los rostros se buscan en el celular,
// con BlazeFace (TF.js) alojado en GovTrace. El modelo lo prueba un navegador
// de verdad (make e2e, con una pintura de dominio público); aquí, lo que la app
// decide: dónde mirar, cómo juntar lo que ve y cuánto cubrir de la cabeza.
// It. 46f: en vez de los cuatro cuartos, una grilla de 4 × 4 ventanas, para
// los rostros lejanos (medido con el modelo real: desde unos 48 px, no 80).
// It. 48: el modelo corre en WebAssembly, y en la CPU si el navegador no puede.
import { describe, expect, it, vi } from 'vitest';
import { MIN_PROBABILITY, detectFaces, headOf, mergeBoxes, regionsOf, useFastestBackend } from './faces.js';

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
    // La región, del mismo tamaño: lo que ve el modelo está en píxeles de la foto.
    const crop = (source, region) => ({ region, width: region.width, height: region.height });

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

    it('gives the model each region already shrunk to what it sees (128 x 128 px), and puts what it finds back in the photo', async () => {
        const regions = [];
        const shrink = (source, region) => {
            regions.push(region);
            return { region, width: 128, height: 128 };
        };
        // Un rostro de 16 x 12 px en lo que ve el modelo: 240 x 135 px en la foto entera.
        const model = { estimateFaces: vi.fn(async (input) => (input.region.width === 1920 ? [face(32, 24, 16, 12)] : [])) };

        const faces = await detectFaces(canvas, { model, crop: shrink });

        expect(regions).toEqual(regionsOf(1920, 1440));
        expect(faces).toEqual([headOf({ x: 480, y: 270, width: 240, height: 135 }, 1920, 1440)]);
    });

    it('measures a face found in a window in photo pixels, to tell a far face from a big one', async () => {
        const shrink = (source, region) => ({ region, width: 128, height: 128 });
        // 60 px de 128 en una ventana de 595 px: unos 280 px en la foto, más de lo que busca una ventana (12 % de 1920).
        const model = { estimateFaces: vi.fn(async (input) => (input.region.width !== 1920 ? [face(10, 10, 60, 60)] : [])) };

        expect(await detectFaces(canvas, { model, crop: shrink })).toEqual([]);
    });

    it('does not ask the model about a photo too small to hold a face', async () => {
        const model = { estimateFaces: vi.fn() };

        expect(await detectFaces({ width: 1, height: 1 }, { model, crop })).toEqual([]);
        expect(model.estimateFaces).not.toHaveBeenCalled();
    });
});

describe('useFastestBackend', () => {
    const WASM_FILES = ['tfjs-backend-wasm-simd.wasm', 'tfjs-backend-wasm-threaded-simd.wasm', 'tfjs-backend-wasm.wasm'];

    function fakeTf(starts) {
        const flags = {};
        return { flags, env: () => ({ set: (name, value) => (flags[name] = value) }), setBackend: vi.fn(async (name) => starts.includes(name)) };
    }

    it('La revisión de rostros es rápida: runs the model in WebAssembly, downloaded from GovTrace, without threads', async () => {
        const tf = fakeTf(['wasm', 'cpu']);
        const wasm = { setWasmPaths: vi.fn() };
        const cpu = vi.fn();

        expect(await useFastestBackend(tf, { wasm: async () => wasm, cpu })).toBe('wasm');

        const paths = wasm.setWasmPaths.mock.calls[0][0];
        expect(Object.keys(paths).sort()).toEqual(WASM_FILES);
        // De GovTrace, como el modelo: el Service Worker los guarda para usarlos sin señal.
        for (const path of Object.values(paths)) {
            expect(path).not.toMatch(/^(https?:)?\/\//);
        }
        // Los hilos de WebAssembly corren en un worker blob:, que la CSP no permite.
        expect(tf.flags.WASM_HAS_MULTITHREAD_SUPPORT).toBe(false);
        expect(tf.setBackend).toHaveBeenCalledWith('wasm');
        expect(cpu).not.toHaveBeenCalled();
    });

    it('falls back to the CPU where WebAssembly does not start', async () => {
        const tf = fakeTf(['cpu']);
        const cpu = vi.fn(async () => ({}));

        expect(await useFastestBackend(tf, { wasm: async () => ({ setWasmPaths: vi.fn() }), cpu })).toBe('cpu');
        expect(cpu).toHaveBeenCalledOnce();
        expect(tf.setBackend).toHaveBeenLastCalledWith('cpu');
    });

    it('falls back to the CPU where the WebAssembly backend cannot be loaded', async () => {
        const tf = fakeTf(['wasm', 'cpu']);

        const backend = await useFastestBackend(tf, {
            wasm: async () => {
                throw new Error('Failed to fetch dynamically imported module');
            },
            cpu: async () => ({}),
        });

        expect(backend).toBe('cpu');
    });

    it('says so when not even the CPU backend starts', async () => {
        const tf = fakeTf([]);

        await expect(useFastestBackend(tf, { wasm: async () => ({ setWasmPaths: vi.fn() }), cpu: async () => ({}) })).rejects.toThrow('No se pudo iniciar el detector de rostros.');
    });
});
