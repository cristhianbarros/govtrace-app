// Iteración 59 — US-066: en el Inicio, el ciudadano busca la obra y GovTrace
// le dice qué veedurías pueden atender su informe, con reglas públicas. El
// servidor ordena (WorkFinderTest); aquí, la pantalla.
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WorkFinder from './WorkFinder.vue';
import { fetchWhoCanHelp, fetchWorkMunicipalities, searchWorks } from '@/services/api.js';

vi.mock('@/services/api.js');

enableAutoUnmount(afterEach);

const MUNICIPALITIES = [
    { code: '47189', name: 'Ciénaga', department: 'Magdalena', latitude: 11.006667, longitude: -74.247222 },
    { code: '47001', name: 'Santa Marta', department: 'Magdalena', latitude: 11.204679, longitude: -74.199829 },
];
const MINCA = { contract_id: 'CO1.PCCNTR.7654321', name: 'Construcción de la vía a Minca', municipality: 'Santa Marta', entity_name: 'Alcaldía Distrital de Santa Marta' };
const CONTRACT = { contract_id: MINCA.contract_id, name: MINCA.name, municipality: 'Santa Marta', department: 'Magdalena', entity_name: MINCA.entity_name };
const MAGDALENA = { name: 'Veeduría del Magdalena', url: 'http://veeduria-magdalena.govtrace.localhost/contract/CO1.PCCNTR.7654321#informar', reasons: ['Ya sigue esta obra: 1 evidencia publicada', 'Vigila todo Magdalena'], suggested: true };
const SANTA_MARTA = { name: 'Veeduría Santa Marta', url: 'http://veeduria-smr.govtrace.localhost/contract/CO1.PCCNTR.7654321#informar', reasons: ['Vigila Santa Marta'], suggested: false };

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function openFinder() {
    const wrapper = mount(WorkFinder, { attachTo: document.body });
    await flushPromises();
    return wrapper;
}

async function search(wrapper, words) {
    await wrapper.get('input#work-words').setValue(words);
    vi.advanceTimersByTime(400);
    await flushPromises();
}

async function choose(wrapper, who) {
    fetchWhoCanHelp.mockResolvedValue(who);
    searchWorks.mockResolvedValue({ data: [MINCA], has_more: false });
    await search(wrapper, 'minca');
    await wrapper.get('[data-test="work"] button').trigger('click');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fetchWorkMunicipalities.mockResolvedValue(MUNICIPALITIES);
    searchWorks.mockResolvedValue({ data: [], has_more: false });
});

afterEach(() => {
    vi.useRealTimers();
});

