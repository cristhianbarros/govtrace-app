// Iteración 18 — US-012 (UI): el territorio que vigila la organización.
// Buscar departamentos y municipios (desde 3 caracteres, 300 ms sin escribir),
// sumarlos o quitarlos, y guardar.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Territory from './Territory.vue';
import { fetchTerritory, saveTerritory, searchTerritories } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const EMPTY = 'Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia.';
const magdalena = { code: '47', name: 'Magdalena', kind: 'Departamento' };
const medellin = { code: '05001', name: 'Medellín (Antioquia)', kind: 'Municipio' };
const santaMarta = { code: '47001', name: 'Santa Marta (Magdalena)', kind: 'Municipio' };

async function openTerritory(current = []) {
    fetchTerritory.mockResolvedValue(current);
    const wrapper = mount(Territory);
    await flushPromises();
    return wrapper;
}

async function searchAndPick(wrapper, keyword, results) {
    searchTerritories.mockResolvedValue(results);
    await wrapper.get('#territory-search').setValue(keyword);
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
    await wrapper.findAll('[data-test="territory-result"]').find((result) => result.text().includes(results[0].name)).trigger('click');
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);
const chosen = (wrapper) => wrapper.findAll('[data-test="territory-chosen"]').map((row) => row.text());

beforeEach(() => {
    vi.resetAllMocks();
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('Territorio', () => {
    it('shows that it is loading the territory', () => {
        fetchTerritory.mockReturnValue(new Promise(() => {}));

        expect(mount(Territory).text()).toContain('Cargando territorio…');
    });

    it('says when it could not load it, and lets retry', async () => {
        fetchTerritory.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([santaMarta]);
        const wrapper = mount(Territory);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(chosen(wrapper)[0]).toContain('Santa Marta (Magdalena)');
    });

    it('shows what the organization watches today', async () => {
        const wrapper = await openTerritory([magdalena, santaMarta]);

        expect(chosen(wrapper)).toEqual(['Magdalena · DepartamentoQuitar', 'Santa Marta (Magdalena) · MunicipioQuitar']);
    });

    it('Configuración de varios territorios: finds, adds and saves "Magdalena" and "Medellín"', async () => {
        const wrapper = await openTerritory([]);
        expect(wrapper.text()).toContain('Aún no ha elegido territorio.');

        await searchAndPick(wrapper, 'Magd', [magdalena]);
        expect(searchTerritories).toHaveBeenCalledWith('Magd');
        await searchAndPick(wrapper, 'Medell', [medellin]);
        saveTerritory.mockResolvedValue({ message: 'Territorio guardado. Los contratos de SECOP II se están actualizando.' });
        await button(wrapper, 'Guardar territorio').trigger('click');
        await flushPromises();

        expect(saveTerritory).toHaveBeenCalledWith(['47', '05001']);
        expect(wrapper.get('[role="status"]').text()).toBe('Territorio guardado. Los contratos de SECOP II se están actualizando.');
    });

    it('does not add the same place twice', async () => {
        const wrapper = await openTerritory([magdalena]);

        await searchAndPick(wrapper, 'Magd', [magdalena]);

        expect(chosen(wrapper)).toHaveLength(1);
    });

    it('No se puede guardar un territorio vacío: says so and does not save', async () => {
        const wrapper = await openTerritory([santaMarta]);

        await button(wrapper, 'Quitar').trigger('click');
        await button(wrapper, 'Guardar territorio').trigger('click');

        expect(saveTerritory).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe(EMPTY);
    });

    it('searches only from 3 characters, and says when nothing matches', async () => {
        const wrapper = await openTerritory([magdalena]);

        await wrapper.get('#territory-search').setValue('Ma');
        await vi.advanceTimersByTimeAsync(1000);
        expect(searchTerritories).not.toHaveBeenCalled();

        searchTerritories.mockResolvedValue([]);
        await wrapper.get('#territory-search').setValue('Atlántida');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.text()).toContain('No se encontraron departamentos ni municipios.');
    });

    it('shows why the server refused the territory', async () => {
        const message = 'El código geográfico no pertenece a la tabla oficial de departamentos y municipios.';
        saveTerritory.mockRejectedValue({ response: { status: 422, data: { message, errors: { codes: [message] } } } });
        const wrapper = await openTerritory([magdalena]);

        await button(wrapper, 'Guardar territorio').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe(message);
    });
});
