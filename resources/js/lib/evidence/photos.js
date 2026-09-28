// US-009, R-PRIV-06: cada foto se optimiza en el teléfono antes de subirla:
// JPEG, lado mayor de 1920 px, calidad 80 %, y sin EXIF (ni coordenadas, ni
// modelo del teléfono, ni fecha). No se difumina nada (R-PRIV-05): solo se
// escala. Lo que sale de aquí es lo que se hashea, se sube y se sella.

export const MAX_PHOTO_SIDE = 1920;
export const JPEG_QUALITY = 0.8;

export function scaledSize(width, height, maxSide = MAX_PHOTO_SIDE) {
    const scale = Math.min(1, maxSide / Math.max(width, height));
    return { width: Math.round(width * scale), height: Math.round(height * scale) };
}

// Segmentos de un JPEG que no son la imagen: APP1 a APP15 (EXIF, XMP, ICC…) y COM.
const isMetadata = (marker) => (marker >= 0xe1 && marker <= 0xef) || marker === 0xfe;

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

/**
 * El navegador decodifica la foto, HEIC incluida donde la soporta (Safari,
 * que es donde el iPhone la produce). "from-image" respeta la orientación
 * del EXIF, así la foto queda derecha aunque el EXIF se vaya.
 */
async function decodeImage(file) {
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

function encodeJpeg(image, width, height, quality) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(image, 0, 0, width, height);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error(UNREADABLE_PHOTO))), 'image/jpeg', quality);
    });
}

/** @returns {Promise<File>} la foto lista para subir */
export async function optimizePhoto(file, { decode = decodeImage, encode = encodeJpeg } = {}) {
    const { image, width, height } = await decode(file);
    const size = scaledSize(width, height);
    const jpeg = await encode(image, size.width, size.height, JPEG_QUALITY);
    image.close?.();

    const bytes = stripJpegMetadata(new Uint8Array(await jpeg.arrayBuffer()));
    const name = `${file.name.replace(/\.[^.]*$/, '') || 'foto'}.jpg`;

    return new File([bytes], name, { type: 'image/jpeg' });
}
