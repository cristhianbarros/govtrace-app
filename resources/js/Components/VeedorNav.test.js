// Iteración 30, it. 40b — las pestañas de la app del veedor, al alcance del
// pulgar. "Salir" pasó al menú de cuenta de la cabecera (AccountMenu.test.js),
// donde siempre pregunta antes.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import VeedorNav from './VeedorNav.vue';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

describe('VeedorNav', () => {
    it('links the two screens of the veedor, marking the open one', () => {
        const wrapper = mount(VeedorNav, { props: { current: '/my-reports' } });

        expect(wrapper.findAll('a').map((tab) => [tab.text(), tab.attributes('href')])).toEqual([
            ['Nuevo Reporte', '/reports/new'],
            ['Mis Reportes', '/my-reports'],
        ]);
        expect(wrapper.get('a[aria-current="page"]').text()).toBe('Mis Reportes');
    });

    it('no longer has "Salir", which lives in the account menu of the header', () => {
        expect(mount(VeedorNav, { props: { current: '/reports/new' } }).find('button').exists()).toBe(false);
    });
});
