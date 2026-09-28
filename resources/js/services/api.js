// El API de la organización, en su propio subdominio (routes/tenant.php).
// Siempre JSON: un 422 trae el mensaje de la regla que no se cumplió, con
// las mismas palabras que el dominio (ReportValidationException).
import axios from 'axios';

const http = axios.create({
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

/** US-016: obras del territorio que el veedor puede reportar. */
export async function searchContracts(keyword) {
    const { data } = await http.get('/contracts/search', { params: { q: keyword } });
    return data.data;
}

/** US-008: el reporte con sus archivos y sus SHA-256, como multipart. */
export async function sendReport(form) {
    const { data } = await http.post('/reports', form);
    return data;
}
