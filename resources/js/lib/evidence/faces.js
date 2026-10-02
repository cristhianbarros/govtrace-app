// It. 46e — R-PRIV-05 reescrita: los rostros de una foto se buscan en el
// celular, antes de calcular su huella, con BlazeFace (Google, Apache-2.0) en
// TensorFlow.js. El modelo y el código vienen de GovTrace (el Service Worker los
// guarda para usarlos sin señal): la foto nunca sale del teléfono. Se usa el
// backend de CPU: cerca de 0,6 MB en total, sin WebGL ni WebAssembly.
import modelUrl from './blazeface/model.json?url';
import weightsUrl from './blazeface/group1-shard1of1.bin?url';

/** Lo que el modelo no está seguro de que sea un rostro, no lo es. */
export const MIN_PROBABILITY = 0.75;
/** Una foto más pequeña no tiene un rostro que reconocer. */
const MIN_SIDE = 64;
/** Desde este tamaño se mira también por cuartos: BlazeFace ve la foto en 128 x 128 px. */
const QUARTERS_FROM = 800;
const QUARTER = 0.575;
/** En un cuarto se buscan rostros pequeños: uno más ancho que esto, ya lo ve la foto entera (y suele ser otra cosa). */
const QUARTER_FACE_MAX = 0.2;
/** Del recuadro del rostro a la cabeza entera: el cabello, las orejas, el mentón. */
const HEAD_MARGIN = 0.35;
const SAME_FACE = 0.3;

/** @returns {Array<{x: number, y: number, width: number, height: number}>} */
export function regionsOf(width, height) {
    const whole = { x: 0, y: 0, width, height };
    if (Math.max(width, height) < QUARTERS_FROM) {
        return [whole];
    }
    const w = Math.round(width * QUARTER);
    const h = Math.round(height * QUARTER);
    return [whole, ...[0, height - h].flatMap((y) => [0, width - w].map((x) => ({ x, y, width: w, height: h })))];
}

export function headOf(box, width, height) {
    const left = Math.max(0, box.x - box.width * HEAD_MARGIN);
    const top = Math.max(0, box.y - box.height * HEAD_MARGIN);
    const right = Math.min(width, box.x + box.width * (1 + HEAD_MARGIN));
    const bottom = Math.min(height, box.y + box.height * (1 + HEAD_MARGIN));
    return { x: Math.round(left), y: Math.round(top), width: Math.round(right - left), height: Math.round(bottom - top) };
}

const area = (box) => box.width * box.height;

function overlap(a, b) {
    const width = Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x);
    const height = Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y);
    return width > 0 && height > 0 ? width * height : 0;
}

/** El mismo rostro, visto en la foto entera y en un cuarto: se juntan en uno que cubre los dos. */
export function mergeBoxes(boxes) {
    const merged = [];
    for (const box of boxes) {
        const same = merged.find((other) => {
            const shared = overlap(box, other);
            return shared / (area(box) + area(other) - shared) > SAME_FACE || shared / Math.min(area(box), area(other)) > 0.6;
        });
        if (same) {
            const right = Math.max(same.x + same.width, box.x + box.width);
            const bottom = Math.max(same.y + same.height, box.y + box.height);
            same.x = Math.min(same.x, box.x);
            same.y = Math.min(same.y, box.y);
            same.width = right - same.x;
            same.height = bottom - same.y;
        } else {
            merged.push({ ...box });
        }
    }
    return merged;
}

function cropOf(source, region) {
    if (region.width === source.width && region.height === source.height) {
        return source;
    }
    const canvas = document.createElement('canvas');
    canvas.width = region.width;
    canvas.height = region.height;
    canvas.getContext('2d').drawImage(source, region.x, region.y, region.width, region.height, 0, 0, region.width, region.height);
    return canvas;
}

const probabilityOf = (face) => Number(face.probability?.length !== undefined ? face.probability[0] : face.probability);

/** @returns {Promise<Array<{x: number, y: number, width: number, height: number}>>} each head, in photo pixels */
export async function detectFaces(canvas, { model = null, crop = cropOf } = {}) {
    if (Math.min(canvas.width, canvas.height) < MIN_SIDE) {
        return [];
    }
    const detector = model ?? (await loadDetector());
    const found = [];
    for (const [index, region] of regionsOf(canvas.width, canvas.height).entries()) {
        for (const face of await detector.estimateFaces(crop(canvas, region), false)) {
            const [left, top] = face.topLeft;
            const [right, bottom] = face.bottomRight;
            if (probabilityOf(face) < MIN_PROBABILITY || (index > 0 && right - left > canvas.width * QUARTER_FACE_MAX)) {
                continue;
            }
            const box = { x: region.x + left, y: region.y + top, width: right - left, height: bottom - top };
            found.push(headOf(box, canvas.width, canvas.height));
        }
    }
    return mergeBoxes(found);
}

async function modelArtifacts() {
    const [json, weights] = await Promise.all([fetch(modelUrl), fetch(weightsUrl)].map(async (request) => {
        const response = await request;
        if (!response.ok) {
            throw new Error('No se pudo descargar el detector de rostros.');
        }
        return response;
    }));
    const graph = await json.json();
    return {
        modelTopology: graph.modelTopology,
        format: graph.format,
        generatedBy: graph.generatedBy,
        convertedBy: graph.convertedBy,
        weightSpecs: graph.weightsManifest.flatMap((group) => group.weights),
        weightData: await weights.arrayBuffer(),
    };
}

let loading = null;

/** Una sola vez por pantalla. Si falla (sin señal la primera vez), se puede volver a intentar. */
export function loadDetector() {
    loading ??= (async () => {
        const [tf, , blazeface] = await Promise.all([import('@tensorflow/tfjs-core'), import('@tensorflow/tfjs-backend-cpu'), import('@tensorflow-models/blazeface')]);
        await tf.setBackend('cpu');
        await tf.ready();
        const artifacts = await modelArtifacts();
        return blazeface.load({ modelUrl: { load: async () => artifacts }, maxFaces: 20, scoreThreshold: MIN_PROBABILITY });
    })().catch((error) => {
        loading = null;
        throw error;
    });
    return loading;
}
