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
});
