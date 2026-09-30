// Cómo se leen en pantalla fechas y pesos colombianos.

const cop = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
// Año con 4 cifras: en un log de auditoría "26" no alcanza.
const dateTime = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'America/Bogota' });

/** "$1.250.000.000" (US-017): sin el espacio que Intl pone tras el signo. */
export const formatCop = (value) => (value === null || value === undefined ? '—' : cop.format(value).replace(/\s/u, ''));

/** "2026-01-15" → "15/01/2026", sin pasar por zonas horarias. */
export const formatDate = (isoDate) => (isoDate ? isoDate.split('-').reverse().join('/') : '—');

const day = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'America/Bogota' });

/** Una hora ISO → "30/09/2026", el día que era en Colombia. */
export const formatDay = (iso) => day.format(new Date(iso));

/** Una hora ISO, en la hora de Colombia. */
export const formatDateTime = (iso) => dateTime.format(new Date(iso));

const monthOfYear = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric', timeZone: 'UTC' });

/** "2026-09" → "septiembre de 2026". */
export const formatMonth = (yearMonth) => monthOfYear.format(new Date(`${yearMonth}-01T00:00:00Z`));

/** "1234.5" → "1.234,5" (US-022, US-004): XLM exactos, sin pasar por un número. */
export function formatXlm(amount) {
    const [whole, fraction] = String(amount).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fraction ? `${grouped},${fraction}` : grouped;
}
