// US-009, R-PRIV-04: el PDF se sube sin metadatos — ni título, autor,
// asunto, palabras clave, software o fechas (el diccionario Info), ni XMP —
// y sin el EXIF de las fotos que lleva dentro, que puede traer las
// coordenadas GPS del celular. El SHA-256 se calcula sobre el PDF ya
// limpio. Se lee el documento con pdf-lib porque los metadatos pueden ir
// dentro de flujos comprimidos.
import { PDFArray, PDFDict, PDFDocument, PDFName, PDFRawStream, PDFRef, PDFStream } from 'pdf-lib';
import { stripJpegMetadata } from './photos.js';

const METADATA = PDFName.of('Metadata');
const JPEG = PDFName.of('DCTDecode');

const UNCLEANABLE_IMAGES = 'Este PDF trae imágenes que no se pueden limpiar. Expórtelo de nuevo o adjunte fotos en lugar del PDF.';

/** Los filtros de un flujo, en el orden en que se aplican. */
function filtersOf(stream, context) {
    const filter = stream.dict.lookup(PDFName.of('Filter'));
    if (filter instanceof PDFArray) {
        return filter.asArray().map((name) => context.lookup(name));
    }
    return filter instanceof PDFName ? [filter] : [];
}

/**
 * Una foto JPEG sin su EXIF. Si el JPEG va además comprimido con otro
 * filtro, o no se deja leer, el PDF se rechaza: nunca se sube sin limpiar.
 */
function withoutExif(image, context) {
    const filters = filtersOf(image, context);
    if (filters.length !== 1) {
        throw new Error(UNCLEANABLE_IMAGES);
    }
    try {
        return PDFRawStream.of(image.dict, stripJpegMetadata(image.contents));
    } catch {
        throw new Error(UNCLEANABLE_IMAGES);
    }
}

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

    for (const [ref, object] of doc.context.enumerateIndirectObjects()) {
        // XMP: en el catálogo, pero también en páginas o imágenes.
        const dict = object instanceof PDFStream ? object.dict : object;
        const metadata = dict instanceof PDFDict ? dict.get(METADATA) : undefined;
        if (metadata !== undefined) {
            dict.delete(METADATA);
            if (metadata instanceof PDFRef) {
                doc.context.delete(metadata);
            }
        }

        // Las fotos JPEG, con su EXIF.
        if (object instanceof PDFRawStream && filtersOf(object, doc.context).includes(JPEG)) {
            doc.context.assign(ref, withoutExif(object, doc.context));
        }
    }

    return new File([await doc.save({ useObjectStreams: false })], file.name, { type: 'application/pdf' });
}
