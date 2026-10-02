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
            ['Super Administradores', '/admin/super-administrators'], // it. 46a
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
        expect(wrapper.get('[data-test="more"]').findAll('a').map((link) => link.text())).toEqual(['Solicitudes de alta', 'Uso', 'Super Administradores', 'Parámetros', 'Auditoría']);
        wrapper.unmount();
        page.url = '/admin/inbox';
    });

    it('says in the menu how many requests for an alta wait (it. 43k, V10)', () => {
        page.props.organizationRequestsPending = 2;
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Organizaciones' } });

        expect(wrapper.get('[data-test="sidebar"] a[href="/admin/organization-requests"]').attributes('aria-label')).toBe('Solicitudes de alta, 2 pendientes');
        page.props.organizationRequestsPending = null;
    });

    // It. 46a (US-063-USR): con un solo Super Administrador activo, todas las pantallas lo avisan.
    const ONE_LEFT = 'Solo hay un Super Administrador activo. Si pierde el acceso, nadie podrá dar de alta veedurías ni atender las alertas. Invite a otro.';

    it('El panel avisa cuando queda un solo Super Administrador activo: with a link to invite another one', () => {
        page.props.superAdministratorsActive = 1;
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Organizaciones' } });

        const warning = wrapper.get('[data-test="one-super-admin"]');
        expect(warning.attributes('role')).toBe('status');
        expect(warning.text()).toContain(ONE_LEFT);
        expect(warning.get('a').attributes('href')).toBe('/admin/super-administrators');
        expect(warning.get('a').text()).toBe('Invitar a otro Super Administrador');
        page.props.superAdministratorsActive = null;
    });

    it('does not warn while two or more are active', () => {
        page.props.superAdministratorsActive = 2;
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Organizaciones' } });

        expect(wrapper.find('[data-test="one-super-admin"]').exists()).toBe(false);
        page.props.superAdministratorsActive = null;
    });
});
