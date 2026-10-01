// Iteración 40f: los estados de una pantalla que carga datos, ilustrados —
// vacío con su dibujo y su texto, error con el suyo y "Reintentar".
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LoadState from './LoadState.vue';

const props = { loadingText: 'Cargando…', emptyText: 'Aún no hay nada.' };

describe('Los estados de carga, ilustrados (it. 40f)', () => {
    it('shows the illustration of an empty screen with its words', () => {
        const wrapper = mount(LoadState, { props: { ...props, empty: true, illustration: 'team' } });

        expect(wrapper.get('[data-test="empty"]').text()).toBe('Aún no hay nada.');
        expect(wrapper.get('[data-test="empty"] svg').attributes('data-illustration')).toBe('team');
    });

    it('uses the generic illustration when the screen does not ask for one', () => {
        const wrapper = mount(LoadState, { props: { ...props, empty: true } });

        expect(wrapper.get('[data-test="empty"] svg').attributes('data-illustration')).toBe('empty');
    });

    it('shows an error with its illustration and the way to retry', async () => {
        const wrapper = mount(LoadState, { props: { ...props, error: 'No se pudo conectar con el servidor.' } });

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        expect(wrapper.get('[role="alert"] svg').attributes('data-illustration')).toBe('error');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });
});
