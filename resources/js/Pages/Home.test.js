// Iteración 36 — la página del dominio central, que /audit encontró con un
// texto provisional (specs/AUDIT.md). No hay un mapa global (R-MAP-01):
// cada organización publica el suyo en su propio subdominio.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Home from './Home.vue';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

describe('Inicio del dominio central', () => {
    it('says what GovTrace is, without a global map, and leads to the global panel', () => {
        const wrapper = mount(Home);

        expect(wrapper.get('h1').text()).toBe('GovTrace');
        expect(wrapper.text()).toContain('Veeduría ciudadana de obras públicas, con evidencia sellada en la red Stellar.');
        expect(wrapper.text()).toContain('Cada organización veedora publica su mapa de obras en su propio subdominio.');
        expect(wrapper.get('a[href="/login"]').text()).toBe('Entrar al panel global');
        expect(wrapper.text()).not.toContain('listo para construirse');
    });
});
