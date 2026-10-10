// It. 47a (US-016): la situación de cada obra, en palabras, con su fecha.
import { describe, expect, it } from 'vitest';
import { situationText } from './situation.js';

const today = new Date('2026-10-10T12:00:00');

describe('situationText', () => {
    it.each([
        [{ situation: 'overdue', end_date: '2026-08-31' }, 'Plazo vencido hace 40 días'],
        [{ situation: 'overdue', end_date: '2026-10-09' }, 'Plazo vencido hace 1 día'],
        [{ situation: 'overdue', end_date: '2025-10-10' }, 'Plazo vencido hace 12 meses'],
        [{ situation: 'in_progress', end_date: '2026-10-30' }, 'Vence en 20 días'],
        [{ situation: 'in_progress', end_date: '2026-10-10' }, 'Vence hoy'],
        [{ situation: 'in_progress', end_date: '2027-01-08' }, 'Vence en 3 meses'],
        [{ situation: 'no_end_date', end_date: null }, 'Sin fecha de fin'],
        [{ situation: 'finished', end_date: '2026-08-10' }, 'Terminada hace 2 meses'],
        [{ situation: 'finished', end_date: '2026-10-05' }, 'Terminada hace 5 días'],
    ])('%o se lee "%s"', (item, text) => {
        expect(situationText(item, today)).toBe(text);
    });
});
