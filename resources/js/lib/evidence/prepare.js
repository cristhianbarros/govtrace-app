// US-009, R-HASH-01: cada archivo se prepara en el teléfono — la foto
// optimizada y sin EXIF, el PDF sin metadatos — y su SHA-256 se calcula
// sobre ese resultado, que es lo que se sube y lo que el servidor recalcula.
// It. 46e (R-PRIV-05): la foto se revisa antes: primero un borrador con los
// rostros que vio el detector, y al aceptarla se difumina, y solo entonces
// se codifica y se calcula su huella.
import { sha256Hex } from '../merkle.js';
import { blurZones } from './blur.js';
import { detectFaces } from './faces.js';
import { optimizePhoto as optimize, photoCanvas, photoFile, photoName } from './photos.js';

/** Si el detector no responde en este tiempo (el modelo no baja), la foto se revisa a mano. */
const DETECTION_TIMEOUT_MS = 30_000;

// pdf-lib pesa unos 400 KB: el teléfono lo descarga solo si se adjunta un PDF.
const clean = async (file) => (await import('./pdf.js')).cleanPdfMetadata(file);

/** "photo", "pdf" o null si no es evidencia (un video, un SVG…). */
export function kindOf(file) {
    if (file.type === 'application/pdf') {
        return 'pdf';
    }
    if ((file.type.startsWith('image/') && file.type !== 'image/svg+xml') || /\.hei[cf]$/i.test(file.name)) {
        return 'photo';
    }
    return null;
}

/** @returns {Promise<{kind: string, file: File, sha256: string}>} */
export async function prepareEvidence(file, { optimizePhoto = optimize, cleanPdfMetadata = clean } = {}) {
    const kind = kindOf(file);
    const prepared = kind === 'photo' ? await optimizePhoto(file) : await cleanPdfMetadata(file);

    return { kind, file: prepared, sha256: await sha256Hex(new Uint8Array(await prepared.arrayBuffer())) };
}

const timeout = (ms) => new Promise((resolve, reject) => setTimeout(() => reject(new Error('El detector de rostros no respondió.')), ms));

/**
 * @returns {Promise<{name: string, canvas: object, faces: Array<object>, detector: 'ok'|'unavailable'}>}
 *          the photo, scaled, and the heads the detector found — or that it could not look
 */
export async function draftPhoto(file, { canvasOf = photoCanvas, findFaces = detectFaces, timeoutMs = DETECTION_TIMEOUT_MS } = {}) {
    const canvas = await canvasOf(file);
    try {
        return { name: photoName(file), canvas, faces: await Promise.race([findFaces(canvas), timeout(timeoutMs)]), detector: 'ok' };
    } catch {
        return { name: photoName(file), canvas, faces: [], detector: 'unavailable' };
    }
}

/**
 * The photo as it is sent: the faces not dismissed and the zones blurred by
 * hand, blurred for good; then the JPEG, and its SHA-256.
 *
 * @param {{dismissed?: number[], manual?: Array<object>}} review
 */
export async function finishPhoto(draft, { dismissed = [], manual = [] } = {}, { blur = blurZones, toFile = photoFile } = {}) {
    const kept = draft.faces.filter((_, index) => !dismissed.includes(index));
    blur(draft.canvas, [...kept, ...manual]);
    const file = await toFile(draft.canvas, draft.name);

    return {
        kind: 'photo',
        file,
        sha256: await sha256Hex(new Uint8Array(await file.arrayBuffer())),
        blurs: { faces: kept.length, dismissed: dismissed.length, manual: manual.length },
    };
}
