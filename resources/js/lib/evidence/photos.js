// US-009, R-PRIV-06: cada foto se optimiza en el teléfono antes de subirla:
// JPEG, lado mayor de 1920 px, calidad 80 %, y sin EXIF (ni coordenadas, ni
// modelo del teléfono, ni fecha). It. 46e (R-PRIV-05): entre el escalado y el
// JPEG, los rostros se difuminan (prepare.js). Lo que sale de aquí es lo que se
// hashea, se sube y se sella.

export const MAX_PHOTO_SIDE = 1920;
export const JPEG_QUALITY = 0.8;

export function scaledSize(width, height, maxSide = MAX_PHOTO_SIDE) {
    const scale = Math.min(1, maxSide / Math.max(width, height));
    return { width: Math.round(width * scale), height: Math.round(height * scale) };
}

// Segmentos de un JPEG que no son la imagen: APP1 a APP15 (EXIF, XMP, ICC…) y
// COM. Se conserva APP14 "Adobe": no lleva datos personales y los JPEG en
// CMYK, comunes dentro de los PDF, lo necesitan para sus colores.
const APP14 = 0xee;
const isMetadata = (marker) => ((marker >= 0xe1 && marker <= 0xef) || marker === 0xfe) && marker !== APP14;

/** El JPEG sin sus segmentos de metadatos; los de la imagen quedan byte a byte. */
export function stripJpegMetadata(bytes) {
    if (bytes[0] !== 0xff || bytes[1] !== 0xd8) {
        throw new Error('La foto no es un JPEG.');
    }

    const kept = [bytes.subarray(0, 2)];
    for (let offset = 2; offset + 4 <= bytes.length; ) {
        const marker = bytes[offset + 1];
        if (bytes[offset] !== 0xff) {
            break;
        }
        if (marker === 0xda) {
            // Inicio del escaneo: de aquí al final son los datos de la imagen.
            kept.push(bytes.subarray(offset));
            return concat(kept);
        }
        const end = offset + 2 + ((bytes[offset + 2] << 8) | bytes[offset + 3]);
        if (!isMetadata(marker)) {
            kept.push(bytes.subarray(offset, end));
        }
        offset = end;
    }

    throw new Error('La foto está dañada.');
}

function concat(parts) {
    const result = new Uint8Array(parts.reduce((total, part) => total + part.length, 0));
    let offset = 0;
    for (const part of parts) {
        result.set(part, offset);
        offset += part.length;
    }
    return result;
}

const UNREADABLE_PHOTO = 'No se pudo leer la foto. Tómela de nuevo con la cámara del teléfono.';

// It. 62b: una foto de muchos megapíxeles se decodificaba entera antes de
// reducirla — una de 50 MP ocupa 200 MB —, y en un celular de gama media el
// sistema cerraba la app. Un JPEG se puede decodificar ya reducido, en octavos
// de su tamaño, sin pasar por el tamaño completo: lo hace ImageDecoder.

/** El SOF (C0 a CF, menos C4, C8 y CC), donde un JPEG dice su ancho y su alto, suele estar en los primeros KB. */
const JPEG_HEADER_BYTES = 256 * 1024;
const isFrameStart = (marker) => marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker);

/** El ancho y el alto de un JPEG, leídos de su encabezado, sin decodificarlo; null si no los encuentra. */
export function jpegSize(bytes) {
    if (bytes[0] !== 0xff || bytes[1] !== 0xd8) {
        return null;
    }
    for (let offset = 2; offset + 9 <= bytes.length && bytes[offset] === 0xff; ) {
        const marker = bytes[offset + 1];
        if (isFrameStart(marker)) {
            return { width: (bytes[offset + 7] << 8) | bytes[offset + 8], height: (bytes[offset + 5] << 8) | bytes[offset + 6] };
        }
        if (marker === 0xda) {
            return null;
        }
        offset += 2 + ((bytes[offset + 2] << 8) | bytes[offset + 3]);
    }
    return null;
}

/** El menor octavo de la foto cuyo lado mayor todavía llega a maxSide (un JPEG se decodifica de 1/8 a 8/8). */
export function jpegDecodeSize(width, height, maxSide = MAX_PHOTO_SIDE) {
    const eighths = Math.min(8, Math.ceil((maxSide * 8) / Math.max(width, height)));
    return { width: Math.ceil((width * eighths) / 8), height: Math.ceil((height * eighths) / 8) };
}

/** ImageDecoder, solo donde lo que decodifica trae el giro del EXIF (VideoFrame.rotation) y el canvas lo aplica. */
const reducedDecoder = () => (typeof ImageDecoder !== 'undefined' && typeof VideoFrame !== 'undefined' && 'rotation' in VideoFrame.prototype ? ImageDecoder : null);

