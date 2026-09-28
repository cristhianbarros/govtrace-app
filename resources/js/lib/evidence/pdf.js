// US-009, R-PRIV-04: el PDF se sube sin metadatos — ni título, autor,
// asunto, palabras clave, software o fechas (el diccionario Info), ni XMP —
// y el SHA-256 se calcula sobre el PDF ya limpio. Se lee el documento con
// pdf-lib porque los metadatos pueden ir dentro de flujos comprimidos.
import { PDFDict, PDFDocument, PDFName, PDFRef, PDFStream } from 'pdf-lib';

const METADATA = PDFName.of('Metadata');

/** @returns {Promise<File>} el mismo PDF, sin metadatos */
export async function cleanPdfMetadata(file) {
    let doc;
    try {
        // updateMetadata: false — si no, pdf-lib se anota como software y pone fechas.
        doc = await PDFDocument.load(await file.arrayBuffer(), { updateMetadata: false });
    } catch {
        throw new Error('No se pudo leer el PDF. Adjunte un PDF sin contraseña ni daños.');
    }

    const info = doc.context.trailerInfo.Info;
    doc.context.trailerInfo.Info = undefined;
    if (info instanceof PDFRef) {
        doc.context.delete(info);
    }

    // XMP: en el catálogo, pero también en páginas o imágenes.
    for (const [, object] of doc.context.enumerateIndirectObjects()) {
        const dict = object instanceof PDFStream ? object.dict : object;
        const metadata = dict instanceof PDFDict ? dict.get(METADATA) : undefined;
        if (metadata !== undefined) {
            dict.delete(METADATA);
            if (metadata instanceof PDFRef) {
                doc.context.delete(metadata);
            }
        }
    }

    return new File([await doc.save({ useObjectStreams: false })], file.name, { type: 'application/pdf' });
}
