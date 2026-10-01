// Iteración 36, it. 40d — la página del dominio central. No hay un mapa global
// (R-MAP-01): es la puerta de entrada — qué es GovTrace, cómo funciona, y el
// directorio de veedurías, cada una con el enlace a su mapa (V5).
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Home from './Home.vue';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const ORGANIZATIONS = [
    { name: 'Ojo Ciudadano Ciénaga', territory: 'Ciénaga', url: 'http://ojo-cienaga.govtrace.localhost:8080/', suspended: true },
    { name: 'Veeduría Ciudadana Santa Marta', territory: 'Magdalena', url: 'http://veeduria-smr.govtrace.localhost:8080/', suspended: false },
];

describe('Inicio del dominio central', () => {
    it('says what GovTrace is and how it works, in three steps and plain words', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });

        expect(wrapper.get('header').text()).toContain('GovTrace');
        expect(wrapper.get('h1').text()).toBe('Veeduría ciudadana de obras públicas');
        const steps = wrapper.findAll('[data-test="how"] li').map((step) => step.text());
        expect(steps).toHaveLength(3);
        expect(steps[1]).toContain('sello digital');
        expect(wrapper.text()).not.toContain('subdominio');
    });

    it('Llegar al mapa de una veeduría desde el Inicio: each veeduría with its territory and the way to its map', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });
        const listed = wrapper.findAll('[data-test="organization"]');

        expect(listed.map((row) => row.get('a').attributes('href'))).toEqual(['http://ojo-cienaga.govtrace.localhost:8080/', 'http://veeduria-smr.govtrace.localhost:8080/']);
        expect(listed[1].text()).toContain('Veeduría Ciudadana Santa Marta');
        expect(listed[1].text()).toContain('Magdalena');
        expect(listed[1].get('a').text()).toBe('Ver su mapa de obras');
        expect(listed[0].text()).toContain('Suspendida por ahora: sus evidencias siguen a la vista.');
    });

    it('says so when there is no veeduría yet, and keeps the way in for the administrators at the bottom', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.text()).toContain('Aún no hay veedurías publicando en GovTrace.');
        expect(wrapper.get('footer a[href="/login"]').text()).toBe('Acceso para administradores de GovTrace');
    });
});

describe('La política de datos (it. 44e)', () => {
    it('La política está enlazada donde se entra: the footer of the Home', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.findAll('a').find((link) => link.text() === 'Política de tratamiento de datos').attributes('href')).toBe('/privacidad');
    });
});

describe('Más imágenes en el Inicio (it. 40f)', () => {
    it('draws each step of how it works: the photo, its seal and the map', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });

        expect(wrapper.findAll('[data-test="how"] li svg').map((art) => art.attributes('data-illustration'))).toEqual(['evidence', 'validator', 'map']);
    });

    it('draws the neighbors waiting when there is no veeduría yet', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.get('#veedurias [data-test="empty"]').text()).toBe('Aún no hay veedurías publicando en GovTrace.');
        expect(wrapper.get('#veedurias [data-test="empty"] svg').attributes('data-illustration')).toBe('team');
    });
});
