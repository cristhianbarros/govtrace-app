// Iteración 40e — US-027: el sitio de la veeduría se recorre con pestañas —
// Obras, Estadísticas y Validar —, no con botones sueltos al pie del mapa.
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SectionTabs from './SectionTabs.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const tabs = (wrapper) => wrapper.findAll('a').map((link) => [link.text(), link.attributes('href'), link.attributes('aria-current') ?? null]);

afterEach(() => {
    page.url = '/admin/inbox';
});

describe('Las pestañas del sitio de la veeduría', () => {
    it('Las secciones del sitio de la veeduría se recorren con pestañas: the three, with the current one marked', () => {
        page.url = '/stats';
        const wrapper = mount(SectionTabs);

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Secciones de la veeduría');
        expect(tabs(wrapper)).toEqual([
            ['Obras', '/', null],
            ['Estadísticas', '/stats', 'page'],
            ['Validar', '/verify', null],
        ]);
    });

    it('marks "Obras" on the map and on a worksite', () => {
        for (const url of ['/', '/?estado=red', '/worksite/7']) {
            page.url = url;
            expect(tabs(mount(SectionTabs))[0][2]).toBe('page');
        }
    });

    it('marks none on a page that is not one of them, like the data policy', () => {
        page.url = '/privacidad';

        expect(tabs(mount(SectionTabs)).map((tab) => tab[2])).toEqual([null, null, null]);
    });
});
