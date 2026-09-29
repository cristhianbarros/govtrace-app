// Cómo se leen en pantalla fechas y pesos colombianos.

const cop = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
// Año con 4 cifras: en un log de auditoría "26" no alcanza.
const dateTime = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'America/Bogota' });

/** "$1.250.000.000" (US-017): sin el espacio que Intl pone tras el signo. */
export const formatCop = (value) => (value === null || value === undefined ? '—' : cop.format(value).replace(/\s/u, ''));

/** "2026-01-15" → "15/01/2026", sin pasar por zonas horarias. */
export const formatDate = (isoDate) => (isoDate ? isoDate.split('-').reverse().join('/') : '—');

/** Una hora ISO, en la hora de Colombia. */
export const formatDateTime = (iso) => dateTime.format(new Date(iso));
