// Iteración 18 — el marco del panel del Administrador: el nombre de la
// organización y la navegación entre sus pantallas, al alcance del pulgar.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminLayout from './AdminLayout.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

describe('AdminLayout', () => {
    it('shows the organization and links every screen of the panel, marking the current one', () => {
        page.url = '/admin/contracts?page=2';
        const wrapper = mount(AdminLayout, { props: { title: 'Contratos' }, slots: { default: '<p>contenido</p>' } });

        expect(wrapper.get('h1').text()).toBe('Veeduría Ciudadana Santa Marta');
        expect(wrapper.find('h2').text()).toBe('Contratos');
        expect(wrapper.findAll('nav a').map((link) => [link.text(), link.attributes('href')])).toEqual([
            ['Bandeja', '/admin/inbox'],
            ['Veedores', '/admin/observers'],
            ['Territorio', '/admin/territory'],
            ['Contratos', '/admin/contracts'],
            ['Obras', '/admin/worksites'],
        ]);
        expect(wrapper.get('nav a[aria-current="page"]').text()).toBe('Contratos');
        expect(wrapper.text()).toContain('contenido');
    });
});
