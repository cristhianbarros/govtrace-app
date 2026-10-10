// It. 47a (US-016): la situación de cada obra de la lista, en palabras y con
// su fecha, como la lee un veedor: "Plazo vencido hace 40 días", "Vence en
// 3 meses", "Terminada hace 2 meses". Hasta 60 días se cuentan días; después, meses.

const DAY = 24 * 60 * 60 * 1000;

const startOfDay = (date) => Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());
const parse = (isoDate) => {
    const [year, month, day] = isoDate.split('-').map(Number);
    return Date.UTC(year, month - 1, day);
};

function span(days) {
    if (days <= 60) {
        return days === 1 ? '1 día' : `${days} días`;
    }
    const months = Math.round(days / 30);
    return months === 1 ? '1 mes' : `${months} meses`;
}

export function situationText({ situation, end_date: endDate }, today = new Date()) {
    if (situation === 'no_end_date' || !endDate) {
        return 'Sin fecha de fin';
    }
    const days = Math.round((parse(endDate) - startOfDay(today)) / DAY);

    if (situation === 'overdue') {
        return `Plazo vencido hace ${span(-days)}`;
    }
    if (situation === 'finished') {
        return `Terminada hace ${span(-days)}`;
    }
    return days === 0 ? 'Vence hoy' : `Vence en ${span(days)}`;
}
