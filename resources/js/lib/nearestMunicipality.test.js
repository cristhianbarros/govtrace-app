// Iteración 59 — US-066: el municipio cuya cabecera está más cerca de donde
// está el ciudadano, calculado en su navegador: la ubicación no viaja.
import { describe, expect, it } from 'vitest';
import { nearestMunicipality } from './nearestMunicipality.js';

const MUNICIPALITIES = [
    { code: '47001', name: 'Santa Marta', department: 'Magdalena', latitude: 11.204679, longitude: -74.199829 },
    { code: '47189', name: 'Ciénaga', department: 'Magdalena', latitude: 11.006667, longitude: -74.247222 },
    { code: '05001', name: 'Medellín', department: 'Antioquia', latitude: 6.244203, longitude: -75.581212 },
];

describe('nearestMunicipality', () => {
    it('Usar mi ubicación propone el municipio, sin enviarla: the one whose seat is nearest', () => {
        expect(nearestMunicipality(MUNICIPALITIES, { latitude: 11.1427, longitude: -74.1173 }).code).toBe('47001');
        expect(nearestMunicipality(MUNICIPALITIES, { latitude: 11.02, longitude: -74.25 }).code).toBe('47189');
        expect(nearestMunicipality(MUNICIPALITIES, { latitude: 6.25806, longitude: -75.60375 }).code).toBe('05001');
    });

    it('proposes nothing without municipalities', () => {
        expect(nearestMunicipality([], { latitude: 11.1, longitude: -74.1 })).toBeNull();
    });
});