/**
 * It. 62b: el JPEG decodificado ya reducido, derecho; null si no se puede (otro
 * formato, un navegador sin ImageDecoder) o si no hace falta. El cuadro que da
 * ImageDecoder se copia a un ImageBitmap y se cierra: dibujado directo en el
 * canvas, Chromium dejaba tomada su memoria, foto tras foto.
 */
export async function decodeReduced(file, { Decoder = reducedDecoder(), toBitmap = (frame) => createImageBitmap(frame) } = {}) {
    if (!Decoder) {
        return null;
    }
    const size = jpegSize(new Uint8Array(await file.slice(0, JPEG_HEADER_BYTES).arrayBuffer()));
    const wanted = size && jpegDecodeSize(size.width, size.height);
    if (!wanted || wanted.width >= size.width) {
        return null;
    }
    let decoder = null;
    let frame = null;
    try {
        decoder = new Decoder({ data: file.stream(), type: 'image/jpeg', desiredWidth: wanted.width, desiredHeight: wanted.height });
        frame = (await decoder.decode()).image;
        const image = await toBitmap(frame);
        // El tamaño pedido es una sugerencia: si llega más pequeña de lo necesario, o acostada, se decodifica entera.
        if (Math.max(image.width, image.height) < MAX_PHOTO_SIDE || image.width !== frame.displayWidth || image.height !== frame.displayHeight) {
            image.close();
            return null;
        }
        return { image, width: image.width, height: image.height };
    } catch {
        return null;
    } finally {
        frame?.close();
        decoder?.close();
    }
}

/**
 * El navegador decodifica la foto, HEIC incluida donde la soporta (Safari,
 * que es donde el iPhone la produce). "from-image" respeta la orientación
 * del EXIF, así la foto queda derecha aunque el EXIF se vaya.
 */
async function decodeImage(file) {
    const reduced = await decodeReduced(file);
    if (reduced) {
        return reduced;
    }
    try {
        const image = await createImageBitmap(file, { imageOrientation: 'from-image' });
        return { image, width: image.width, height: image.height };
    } catch {
        const url = URL.createObjectURL(file);
        try {
            const image = new Image();
            image.src = url;
            await image.decode();
            return { image, width: image.naturalWidth, height: image.naturalHeight };
        } catch {
            throw new Error(UNREADABLE_PHOTO);
        } finally {
            URL.revokeObjectURL(url);
        }
    }
}

/** It. 62b: un canvas ocupa su memoria hasta que se encoge (Safari, en el iPhone, no la suelta antes). */
export function releaseCanvas(canvas) {
    canvas.width = 0;
    canvas.height = 0;
}

function toJpeg(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error(UNREADABLE_PHOTO))), 'image/jpeg', quality);
    });
}

async function encodeJpeg(image, width, height, quality) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(image, 0, 0, width, height);
    try {
        return await toJpeg(canvas, quality);
    } finally {
        releaseCanvas(canvas);
    }
}

export const photoName = (file) => `${file.name.replace(/\.[^.]*$/, '') || 'foto'}.jpg`;

/** @returns {Promise<File>} la foto lista para subir */
export async function optimizePhoto(file, { decode = decodeImage, encode = encodeJpeg } = {}) {
    const { image, width, height } = await decode(file);
    const size = scaledSize(width, height);
    const jpeg = await encode(image, size.width, size.height, JPEG_QUALITY);
    image.close?.();

    return new File([stripJpegMetadata(new Uint8Array(await jpeg.arrayBuffer()))], photoName(file), { type: 'image/jpeg' });
}

/** It. 46e: la foto escalada en un canvas, para revisar sus rostros y difuminarlos antes del JPEG. */
export async function photoCanvas(file, { decode = decodeImage, createCanvas = () => document.createElement('canvas') } = {}) {
    const { image, width, height } = await decode(file);
    const size = scaledSize(width, height);
    const canvas = createCanvas();
    canvas.width = size.width;
    canvas.height = size.height;
    canvas.getContext('2d').drawImage(image, 0, 0, size.width, size.height);
    image.close?.();
    return canvas;
}

/** It. 46e: el canvas, ya difuminado, en JPEG y sin metadatos. It. 62b: el mismo canvas, sin dibujarlo en otro. */
export async function photoFile(canvas, name, { encode = toJpeg } = {}) {
    const jpeg = await encode(canvas, JPEG_QUALITY);
    return new File([stripJpegMetadata(new Uint8Array(await jpeg.arrayBuffer()))], name, { type: 'image/jpeg' });
}
