// Iteración 16 — US-008: la posición se toma del GPS del teléfono, con alta
// precisión y sin reutilizar una lectura vieja. Precisión mínima fija de
// 50 m (R-CFG-02), la misma que valida el servidor.
import { describe, expect, it, vi } from 'vitest';
import { GPS_DENIED_MESSAGE, capturePosition, imprecisionMessage, isPreciseEnough } from './geolocation.js';

const reading = (accuracy) => ({ coords: { latitude: 11.2419, longitude: -74.199, accuracy }, timestamp: Date.parse('2026-09-28T15:00:00Z') });

describe('capturePosition', () => {
    it('asks the phone for a fresh, high-accuracy position', async () => {
        const geolocation = { getCurrentPosition: vi.fn((ok) => ok(reading(15))) };

        const position = await capturePosition(geolocation);

        expect(position).toEqual({ latitude: 11.2419, longitude: -74.199, accuracy: 15, capturedAt: '2026-09-28T15:00:00.000Z' });
        expect(geolocation.getCurrentPosition.mock.calls[0][2]).toMatchObject({ enableHighAccuracy: true, maximumAge: 0 });
    });

    it('explains why the GPS is required when the permission is denied', async () => {
        const geolocation = { getCurrentPosition: vi.fn((_ok, fail) => fail({ code: 1, PERMISSION_DENIED: 1 })) };

        await expect(capturePosition(geolocation)).rejects.toThrow(
            'GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.',
        );
        expect(GPS_DENIED_MESSAGE).toMatch(/^GovTrace requiere acceso/);
    });

    it('also asks to enable the GPS when the phone has no geolocation at all', async () => {
        await expect(capturePosition(undefined)).rejects.toThrow(GPS_DENIED_MESSAGE);
    });
});

describe('Precisión mínima del GPS de 50 m', () => {
    it.each([
        [50, true],
        [51, false],
    ])('%i m', (accuracy, precise) => {
        expect(isPreciseEnough(accuracy)).toBe(precise);
    });

    it('asks to retry with the same words as the server', () => {
        expect(imprecisionMessage(51)).toBe('La precisión del GPS es de 51 m y se requieren 50 m o menos. Espere a tener mejor señal y vuelva a intentarlo.');
        expect(imprecisionMessage(51.25)).toBe('La precisión del GPS es de 51.25 m y se requieren 50 m o menos. Espere a tener mejor señal y vuelva a intentarlo.');
    });
});
