// Iteración 45f — la ubicación del veedor nunca viaja en la URL: las URL
// quedan en los registros de acceso del proxy y del servidor web, con la hora
// y la IP. "Obras cercanas" la manda en el cuerpo de un POST.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchNearbyWorksites } from './api.js';
import source from './api.js?raw';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() }));
vi.mock('axios', () => ({ default: { create: () => http } }));

beforeEach(() => {
    vi.resetAllMocks();
});

describe('El API de la organización', () => {
    it('asks for the nearby worksites with the location in the body of a POST', async () => {
        http.post.mockResolvedValue({ data: { data: [{ worksite_id: 1, distance_meters: 26 }] } });

        expect(await fetchNearbyWorksites(6.2608774, -75.6076621)).toEqual([{ worksite_id: 1, distance_meters: 26 }]);
        expect(http.post).toHaveBeenCalledWith('/worksites/nearby', { latitude: 6.2608774, longitude: -75.6076621 });
        expect(http.get).not.toHaveBeenCalled();
    });

    it('never sends a location in the URL of a GET', () => {
        const getRequests = source.split('\n').filter((line) => line.includes('http.get('));

        expect(getRequests.length).toBeGreaterThan(0);
        expect(getRequests.filter((line) => /latitude|longitude/.test(line))).toEqual([]);
    });
});
