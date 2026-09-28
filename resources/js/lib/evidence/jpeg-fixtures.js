// Solo para los tests (entorno node): el JPEG de tests/fixtures/evidence y
// el mismo con un segmento EXIF con coordenadas GPS.
import { readFileSync } from 'node:fs';

export const cleanJpeg = new Uint8Array(readFileSync(new URL('../../../../tests/fixtures/evidence/foto.jpg', import.meta.url)));

export const ascii = (text) => new TextEncoder().encode(text);

/** El JPEG de prueba con un APP1 "Exif" con coordenadas, justo después del SOI. */
export function jpegWithExif() {
    const payload = new Uint8Array([...ascii('Exif\0\0'), ...ascii('MM\0*GPSLatitude=11.2408;GPSLongitude=-74.1990')]);
    const app1 = new Uint8Array([0xff, 0xe1, 0, payload.length + 2, ...payload]);
    return new Uint8Array([...cleanJpeg.slice(0, 2), ...app1, ...cleanJpeg.slice(2)]);
}

export const contains = (bytes, text) => Buffer.from(bytes).includes(Buffer.from(text));
