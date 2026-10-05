// Iteración 16 — US-009: las reglas de adjuntos que la app aplica antes de
// subir nada, con los mismos mensajes que el servidor
// (App\Domain\Reports\Exceptions\ReportValidationException).
import { describe, expect, it } from 'vitest';
import { MESSAGES, acceptedTypes, cannotAdd, cannotUpload } from './attachments.js';

const photos = (count) => Array.from({ length: count }, () => ({ kind: 'photo' }));
const MB = 1024 * 1024;

describe('Cantidad y combinación de archivos', () => {
    it.each([
        ['1 foto', [], 'photo', null],
        ['5 fotos', photos(4), 'photo', null],
        ['6 fotos', photos(5), 'photo', MESSAGES.tooManyPhotos],
        ['1 PDF', [], 'pdf', null],
        ['2 PDF', [{ kind: 'pdf' }], 'pdf', MESSAGES.mixed],
        ['2 fotos y 1 PDF', photos(2), 'pdf', MESSAGES.mixed],
        ['un PDF y luego una foto', [{ kind: 'pdf' }], 'photo', MESSAGES.mixed],
    ])('%s', (_case, attached, kind, message) => {
        expect(cannotAdd(attached, kind)).toBe(message);
    });

    it('rejects a report with no file', () => {
        expect(cannotUpload([])).toBe(MESSAGES.required);
        expect(cannotUpload(photos(1))).toBeNull();
    });
});

describe('La app no permite mezclar fotos y PDF', () => {
    it('once 2 photos are attached, a PDF cannot be added', () => {
        expect(cannotAdd(photos(2), 'pdf')).toBe('Un reporte lleva de 1 a 5 fotos o un único PDF; no se pueden mezclar.');
    });
});

describe('Peso máximo de 10 MB por archivo', () => {
    it.each([
        ['10 MB', 10 * MB, null],
        ['10.1 MB', Math.round(10.1 * MB), 'Cada archivo puede pesar máximo 10 MB.'],
    ])('a PDF of %s', (_case, size, message) => {
        expect(cannotAdd([], 'pdf', size)).toBe(message);
    });
});

describe('No se aceptan videos', () => {
    it('rejects a 20-second video', () => {
        expect(cannotAdd([], null)).toBe('Solo se aceptan fotos en JPEG o un documento PDF; los videos y otros archivos no están permitidos.');
    });
});

// It. 46h (US-059-LEG): el ciudadano adjunta de 1 a 3 fotos, sin PDF, con las mismas reglas del veedor.
describe('Las fotos del informe ciudadano', () => {
    const citizen = { max: 3, photosOnly: true };

    it.each([
        ['la 1.ª foto', [], 'photo', null],
        ['la 3.ª foto', photos(2), 'photo', null],
        ['la 4.ª foto', photos(3), 'photo', 'Un informe admite máximo 3 fotos.'],
    ])('%s', (_case, attached, kind, message) => {
        expect(cannotAdd(attached, kind, 0, citizen)).toBe(message);
    });

    it('does not take a PDF, nor a video', () => {
        expect(cannotAdd([], 'pdf', 0, citizen)).toBe('Solo se aceptan fotos en JPEG; los PDF, los videos y otros archivos no están permitidos aquí.');
        expect(cannotAdd([], null, 0, citizen)).toBe('Solo se aceptan fotos en JPEG; los PDF, los videos y otros archivos no están permitidos aquí.');
    });

    it('offers only photos, and nothing once the 3 are attached', () => {
        expect(acceptedTypes([], citizen)).toBe('image/*');
        expect(acceptedTypes(photos(2), citizen)).toBe('image/*');
        expect(acceptedTypes(photos(3), citizen)).toBe('');
        expect(acceptedTypes([])).toBe('image/*,application/pdf');
    });

    it('still keeps the veedor rules by default', () => {
        expect(cannotAdd(photos(5), 'photo')).toBe(MESSAGES.tooManyPhotos);
    });
});
