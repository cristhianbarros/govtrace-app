// It. 68 — US-059-LEG y US-035: buscar dónde queda una obra por su dirección o
// un sitio conocido. El servidor le pregunta a OpenStreetMap (PlaceSearchTest);
// aquí, la pantalla: se busca al tocar "Buscar" o con Enter, nunca letra a letra
// (la política de Nominatim no admite autocompletar).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PlaceSearch from './PlaceSearch.vue';
import { searchPlaces } from '@/services/api.js';

vi.mock('@/services/api.js');

const CONTRACT = 'CO1.PCCNTR.7654321';
const PLACES = [
    { label: 'Parque de Minca, Minca, Santa Marta, Magdalena', latitude: 11.1427, longitude: -74.1173 },
    { label: 'Calle del Parque, Minca, Santa Marta, Magdalena, 470001', latitude: 11.1431, longitude: -74.1169 },
];
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

function mountSearch() {
    return mount(PlaceSearch, { props: { work: { contract_id: CONTRACT } } });
}

async function search(wrapper, text) {
    await wrapper.get('input#place-search').setValue(text);
    await button(wrapper, 'Buscar').trigger('click');
    await flushPromises();
}

beforeEach(() => vi.resetAllMocks());

describe('Buscar el lugar de la obra (it. 68)', () => {
    it('Escribo dónde queda la obra y el mapa la ubica: searches when "Buscar" is tapped or with Enter, never letter by letter, and choosing a place hands its point over', async () => {
        searchPlaces.mockResolvedValue({ data: PLACES });
        const wrapper = mountSearch();
        expect(wrapper.get('label[for="place-search"]').text()).toBe('Dirección o un sitio conocido');

        await wrapper.get('input#place-search').setValue('Parque de Minca');
        expect(searchPlaces).not.toHaveBeenCalled();
        await button(wrapper, 'Buscar').trigger('click');
        await flushPromises();

        expect(searchPlaces).toHaveBeenCalledWith({ q: 'Parque de Minca', contract_id: CONTRACT });
        const found = wrapper.findAll('[data-test="place"]');
        expect(found.map((place) => place.text())).toEqual(PLACES.map((place) => place.label));
        await found[1].trigger('click');

        expect(wrapper.emitted('found')).toEqual([[{ latitude: 11.1431, longitude: -74.1169 }]]);
        expect(wrapper.get('[role="status"]').text()).toBe('El punto quedó en Calle del Parque. Muévalo en el mapa si hace falta.');
        expect(wrapper.find('[data-test="place"]').exists()).toBe(false);
        // Lo escrito es del formulario (v-model): las palabras del ciudadano van con su informe.
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Parque de Minca']);

        await wrapper.get('input#place-search').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        expect(searchPlaces).toHaveBeenCalledTimes(2);
    });

    it('Si no se encuentra el lugar, la obra se marca en el mapa: says so when nothing is found, and what the server says when the search does not answer', async () => {
        searchPlaces
            .mockResolvedValueOnce({ data: [] })
            .mockRejectedValueOnce({ response: { status: 503, data: { message: 'No pudimos buscar el lugar ahora. Márquelo en el mapa.' } } });
        const wrapper = mountSearch();

        await search(wrapper, 'Hangar de Minca');
        expect(wrapper.get('[role="alert"]').text()).toBe('No encontramos ese lugar. Pruebe con el barrio, un sitio conocido cercano o la calle sin el número, o márquelo en el mapa.');

        await button(wrapper, 'Buscar').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('No pudimos buscar el lugar ahora. Márquelo en el mapa.');
        expect(wrapper.emitted('found')).toBeUndefined();
    });

    it('asks for at least 3 letters before searching, and does not search twice at once', async () => {
        let answer;
        searchPlaces.mockReturnValue(new Promise((resolve) => (answer = resolve)));
        const wrapper = mountSearch();

        await search(wrapper, ' ab ');
        expect(wrapper.get('[role="alert"]').text()).toBe('Escriba al menos 3 letras del lugar.');
        expect(searchPlaces).not.toHaveBeenCalled();

        await wrapper.get('input#place-search').setValue('Parque de Minca');
        await button(wrapper, 'Buscar').trigger('click');
        expect(button(wrapper, 'Buscando…').attributes('disabled')).toBeDefined();
        await wrapper.get('input#place-search').trigger('keydown', { key: 'Enter' });
        expect(searchPlaces).toHaveBeenCalledTimes(1);

        answer({ data: PLACES });
        await flushPromises();
        expect(wrapper.findAll('[data-test="place"]')).toHaveLength(2);
    });

    it('credits OpenStreetMap, whose data it searches', () => {
        const credit = mountSearch().get('a[data-test="osm-credit"]');

        expect(credit.text()).toBe('© OpenStreetMap');
        expect(credit.attributes('href')).toBe('https://www.openstreetmap.org/copyright');
    });
});
