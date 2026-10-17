// @vitest-environment node
// Iteración 16 — US-009, R-PRIV-06: cada foto se optimiza en el teléfono
// (JPEG, lado mayor de 1920 px, calidad 80 %) y se sube sin EXIF, con sus
// coordenadas GPS incluidas. Decodificar y dibujar la imagen es del
// navegador (canvas); aquí se prueba lo que la app decide y el JPEG que sale.
import { describe, expect, it, vi } from 'vitest';
import { ascii, cleanJpeg, contains, jpegWithExif } from './jpeg-fixtures.js';
import { decodeReduced, jpegDecodeSize, jpegSize, optimizePhoto, photoFile, scaledSize, stripJpegMetadata } from './photos.js';

describe('optimizePhoto', () => {
    it('Fotos optimizadas, sin EXIF y con hash calculado en el teléfono: converts a 4000x3000 HEIC photo with GPS in its EXIF into a 1920 px JPEG at 80 %, without EXIF', async () => {
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

// It. 62b: una foto de muchos megapíxeles se decodificaba entera antes de
// reducirla (8160 x 6120 px: 200 MB), y el celular cerraba la app. Un JPEG se
// decodifica ya reducido, en octavos de su tamaño.
describe('decodeReduced', () => {
    /** The header of a phone JPEG: its EXIF with Orientation 6 (taken upright) and its frame size, then the scan. */
    function phoneJpeg(width, height) {
        const exif = [...ascii('Exif\0\0MM\0*'), 0, 0, 0, 8, 0, 1, 0x01, 0x12, 0, 3, 0, 0, 0, 1, 0, 6, 0, 0, 0, 0, 0, 0];
        const frame = [8, height >> 8, height & 0xff, width >> 8, width & 0xff, 3, 1, 0x22, 0, 2, 0x11, 1, 3, 0x11, 1];
        return new File([new Uint8Array([0xff, 0xd8, 0xff, 0xe1, 0, exif.length + 2, ...exif, 0xff, 0xc0, 0, frame.length + 2, ...frame, 0xff, 0xda, 0, 2, 0xff, 0xd9])], 'IMG_0003.jpg', { type: 'image/jpeg' });
    }

    /** A WebCodecs ImageDecoder double: it gives the photo at `gives`; the frame turned upright is 'display'. */
    function decoderGiving(gives) {
        const created = [];
        const frame = { displayWidth: gives[1], displayHeight: gives[0], close: vi.fn() };
        class Decoder {
            constructor(init) {
                this.init = init;
                this.close = vi.fn();
                created.push(this);
            }

            async decode() {
                return { image: frame };
            }
        }
        return { Decoder, created, frame };
    }

    // Dibujar el VideoFrame en el canvas dejaba tomada su memoria, foto tras foto: se copia antes a un ImageBitmap.
    const bitmapOf = (width, height) => vi.fn(async () => ({ width, height, close: vi.fn() }));

    it('Una foto de 50 megapíxeles se prepara sin agotar la memoria del celular: decodes a 8160x6120 JPEG at a quarter, upright, and lets its decoder go', async () => {
        const { Decoder, created, frame } = decoderGiving([2040, 1530]);
        const toBitmap = bitmapOf(1530, 2040);

        const decoded = await decodeReduced(phoneJpeg(8160, 6120), { Decoder, toBitmap });

        expect(created).toHaveLength(1);
        expect(created[0].init).toMatchObject({ type: 'image/jpeg', desiredWidth: 2040, desiredHeight: 1530 });
        expect(toBitmap).toHaveBeenCalledWith(frame);
        expect(decoded).toEqual({ image: await toBitmap.mock.results[0].value, width: 1530, height: 2040 });
        expect(scaledSize(decoded.width, decoded.height)).toEqual({ width: 1440, height: 1920 });
        expect(frame.close).toHaveBeenCalled();
        expect(created[0].close).toHaveBeenCalled();
    });

    it('decodes it whole, as before, when the browser cannot decode it reduced or gives it smaller than needed', async () => {
        // Sin ImageDecoder (o sin el giro del EXIF): null, y la foto se decodifica entera.
        expect(await decodeReduced(phoneJpeg(8160, 6120), { Decoder: null })).toBeNull();

        // El tamaño pedido es una sugerencia: un octavo (1020 px) se vería borroso.
        const small = decoderGiving([1020, 765]);
        expect(await decodeReduced(phoneJpeg(8160, 6120), { Decoder: small.Decoder, toBitmap: bitmapOf(765, 1020) })).toBeNull();
        expect(small.frame.close).toHaveBeenCalled();

        // Una copia que perdió el giro del EXIF tampoco: la foto quedaría acostada.
        const lying = bitmapOf(2040, 1530);
        expect(await decodeReduced(phoneJpeg(8160, 6120), { Decoder: decoderGiving([2040, 1530]).Decoder, toBitmap: lying })).toBeNull();
        expect((await lying.mock.results[0].value).close).toHaveBeenCalled();

        // Una foto que ya es pequeña, o que no es un JPEG, no se toca.
        const unused = decoderGiving([1920, 1440]);
        expect(await decodeReduced(phoneJpeg(1920, 1440), { Decoder: unused.Decoder })).toBeNull();
        expect(await decodeReduced(new File([ascii('ftypheic…')], 'IMG_0001.HEIC', { type: 'image/heic' }), { Decoder: unused.Decoder })).toBeNull();
        expect(unused.created).toHaveLength(0);
    });

    it('picks the smallest eighth of the photo whose longer side still reaches 1920 px', () => {
        expect(jpegDecodeSize(8160, 6120)).toEqual({ width: 2040, height: 1530 }); // 50 MP: un cuarto
        expect(jpegDecodeSize(4000, 3000)).toEqual({ width: 2000, height: 1500 }); // 12 MP: la mitad
        expect(jpegDecodeSize(3000, 4000)).toEqual({ width: 1500, height: 2000 });
        expect(jpegDecodeSize(12000, 9000)).toEqual({ width: 3000, height: 2250 }); // 108 MP
        expect(jpegDecodeSize(2400, 1800)).toEqual({ width: 2100, height: 1575 }); // 7/8
        expect(jpegDecodeSize(2000, 1500)).toEqual({ width: 2000, height: 1500 }); // 7/8 ya no llega: entera
    });

    it('reads the size of a JPEG from its header, without decoding it', () => {
        expect(jpegSize(cleanJpeg)).toEqual({ width: 1, height: 1 });
        expect(jpegSize(ascii('%PDF-1.7'))).toBeNull();
    });
});

describe('photoFile', () => {
    it('encodes the reviewed canvas itself, without drawing it on a second one', async () => {
        const canvas = { width: 1920, height: 1440, toBlob: vi.fn((done, type, quality) => done(new Blob([jpegWithExif()], { type }))) };

        const file = await photoFile(canvas, 'IMG_0002.jpg');

        expect(canvas.toBlob).toHaveBeenCalledWith(expect.any(Function), 'image/jpeg', 0.8);
        expect(new Uint8Array(await file.arrayBuffer())).toEqual(cleanJpeg);
    });
});
