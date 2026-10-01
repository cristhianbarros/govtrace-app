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

        expect(wrapper.get('header').text()).toContain('Veeduría Ciudadana Santa Marta');
        expect(wrapper.get('h1').text()).toBe('Contratos');
        // It. 40c: en el computador, la barra lateral con todas, por grupos.
        expect(wrapper.findAll('[data-test="sidebar"] a').map((link) => [link.text(), link.attributes('href')])).toEqual([
            ['Bandeja', '/admin/inbox'],
            ['Informes ciudadanos', '/admin/citizen-reports'], // it. 44f
            ['Resumen', '/admin/summary'],
            ['Territorio', '/admin/territory'],
            ['Contratos', '/admin/contracts'],
            ['Obras', '/admin/worksites'],
            ['Veedores', '/admin/observers'],
            ['Organización', '/admin/organization'],
            ['Auditoría', '/admin/audit'],
            ['Autorización', '/admin/authorization'],
        ]);
        expect(wrapper.get('[data-test="sidebar"] a[aria-current="page"]').text()).toBe('Contratos');
        expect(wrapper.text()).toContain('contenido');
    });

    it('Navegación del panel con íconos: on the phone, three tabs with an icon and their name, and "Más" for the rest', async () => {
        page.url = '/admin/territory';
        const wrapper = mount(AdminLayout, { props: { title: 'Territorio' }, attachTo: document.body });
        const tabs = wrapper.get('[data-test="tabs"]');

        expect(tabs.findAll('a').map((tab) => tab.text())).toEqual(['Bandeja', 'Obras', 'Veedores']);
        expect(tabs.findAll('a svg')).toHaveLength(3);
        const more = tabs.get('button[aria-haspopup="menu"]');
        expect(more.text()).toBe('Más');
        expect(more.attributes('data-current')).toBe('true'); // Territorio vive en "Más"

        await more.trigger('click');
        expect(wrapper.get('[data-test="more"]').findAll('a').map((link) => link.text())).toEqual(['Informes ciudadanos', 'Resumen', 'Territorio', 'Contratos', 'Organización', 'Auditoría', 'Autorización']);
        wrapper.unmount();
    });

    it('counts on the tab of the Bandeja how many evidences wait for review', () => {
        page.url = '/admin/observers';
        page.props.inboxPending = 3;
        const wrapper = mount(AdminLayout, { props: { title: 'Veedores' } });

        expect(wrapper.get('[data-test="tabs"] a[href="/admin/inbox"]').text()).toContain('3');
        expect(wrapper.get('[data-test="sidebar"] a[href="/admin/inbox"]').attributes('aria-label')).toBe('Bandeja, 3 por revisar');
        page.props.inboxPending = null;
    });

    it('shows the logo of the organization in the header (US-007)', () => {
        page.props.organizationLogo = '/organization/logo?v=logo-abc';
        const wrapper = mount(AdminLayout, { props: { title: 'Bandeja de entrada' } });

        expect(wrapper.get('header img').attributes('src')).toBe('/organization/logo?v=logo-abc');
        page.props.organizationLogo = null;
    });

    it.each([
        [3, 'Alerta: 3 evidencias no se pudieron certificar de forma segura. El soporte técnico de GovTrace tiene que revisarlas.'],
        [1, 'Alerta: 1 evidencia no se pudo certificar de forma segura. El soporte técnico de GovTrace tiene que revisarla.'],
    ])('Banner para el Administrador de Organización: %i in "Falla de Sellado" (US-021)', (failures, message) => {
        page.props.sealingFailures = failures;
        const wrapper = mount(AdminLayout, { props: { title: 'Bandeja de entrada' } });

        expect(wrapper.get('[role="alert"]').text()).toBe(message);
        page.props.sealingFailures = null;
    });

    it('shows no banner when nothing failed', () => {
        page.props.sealingFailures = 0;

        expect(mount(AdminLayout, { props: { title: 'Bandeja de entrada' } }).find('[role="alert"]').exists()).toBe(false);
        page.props.sealingFailures = null;
    });
});
