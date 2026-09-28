// Cómo se leen en pantalla fechas y pesos colombianos.

const cop = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
const dateTime = new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Bogota' });

export const formatCop = (value) => (value === null || value === undefined ? '—' : cop.format(value));

/** "2026-01-15" → "15/01/2026", sin pasar por zonas horarias. */
export const formatDate = (isoDate) => (isoDate ? isoDate.split('-').reverse().join('/') : '—');

/** Una hora ISO, en la hora de Colombia. */
export const formatDateTime = (iso) => dateTime.format(new Date(iso));
