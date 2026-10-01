// Iteración 40f — R-UX-10: las ilustraciones de GovTrace, en el mismo estilo y
// la misma paleta, para las pantallas vacías, los errores y los encabezados.
// Son decorativas: no las lee un lector de pantalla.
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Illustration, { ILLUSTRATIONS } from './Illustration.vue';

describe('Las ilustraciones', () => {
    it('draws each one, hidden from screen readers', () => {
        expect(ILLUSTRATIONS).toEqual(['empty', 'evidence', 'inbox-done', 'search', 'team', 'reports', 'messages', 'records', 'works', 'map', 'offline', 'error', 'validator', 'stats', 'welcome', 'privacy']);
        for (const name of ILLUSTRATIONS) {
            const svg = mount(Illustration, { props: { name } }).get('svg');

            expect(svg.attributes('data-illustration')).toBe(name);
            expect(svg.attributes('aria-hidden')).toBe('true');
            expect(svg.findAll('*').length).toBeGreaterThan(4);
        }
    });

    it('draws the generic one for a name it does not know', () => {
        expect(mount(Illustration, { props: { name: 'nada' } }).get('svg').attributes('data-illustration')).toBe('empty');
    });
});
