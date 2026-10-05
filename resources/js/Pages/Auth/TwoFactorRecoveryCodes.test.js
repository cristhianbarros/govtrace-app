// Iteración 46g — US-065-SEC (UI): los 8 códigos de recuperación, una sola vez.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TwoFactorRecoveryCodes from './TwoFactorRecoveryCodes.vue';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const CODES = ['7KQ3M-9XD2P', 'A1B2C-D3E4F', 'G5H6J-K7M8N', 'P9Q0R-S1T2V', 'W3X4Y-Z5A6B', 'C7D8E-F9G0H', 'J1K2M-N3P4Q', 'R5S6T-V7W8X'];

describe('Los códigos de recuperación', () => {
    it('La primera vez, el Super Administrador configura su app autenticadora: shows the 8 codes once, to keep them off the phone', () => {
        const wrapper = mount(TwoFactorRecoveryCodes, { props: { codes: CODES } });

        expect(wrapper.findAll('[data-test="recovery-code"]').map((code) => code.text())).toEqual(CODES);
        expect(wrapper.text()).toContain('Guárdelos fuera del teléfono');
        expect(wrapper.text()).toContain('Cada uno sirve una sola vez');
        expect(wrapper.text()).toContain('No se volverán a mostrar');
        expect(wrapper.findAll('a').find((link) => link.text() === 'Ya los guardé, ir al panel').attributes('href')).toBe('/admin/organizations');
    });
});
