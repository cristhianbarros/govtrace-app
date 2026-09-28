// @vitest-environment node
// Iteración 16 — US-009, R-PRIV-04: el PDF que se sube no conserva autor,
// software ni fechas. Tampoco su XMP. Los metadatos pueden ir dentro de
// flujos de objetos comprimidos, así que se limpia leyendo el PDF, no
// buscando texto.
import { describe, expect, it } from 'vitest';
import { PDFDocument, PDFName } from 'pdf-lib';
import { cleanPdfMetadata } from './pdf.js';

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

    it('rejects a file that is not a readable PDF', async () => {
        await expect(cleanPdfMetadata(new File(['no soy un pdf'], 'acta.pdf', { type: 'application/pdf' }))).rejects.toThrow(
            'No se pudo leer el PDF. Adjunte un PDF sin contraseña ni daños.',
        );
    });
});
