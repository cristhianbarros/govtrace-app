// Iteración 33 — US-003b (UI): el mapa de una organización dada de baja sale
// de línea; sus evidencias siguen verificables en el validador.
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Offline from './Offline.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const NOTICE = '⚠️ Esta organización fue dada de baja. Su mapa público ya no está disponible; sus evidencias selladas siguen verificables en el validador.';

afterEach(() => {
    delete page.props.organizationNotice;
});

describe('Mapa fuera de línea', () => {
    it('Tras la baja, el mapa sale de línea pero las evidencias siguen verificables: says so, and leads to the validator', () => {
        page.props.organizationNotice = NOTICE;

        const wrapper = mount(Offline);

        expect(wrapper.get('[role="status"]').text()).toBe(NOTICE);
        expect(wrapper.text()).toContain('El mapa de obras de esta organización ya no está disponible.');
        expect(wrapper.get('a[href="/verify"]').text()).toBe('Verificar un archivo en el validador');
    });
});
