// Iteración 30 — US-018: cerrar sesión desde la app del veedor, con aviso si
// quedan reportes sin enviar en el teléfono.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import VeedorNav from './VeedorNav.vue';
import { configureOutbox } from '@/composables/useOutbox.js';
import { createOutbox, memoryStore } from '@/lib/outbox.js';
import { logout } from '@/services/api.js';
import { router } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const WARNING = '🚨 Tienes reportes sin enviar. Si cierras sesión ahora, se borrarán permanentemente del teléfono. ¿Deseas continuar?';

async function withPending(count) {
    const store = memoryStore();
    const outbox = createOutbox(store);
    for (let i = 0; i < count; i++) {
        await outbox.add({ fields: { captured_at: new Date().toISOString() }, hashes: ['ab'.repeat(32)], files: [new File(['x'], 'x.jpg')] });
    }
    configureOutbox({ store });
    const wrapper = mount(VeedorNav, { props: { current: '/my-reports' } });
    await flushPromises();
    return { wrapper, outbox };
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
    logout.mockResolvedValue(undefined);
});

describe('Cerrar sesión', () => {
    it('Cerrar sesión con reportes pendientes: warns, and does not log out until confirmed', async () => {
        const { wrapper, outbox } = await withPending(2);

        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alertdialog"]').text()).toContain(WARNING);
        expect(logout).not.toHaveBeenCalled();
        await button(wrapper, 'Cancelar').trigger('click');
        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);
        expect(await outbox.pending()).toHaveLength(2);

        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();
        await button(wrapper, 'Sí, cerrar sesión').trigger('click');
        await flushPromises();

        expect(logout).toHaveBeenCalledOnce();
        expect(await outbox.pending()).toEqual([]);
        expect(router.visit).toHaveBeenCalledWith('/login');
    });

    it('logs out at once when nothing is pending', async () => {
        const { wrapper } = await withPending(0);

        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);
        expect(logout).toHaveBeenCalledOnce();
        expect(router.visit).toHaveBeenCalledWith('/login');
    });
});
