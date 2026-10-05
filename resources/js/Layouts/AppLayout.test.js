import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AppLayout from './AppLayout.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

describe('AppLayout', () => {
    it('shows the name in the header as a brand, and leaves the heading of the page to the page (it. 40b)', () => {
        page.props = { organization: null, account: null };
        const wrapper = mount(AppLayout, {
            props: { title: 'GovTrace' },
            slots: { default: '<p>content</p>' },
        });

        expect(wrapper.get('header').text()).toContain('GovTrace');
        expect(wrapper.find('h1').exists()).toBe(false);
        expect(wrapper.find('main').html()).toContain('<p>content</p>');
    });

    it('renders the navigation only when the nav slot is provided, at the top inside the sticky header', () => {
        page.props = { organization: null, account: null };
        expect(mount(AppLayout).find('nav').exists()).toBe(false);
        const wrapper = mount(AppLayout, { slots: { nav: '<a>Inicio</a>' } });
        expect(wrapper.find('header nav').exists()).toBe(true);
        expect(wrapper.find('footer nav').exists()).toBe(false);
    });

    it('shows the organization logo next to the title, when it has one (US-007)', () => {
        page.props = { organization: null, account: null };
        expect(mount(AppLayout, { props: { title: 'Ojo Ciudadano SMR' } }).find('header img').exists()).toBe(false);

        const img = mount(AppLayout, { props: { title: 'Ojo Ciudadano SMR', logo: '/organization/logo?v=logo-abc' } }).get('header img');
        expect(img.attributes('src')).toBe('/organization/logo?v=logo-abc');
        expect(img.attributes('alt')).toBe('Logo de Ojo Ciudadano SMR');
    });

    it('puts the account menu in the header when someone is logged in, and nothing on a public screen (it. 40b)', () => {
        page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: { name: 'Marta Ospina', email: 'marta@veeduria.org', role: 'Administrador de Organización' } };
        expect(mount(AppLayout).get('header button[aria-haspopup="menu"]').text()).toContain('Marta Ospina');

        page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: null };
        expect(mount(AppLayout).find('header button[aria-haspopup="menu"]').exists()).toBe(false);
    });

    it('ends the site of a veeduría with the GovTrace footer, its neighborhood and the data policy (it. 40f)', () => {
        page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: null };
        const footer = mount(AppLayout, { props: { sections: true } }).get('footer');

        expect(footer.text()).toContain('GovTrace');
        expect(footer.get('a[href="/privacidad"]').text()).toBe('Política de tratamiento de datos');
        expect(footer.get('svg[data-illustration="skyline"]').attributes('aria-hidden')).toBe('true');
        expect(mount(AppLayout).find('footer').exists()).toBe(false);
    });

    it('shows the contact of the veeduría in its footer, when it has one (it. 43h, V13)', () => {
        page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: null, organizationContact: { email: 'contacto@veeduria-smr.org', phone: '+57 300 123 4567' } };
        const contact = mount(AppLayout, { props: { sections: true } }).get('footer [data-test="contact"]');

        expect(contact.text()).toContain('Contacto de la veeduría');
        expect(contact.get('a[href="mailto:contacto@veeduria-smr.org"]').text()).toBe('contacto@veeduria-smr.org');
        expect(contact.get('a[href="tel:+573001234567"]').text()).toBe('+57 300 123 4567');

        page.props = { organization: 'Veeduría Ciudadana Santa Marta', account: null, organizationContact: null };
        expect(mount(AppLayout, { props: { sections: true } }).find('[data-test="contact"]').exists()).toBe(false);
    });
});

