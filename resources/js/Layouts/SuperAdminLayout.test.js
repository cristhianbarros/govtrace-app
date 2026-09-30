// Iteración 21 — el marco del panel global: sus pantallas en la barra de abajo.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SuperAdminLayout from './SuperAdminLayout.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

describe('SuperAdminLayout', () => {
    it('links every screen of the global panel, marking the current one', () => {
        page.url = '/admin/parameters';
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Parámetros' }, slots: { default: '<p>contenido</p>' } });

        expect(wrapper.get('header').text()).toContain('Panel global');
        expect(wrapper.get('h1').text()).toBe('Parámetros');
        expect(wrapper.findAll('nav a').map((link) => [link.text(), link.attributes('href')])).toEqual([
            ['Organizaciones', '/admin/organizations'],
            ['Parámetros', '/admin/parameters'],
            ['Auditoría', '/admin/audit'],
            ['SECOP', '/admin/secop-health'],
            ['Sellado', '/admin/sealing'],
            ['Uso', '/admin/usage'],
        ]);
        expect(wrapper.get('nav a[aria-current="page"]').text()).toBe('Parámetros');
        page.url = '/admin/inbox';
    });
});
