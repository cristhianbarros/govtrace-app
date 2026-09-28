// US-009, R-HASH-01: cada archivo se prepara en el teléfono — la foto
// optimizada y sin EXIF, el PDF sin metadatos — y su SHA-256 se calcula
// sobre ese resultado, que es lo que se sube y lo que el servidor recalcula.
import { sha256Hex } from '../merkle.js';
import { optimizePhoto as optimize } from './photos.js';

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
