import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AppLayout from './AppLayout.vue';

describe('AppLayout', () => {
    it('renders the title and the default slot content', () => {
        const wrapper = mount(AppLayout, {
            props: { title: 'GovTrace' },
            slots: { default: '<p>content</p>' },
        });

        expect(wrapper.find('h1').text()).toBe('GovTrace');
        expect(wrapper.find('main').html()).toContain('<p>content</p>');
    });

    it('renders the bottom navigation only when the nav slot is provided', () => {
        expect(mount(AppLayout).find('nav').exists()).toBe(false);
        expect(mount(AppLayout, { slots: { nav: '<a>Inicio</a>' } }).find('nav').exists()).toBe(true);
    });

    it('shows the organization logo next to the title, when it has one (US-007)', () => {
        expect(mount(AppLayout, { props: { title: 'Ojo Ciudadano SMR' } }).find('header img').exists()).toBe(false);

        const img = mount(AppLayout, { props: { title: 'Ojo Ciudadano SMR', logo: '/organization/logo?v=logo-abc' } }).get('header img');
        expect(img.attributes('src')).toBe('/organization/logo?v=logo-abc');
        expect(img.attributes('alt')).toBe('Logo de Ojo Ciudadano SMR');
    });
});
