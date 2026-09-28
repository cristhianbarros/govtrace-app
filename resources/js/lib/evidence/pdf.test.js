// @vitest-environment node
// Iteración 16 — US-009, R-PRIV-04: el PDF que se sube no conserva autor,
// software ni fechas. Tampoco su XMP, ni el EXIF de las fotos que lleva
// dentro (un acta de Word con fotos del celular trae sus coordenadas GPS).
// Los metadatos pueden ir dentro de flujos de objetos comprimidos, así que
// se limpia leyendo el PDF, no buscando texto.
import { describe, expect, it } from 'vitest';
import { PDFArray, PDFDocument, PDFName, PDFRawStream } from 'pdf-lib';
import { cleanJpeg, contains, jpegWithExif } from './jpeg-fixtures.js';
import { cleanPdfMetadata } from './pdf.js';

/** Un PDF de una página con una foto del celular, EXIF con GPS incluido. */
async function pdfWithPhoto() {
    const doc = await PDFDocument.create();
    const photo = await doc.embedJpg(jpegWithExif());
    doc.addPage().drawImage(photo, { x: 0, y: 0, width: 50, height: 50 });
    await doc.flush(); // pdf-lib incrusta la imagen recién aquí
    return doc;
}

const asFile = async (doc) => new File([await doc.save()], 'acta.pdf', { type: 'application/pdf' });

/** Los bytes de cada imagen del PDF. */
async function imagesIn(file) {
    const doc = await PDFDocument.load(await file.arrayBuffer());
    return doc.context
        .enumerateIndirectObjects()
        .map(([, object]) => object)
        .filter((object) => object instanceof PDFRawStream && object.dict.get(PDFName.of('Subtype')) === PDFName.of('Image'))
        .map((image) => image.contents);
}

/** Un PDF de una página como el que exporta Word: autor, software, fechas y XMP. */
async function pdfFromWord() {
    const doc = await PDFDocument.create();
    doc.addPage();
    doc.setTitle('Acta de visita');
    doc.setAuthor('Carlos Gómez');
    doc.setSubject('Veeduría');
    doc.setKeywords(['obra', 'Calle 30']);
    doc.setCreator('Microsoft® Word para Microsoft 365');
    doc.setProducer('Microsoft® Word para Microsoft 365');
    doc.setCreationDate(new Date('2026-09-20T08:00:00Z'));
    doc.setModificationDate(new Date('2026-09-21T09:30:00Z'));
    const xmp = doc.context.stream('<x:xmpmeta><dc:creator>Carlos Gómez</dc:creator><xmp:CreatorTool>Word</xmp:CreatorTool></x:xmpmeta>', {
        Type: 'Metadata',
        Subtype: 'XML',
    });
    doc.catalog.set(PDFName.of('Metadata'), doc.context.register(xmp));

    return new File([await doc.save()], 'acta.pdf', { type: 'application/pdf' });
}

describe('cleanPdfMetadata', () => {
    it('uploads the PDF without author, software, dates or XMP', async () => {
        const cleaned = await cleanPdfMetadata(await pdfFromWord());
        const bytes = new Uint8Array(await cleaned.arrayBuffer());
        const raw = Buffer.from(bytes).toString('latin1');
        const doc = await PDFDocument.load(bytes, { updateMetadata: false });

        expect(cleaned.type).toBe('application/pdf');
        expect(cleaned.name).toBe('acta.pdf');
        expect(doc.getAuthor()).toBeUndefined();
        expect(doc.getCreator()).toBeUndefined();
        expect(doc.getProducer()).toBeUndefined();
        expect(doc.getCreationDate()).toBeUndefined();
        expect(doc.getModificationDate()).toBeUndefined();
        expect(doc.getTitle()).toBeUndefined();
        expect(doc.catalog.get(PDFName.of('Metadata'))).toBeUndefined();
        for (const leak of ['/Author', '/Creator', '/Producer', '/CreationDate', '/ModDate', 'xmpmeta', 'Carlos', 'Word']) {
            expect(raw).not.toContain(leak);
        }
        // El documento sigue siendo el mismo.
        expect(doc.getPageCount()).toBe(1);
    });

    it('removes the EXIF, GPS coordinates included, of the photos inside the PDF', async () => {
        const source = await asFile(await pdfWithPhoto());
        expect(contains(new Uint8Array(await source.arrayBuffer()), 'GPSLatitude')).toBe(true);

        const cleaned = await cleanPdfMetadata(source);

        expect(contains(new Uint8Array(await cleaned.arrayBuffer()), 'GPSLatitude')).toBe(false);
        // La foto sigue ahí, byte a byte, sin su EXIF.
        expect(await imagesIn(cleaned)).toEqual([cleanJpeg]);
    });

    it('rejects a PDF with a photo it cannot clean, instead of uploading it with its EXIF', async () => {
        // Un JPEG comprimido además con Flate: habría que descomprimirlo para limpiarlo.
        const doc = await pdfWithPhoto();
        const [, image] = doc.context.enumerateIndirectObjects().find(([, object]) => object instanceof PDFRawStream);
        image.dict.set(PDFName.of('Filter'), PDFArray.withContext(doc.context));
        image.dict.lookup(PDFName.of('Filter')).push(PDFName.of('FlateDecode'));
        image.dict.lookup(PDFName.of('Filter')).push(PDFName.of('DCTDecode'));

        await expect(cleanPdfMetadata(await asFile(doc))).rejects.toThrow(
            'Este PDF trae imágenes que no se pueden limpiar. Expórtelo de nuevo o adjunte fotos en lugar del PDF.',
        );
    });

    it('rejects a file that is not a readable PDF', async () => {
        await expect(cleanPdfMetadata(new File(['no soy un pdf'], 'acta.pdf', { type: 'application/pdf' }))).rejects.toThrow(
            'No se pudo leer el PDF. Adjunte un PDF sin contraseña ni daños.',
        );
    });
});
