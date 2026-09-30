// Iteración 44c — US-057-LEG: el veedor que ya tenía cuenta declara, antes de
// su próximo reporte, que no tiene impedimentos para serlo (Ley 850 de 2003,
// art. 19). El servidor lo exige (ImpedimentsDeclarationTest); aquí, la pantalla.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Declaration from './Declaration.vue';
import { resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

beforeEach(resetInertia);

describe('La declaración de impedimentos', () => {
    it('Un veedor que ya tenía cuenta declara antes de su próximo reporte: the cases of the law, and the declaration is sent', async () => {
        const wrapper = mount(Declaration);

        expect(wrapper.get('h1').text()).toBe('Antes de reportar');
        expect(wrapper.get('[data-test="impediments"]').findAll('li')).toHaveLength(5);
        await wrapper.get('input#declaration').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([{ url: '/declaration', data: { declaration: true } }]);
    });

    it('does not send anything without the checkbox, and says what is missing', async () => {
        const wrapper = mount(Declaration);

        await wrapper.get('form').trigger('submit');

        expect(submissions).toEqual([]);
        expect(wrapper.get('[role="alert"]').text()).toBe('Para ser veedor, declare que no está en ninguno de estos casos.');
    });
});