describe('¿Qué obra quiere reportar? (it. 59)', () => {
    it('El ciudadano busca una obra en el Inicio: by name once the person stops writing, by municipality, and 20 more at a time', async () => {
        searchWorks.mockResolvedValue({ data: [MINCA], has_more: true });
        const wrapper = await openFinder();
        expect(wrapper.get('h2').text()).toBe('¿Qué obra quiere reportar?');
        expect(wrapper.findAll('select#work-municipality option').map((option) => option.text())).toEqual(['Todos los municipios', 'Ciénaga (Magdalena)', 'Santa Marta (Magdalena)']);

        await search(wrapper, 'mi');
        expect(searchWorks).not.toHaveBeenCalled();
        await search(wrapper, 'minca');
        expect(searchWorks).toHaveBeenLastCalledWith({ q: 'minca' });
        expect(wrapper.get('[data-test="work"]').text()).toBe('Construcción de la vía a Minca Santa Marta · Alcaldía Distrital de Santa Marta');

        searchWorks.mockResolvedValue({ data: [{ ...MINCA, contract_id: 'CO1.PCCNTR.2', name: 'Andén de Bonda' }], has_more: false });
        await wrapper.get('[data-test="more"]').trigger('click');
        await flushPromises();
        expect(searchWorks).toHaveBeenLastCalledWith({ q: 'minca', page: 2 });
        expect(wrapper.findAll('[data-test="work"]')).toHaveLength(2);

        await wrapper.get('select#work-municipality').setValue('47001');
        await flushPromises();
        expect(searchWorks).toHaveBeenLastCalledWith({ q: 'minca', municipality: '47001' });
    });

    it('GovTrace sugiere la veeduría que ya sigue la obra: "Le sugerimos", its reasons, and the others below', async () => {
        const wrapper = await openFinder();
        await choose(wrapper, { contract: CONTRACT, organizations: [MAGDALENA, SANTA_MARTA] });

        expect(fetchWhoCanHelp).toHaveBeenCalledWith('CO1.PCCNTR.7654321');
        const suggested = wrapper.get('[data-test="suggested"]');
        expect(suggested.text()).toContain('Le sugerimos');
        expect(suggested.text()).toContain('Veeduría del Magdalena');
        expect(suggested.findAll('li').map((reason) => reason.text())).toEqual(['Ya sigue esta obra: 1 evidencia publicada', 'Vigila todo Magdalena']);
        expect(wrapper.get('[data-test="also"] h4').text()).toBe('También cubren esta obra');
        expect(wrapper.get('[data-test="also"]').text()).toContain('Veeduría Santa Marta');
        // Las reglas, en una frase, junto a la sugerencia.
        expect(wrapper.get('[data-test="rules"]').text()).toContain('primero la que ya sigue la obra');
    });

    it('Un toque lleva al formulario de esa veeduría: the link goes to the work in its site, at the form', async () => {
        const wrapper = await openFinder();
        await choose(wrapper, { contract: CONTRACT, organizations: [MAGDALENA, SANTA_MARTA] });

        expect(wrapper.findAll('a').filter((link) => link.text() === 'Informar a esta veeduría').map((link) => link.attributes('href'))).toEqual([MAGDALENA.url, SANTA_MARTA.url]);
    });

    it('En un empate, las veedurías se muestran como iguales: no "Le sugerimos"', async () => {
        const wrapper = await openFinder();
        await choose(wrapper, { contract: CONTRACT, organizations: [{ ...SANTA_MARTA, name: 'Veeduría Bonda' }, SANTA_MARTA] });

        expect(wrapper.find('[data-test="suggested"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="equals"] h4').text()).toBe('Estas veedurías cubren esta obra, en igualdad de condiciones');
        expect(wrapper.findAll('[data-test="equals"] [data-test="organization"]')).toHaveLength(2);
    });

    it('Si ninguna veeduría cubre la obra: it says so, with the Personería, the Contraloría and the request for an alta', async () => {
        const wrapper = await openFinder();
        await choose(wrapper, { contract: { ...CONTRACT, municipality: 'Barranquilla', department: 'Atlántico' }, organizations: [] });

        const none = wrapper.get('[data-test="none"]');
        expect(none.text()).toContain('Ninguna veeduría de GovTrace cubre esta obra todavía.');
        expect(none.text()).toContain('La Personería de Barranquilla');
        expect(none.text()).toContain('Ley 850 de 2003, art. 3');
        expect(none.get('a[href="tel:199"]').exists()).toBe(true);
        expect(none.get('a[href="#pedir-alta"]').text()).toBe('Pida el alta de su veeduría en GovTrace');
    });

    it('goes back to the results', async () => {
        const wrapper = await openFinder();
        await choose(wrapper, { contract: CONTRACT, organizations: [SANTA_MARTA] });

        await button(wrapper, '← Volver a los resultados').trigger('click');

        expect(wrapper.find('[data-test="who"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="work"]')).toHaveLength(1);
    });

    it('Usar mi ubicación propone el municipio, sin enviarla: the nearest seat is asked to be confirmed, and no request carries the location', async () => {
        const getCurrentPosition = vi.fn((success) => success({ coords: { latitude: 11.1427, longitude: -74.1173, accuracy: 900 } }));
        vi.stubGlobal('navigator', { geolocation: { getCurrentPosition } });
        try {
            const wrapper = await openFinder();
            await button(wrapper, 'Usar mi ubicación').trigger('click');
            await flushPromises();

            expect(wrapper.get('[data-test="proposal"]').text()).toContain('¿Está en Santa Marta (Magdalena)?');
            await button(wrapper, 'Sí, buscar allí').trigger('click');
            await flushPromises();
            expect(wrapper.get('select#work-municipality').element.value).toBe('47001');
            expect(searchWorks).toHaveBeenLastCalledWith({ municipality: '47001' });

            const everyCall = [...searchWorks.mock.calls, ...fetchWorkMunicipalities.mock.calls, ...fetchWhoCanHelp.mock.calls];
            expect(JSON.stringify(everyCall)).not.toMatch(/11\.14|74\.11|latitude|longitude/);
        } finally {
            vi.unstubAllGlobals();
        }
    });

    it('asks to choose the municipality when the browser does not give the location', async () => {
        vi.stubGlobal('navigator', { geolocation: { getCurrentPosition: (success, failure) => failure({ code: 1 }) } });
        try {
            const wrapper = await openFinder();
            await button(wrapper, 'Usar mi ubicación').trigger('click');
            await flushPromises();

            expect(wrapper.get('[role="alert"]').text()).toBe('No pudimos saber dónde está. Elija su municipio en la lista.');
        } finally {
            vi.unstubAllGlobals();
        }
    });

    it('La búsqueda no abre una sesión ni guarda la obra en la dirección: the address of the page does not change', async () => {
        const before = window.location.href;
        const wrapper = await openFinder();
        await choose(wrapper, { contract: CONTRACT, organizations: [SANTA_MARTA] });

        expect(wrapper.find('[data-test="who"]').exists()).toBe(true);
        expect(window.location.href).toBe(before);
    });
});
