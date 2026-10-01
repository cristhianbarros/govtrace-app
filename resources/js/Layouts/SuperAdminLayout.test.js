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
        expect(wrapper.findAll('[data-test="sidebar"] a').map((link) => [link.text(), link.attributes('href')])).toEqual([
            ['Organizaciones', '/admin/organizations'],
            ['Solicitudes de alta', '/admin/organization-requests'], // it. 43k (V10)
            ['Sellado', '/admin/sealing'],
            ['SECOP', '/admin/secop-health'],
            ['Uso', '/admin/usage'],
            ['Parámetros', '/admin/parameters'],
            ['Auditoría', '/admin/audit'],
        ]);
        expect(wrapper.get('[data-test="sidebar"] a[aria-current="page"]').text()).toBe('Parámetros');
        page.url = '/admin/inbox';
    });

    it('on the phone, three tabs with an icon and their name, and "Más" for the rest', async () => {
        page.url = '/admin/organizations/new';
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Nueva organización' }, attachTo: document.body });
        const tabs = wrapper.get('[data-test="tabs"]');

        expect(tabs.findAll('a').map((tab) => tab.text())).toEqual(['Organizaciones', 'Sellado', 'SECOP']);
        expect(tabs.get('a[aria-current="page"]').text()).toBe('Organizaciones');
        await tabs.get('button[aria-haspopup="menu"]').trigger('click');
        expect(wrapper.get('[data-test="more"]').findAll('a').map((link) => link.text())).toEqual(['Solicitudes de alta', 'Uso', 'Parámetros', 'Auditoría']);
        wrapper.unmount();
        page.url = '/admin/inbox';
    });

    it('says in the menu how many requests for an alta wait (it. 43k, V10)', () => {
        page.props.organizationRequestsPending = 2;
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Organizaciones' } });

        expect(wrapper.get('[data-test="sidebar"] a[href="/admin/organization-requests"]').attributes('aria-label')).toBe('Solicitudes de alta, 2 pendientes');
        page.props.organizationRequestsPending = null;
    });
});

