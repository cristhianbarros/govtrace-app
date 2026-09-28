// @vitest-environment node
// Iteración 16 — US-009, R-PRIV-06: cada foto se optimiza en el teléfono
// (JPEG, lado mayor de 1920 px, calidad 80 %) y se sube sin EXIF, con sus
// coordenadas GPS incluidas. Decodificar y dibujar la imagen es del
// navegador (canvas); aquí se prueba lo que la app decide y el JPEG que sale.
import { describe, expect, it, vi } from 'vitest';
import { ascii, cleanJpeg, contains, jpegWithExif } from './jpeg-fixtures.js';
import { optimizePhoto, scaledSize, stripJpegMetadata } from './photos.js';

describe('optimizePhoto', () => {
    it('converts a 4000x3000 HEIC photo with GPS in its EXIF into a 1920 px JPEG at 80 %, without EXIF', async () => {
        const heic = new File([ascii('ftypheic…')], 'IMG_0001.HEIC', { type: 'image/heic' });
        const decode = vi.fn(async () => ({ image: 'decoded-image', width: 4000, height: 3000 }));
        // Un codificador que dejara pasar el EXIF: la app lo quita igual.
        const encode = vi.fn(async () => new Blob([jpegWithExif()], { type: 'image/jpeg' }));

        const photo = await optimizePhoto(heic, { decode, encode });
        const bytes = new Uint8Array(await photo.arrayBuffer());

        expect(decode).toHaveBeenCalledWith(heic);
        expect(encode).toHaveBeenCalledWith('decoded-image', 1920, 1440, 0.8);
        expect(photo.type).toBe('image/jpeg');
        expect(photo.name).toBe('IMG_0001.jpg');
        expect(contains(bytes, 'Exif')).toBe(false);
        expect(contains(bytes, 'GPSLatitude')).toBe(false);
        expect(bytes).toEqual(cleanJpeg);
    });

    it('keeps the proportions of a portrait photo and never enlarges a small one', () => {
        expect(scaledSize(3000, 4000)).toEqual({ width: 1440, height: 1920 });
        expect(scaledSize(1200, 900)).toEqual({ width: 1200, height: 900 });
        expect(scaledSize(1920, 1080)).toEqual({ width: 1920, height: 1080 });
    });
});

describe('stripJpegMetadata', () => {
    it('removes EXIF, XMP and comments, and keeps the image itself byte for byte', () => {
        const xmp = new Uint8Array([0xff, 0xe1, 0, 2 + 29, ...ascii('http://ns.adobe.com/xap/1.0/\0')]);
        const comment = new Uint8Array([0xff, 0xfe, 0, 2 + 12, ...ascii('Carlos Gomez')]);
        const dirty = new Uint8Array([...jpegWithExif().slice(0, 2), ...xmp, ...comment, ...jpegWithExif().slice(2)]);

        const clean = stripJpegMetadata(dirty);

        expect(clean).toEqual(cleanJpeg);
        expect(contains(clean, 'Carlos')).toBe(false);
    });

    it('keeps the APP14 "Adobe" segment, without which a CMYK JPEG changes color', () => {
        const adobe = new Uint8Array([0xff, 0xee, 0, 2 + 12, ...ascii('Adobe'), 0, 100, 0, 0, 0, 0, 2]);
        const cmyk = new Uint8Array([...cleanJpeg.slice(0, 2), ...adobe, ...cleanJpeg.slice(2)]);

        expect(stripJpegMetadata(cmyk)).toEqual(cmyk);
    });

    it('rejects a file that is not a JPEG', () => {
        expect(() => stripJpegMetadata(ascii('%PDF-1.7'))).toThrow();
    });
});
