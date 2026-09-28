// @vitest-environment node
// Iteración 16 — US-009, R-HASH-01: el SHA-256 se calcula en el teléfono
// sobre el archivo YA optimizado o limpio, que es el que se sube y el que el
// servidor recalcula. Web Crypto (crypto.subtle), como en el navegador.
import { createHash } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { kindOf, prepareEvidence } from './prepare.js';

const sha256Of = async (file) => createHash('sha256').update(Buffer.from(await file.arrayBuffer())).digest('hex');

describe('prepareEvidence', () => {
    it('hashes the optimized photo, not the one the camera took', async () => {
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

    it('hashes the PDF once its metadata is cleaned', async () => {
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
