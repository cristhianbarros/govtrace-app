// @vitest-environment node
// Iteración 16 — US-009, R-HASH-01: el SHA-256 se calcula en el teléfono
// sobre el archivo YA optimizado o limpio, que es el que se sube y el que el
// servidor recalcula. Web Crypto (crypto.subtle), como en el navegador.
import { createHash } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { draftPhoto, finishPhoto, kindOf, prepareEvidence } from './prepare.js';

const sha256Of = async (file) => createHash('sha256').update(Buffer.from(await file.arrayBuffer())).digest('hex');

describe('prepareEvidence', () => {
    it('Fotos optimizadas, sin EXIF y con hash calculado en el teléfono: hashes the optimized photo, not the one the camera took', async () => {
        const original = new File(['foto original de 4 MB con EXIF'], 'IMG_0001.HEIC', { type: 'image/heic' });
        const optimized = new File(['foto optimizada, sin EXIF'], 'IMG_0001.jpg', { type: 'image/jpeg' });
        const optimizePhoto = vi.fn(async () => optimized);

        const evidence = await prepareEvidence(original, { optimizePhoto });

        expect(optimizePhoto).toHaveBeenCalledWith(original);
        expect(evidence.kind).toBe('photo');
        expect(evidence.file).toBe(optimized);
        expect(evidence.sha256).toBe(await sha256Of(optimized));
        expect(evidence.sha256).not.toBe(await sha256Of(original));
    });

    it('Los metadatos del PDF se limpian antes del hash: hashes the PDF once its metadata is cleaned', async () => {
        const original = new File(['%PDF con autor'], 'acta.pdf', { type: 'application/pdf' });
        const cleaned = new File(['%PDF sin metadatos'], 'acta.pdf', { type: 'application/pdf' });
        const cleanPdfMetadata = vi.fn(async () => cleaned);

        const evidence = await prepareEvidence(original, { cleanPdfMetadata });

        expect(evidence.kind).toBe('pdf');
        expect(evidence.sha256).toBe(await sha256Of(cleaned));
    });
});

describe('kindOf', () => {
    it('tells photos and PDFs apart, and nothing else is evidence', () => {
        expect(kindOf(new File([''], 'a.heic', { type: 'image/heic' }))).toBe('photo');
        expect(kindOf(new File([''], 'a.jpg', { type: 'image/jpeg' }))).toBe('photo');
        expect(kindOf(new File([''], 'a.pdf', { type: 'application/pdf' }))).toBe('pdf');
        expect(kindOf(new File([''], 'a.mp4', { type: 'video/mp4' }))).toBeNull();
        expect(kindOf(new File([''], 'a.svg', { type: 'image/svg+xml' }))).toBeNull();
    });
});

// Iteración 46e — R-PRIV-05 reescrita: la foto se revisa antes de adjuntarla. Primero el
// borrador (la foto escalada y los rostros que vio el detector), y al aceptarla, se
// difumina, se codifica y se calcula su huella: la huella es la de la foto difuminada.
describe('draftPhoto and finishPhoto', () => {
    const photo = new File(['foto de la cámara'], 'IMG_0002.HEIC', { type: 'image/heic' });
    const canvas = { width: 1920, height: 1440 };
    const faces = [
        { x: 330, y: 230, width: 340, height: 340 },
        { x: 1295, y: 991, width: 102, height: 102 },
    ];

    it('Los rostros de una foto se difuminan en el celular antes de calcular su huella: blurs them, then encodes and hashes the blurred photo', async () => {
        const draft = await draftPhoto(photo, { canvasOf: async () => canvas, findFaces: async () => faces });
        expect(draft).toMatchObject({ name: 'IMG_0002.jpg', canvas, faces, detector: 'ok' });

        const order = [];
        const blurred = new File(['foto difuminada'], 'IMG_0002.jpg', { type: 'image/jpeg' });
        const blur = vi.fn(() => order.push('blur'));
        const toFile = vi.fn(async () => {
            order.push('encode');
            return blurred;
        });

        const evidence = await finishPhoto(draft, { dismissed: [], manual: [] }, { blur, toFile });

        expect(blur).toHaveBeenCalledWith(canvas, faces);
        expect(order).toEqual(['blur', 'encode']);
        expect(evidence).toMatchObject({ kind: 'photo', file: blurred, blurs: { faces: 2, dismissed: 0, manual: 0 } });
        expect(evidence.sha256).toBe(await sha256Of(blurred));
    });

    it('Quito un recuadro que no es un rostro, y la Bandeja lo marca: does not blur it, and counts it; a zone by hand is blurred too', async () => {
        const draft = await draftPhoto(photo, { canvasOf: async () => canvas, findFaces: async () => faces });
        const plate = { x: 100, y: 1200, width: 200, height: 200 };
        const blur = vi.fn();

        const evidence = await finishPhoto(draft, { dismissed: [1], manual: [plate] }, { blur, toFile: async () => new File(['x'], 'IMG_0002.jpg') });

        expect(blur).toHaveBeenCalledWith(canvas, [faces[0], plate]);
        expect(evidence.blurs).toEqual({ faces: 1, dismissed: 1, manual: 1 });
    });

    it('Si el detector no carga, la foto se revisa a mano: the draft says so, without faces', async () => {
        const draft = await draftPhoto(photo, {
            canvasOf: async () => canvas,
            findFaces: async () => {
                throw new Error('No se pudo descargar el modelo');
            },
        });

        expect(draft).toMatchObject({ faces: [], detector: 'unavailable' });
    });
});
