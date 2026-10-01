// El reporte de una obra (US-008): lo que el veedor dice que vio, y cómo viaja
// al servidor. Lo comparten "Nuevo Reporte" y el reporte en nombre de una
// organización (it. 43g).

export const CLASSIFICATIONS = ['Avance', 'Retraso', 'Abandono'];

// It. 40c: qué significa cada una, en una línea. Provisional: la valida una veeduría,
// porque cambia el color del mapa (US-027) — docs/ux-analisis.md, decisión 4.
export const MEANING = {
    Avance: 'La obra avanza: hay trabajo o cambios desde la última vez.',
    Retraso: 'Va más lenta de lo previsto, o está detenida por ahora.',
    Abandono: 'No hay nadie trabajando y la obra parece dejada.',
};

export const MAX_COMMENT_LENGTH = 500;

/** The report as the server receives it (StoreReportRequest): its fields, and each file with its SHA-256. */
export function reportFormData({ fields, files, hashes }) {
    const form = new FormData();
    for (const [name, value] of Object.entries(fields)) {
        form.append(name, value);
    }
    files.forEach((file, index) => {
        form.append('files[]', file);
        form.append('hashes[]', hashes[index]);
    });
    return form;
}
