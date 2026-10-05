// US-009: las reglas de adjuntos que la app aplica antes de subir nada —
// de 1 a 5 fotos o un solo PDF, sin mezclar, 10 MB por archivo — con los
// mismos mensajes que el servidor (App\Domain\Reports\EvidenceSet).

export const MAX_PHOTOS = 5;
export const MAX_FILE_BYTES = 10 * 1024 * 1024;

export const MESSAGES = {
    required: 'Adjunte de 1 a 5 fotos o un documento PDF.',
    unsupported: 'Solo se aceptan fotos en JPEG o un documento PDF; los videos y otros archivos no están permitidos.',
    tooLarge: 'Cada archivo puede pesar máximo 10 MB.',
    mixed: 'Un reporte lleva de 1 a 5 fotos o un único PDF; no se pueden mezclar.',
    tooManyPhotos: `Un reporte admite máximo ${MAX_PHOTOS} fotos.`,
    // It. 46h: el ciudadano adjunta fotos y nada más.
    photosOnly: 'Solo se aceptan fotos en JPEG; los PDF, los videos y otros archivos no están permitidos aquí.',
};

/**
 * It. 46h (US-059-LEG): el selector sirve al veedor (de 1 a 5 fotos o un PDF) y al
 * ciudadano (de 1 a 3 fotos, sin PDF).
 *
 * @typedef {{ max?: number, photosOnly?: boolean }} Limits
 */
const DEFAULTS = { max: MAX_PHOTOS, photosOnly: false };

/**
 * Por qué un archivo de tipo `kind` ("photo", "pdf" o null) no se puede
 * sumar a los ya adjuntos; null si se puede. `size`, una vez preparado.
 */
export function cannotAdd(attached, kind, size = 0, limits = DEFAULTS) {
    const { max, photosOnly } = { ...DEFAULTS, ...limits };
    if (photosOnly && kind !== 'photo') {
        return MESSAGES.photosOnly;
    }
    if (kind === null) {
        return MESSAGES.unsupported;
    }
    if (size > MAX_FILE_BYTES) {
        return MESSAGES.tooLarge;
    }
    if (attached.some((evidence) => evidence.kind !== kind) || (kind === 'pdf' && attached.length > 0)) {
        return MESSAGES.mixed;
    }
    if (attached.length >= max) {
        return max === MAX_PHOTOS ? MESSAGES.tooManyPhotos : `Un informe admite máximo ${max} fotos.`;
    }
    return null;
}

export const cannotUpload = (attached) => (attached.length === 0 ? MESSAGES.required : null);

/** Lo que ofrece el selector: fotos o un PDF; con fotos, solo fotos; con el PDF o 5 fotos, nada. */
export function acceptedTypes(attached, limits = DEFAULTS) {
    const { max, photosOnly } = { ...DEFAULTS, ...limits };
    if (attached.some((evidence) => evidence.kind === 'pdf') || attached.length >= max) {
        return '';
    }
    return attached.length > 0 || photosOnly ? 'image/*' : 'image/*,application/pdf';
}
