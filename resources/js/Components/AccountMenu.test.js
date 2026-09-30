// It. 40b — V1 de docs/mapa-funcional.md: en la cabecera de cada panel, quién
// tiene la sesión abierta y cómo salir. Salir siempre pregunta antes; al
// veedor además le avisa si quedan reportes sin enviar (US-018).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AccountMenu from './AccountMenu.vue';
import { configureOutbox } from '@/composables/useOutbox.js';
import { createOutbox, memoryStore } from '@/lib/outbox.js';
import { logout } from '@/services/api.js';
import { page, router } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const WARNING = '🚨 Tienes reportes sin enviar. Si cierras sesión ahora, se borrarán permanentemente del teléfono. ¿Deseas continuar?';
const ADMINISTRATOR = { name: 'Marta Ospina', email: 'marta@veeduria.org', role: 'Administrador de Organización' };
const VEEDOR = { name: 'Ana Torres', email: 'ana.torres@correo.co', role: 'Veedor de Campo' };
const SUPER_ADMINISTRATOR = { name: 'Equipo GovTrace', email: 'root@govtrace.app', role: 'Super Administrador' };

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function menuOf(account, { organization = 'Veeduría Ciudadana Santa Marta', pending = 0 } = {}) {
    page.props = { organization, account };
    const store = memoryStore();
    const outbox = createOutbox(store);
    for (let i = 0; i < pending; i++) {
        await outbox.add({ fields: { captured_at: new Date().toISOString() }, hashes: ['ab'.repeat(32)], files: [new File(['x'], 'x.jpg')] });
    }
    configureOutbox({ store });
    const wrapper = mount(AccountMenu, { attachTo: document.body });
    await flushPromises();
    return { wrapper, outbox };
}

async function openTheMenu(wrapper) {
    await wrapper.get('button[aria-haspopup="menu"]').trigger('click');
}

beforeEach(() => {
    vi.resetAllMocks();
    logout.mockResolvedValue(undefined);
});

describe('Menú de cuenta', () => {
    it('Cerrar sesión desde cualquier panel: names who is logged in, and asks before logging out', async () => {
        const { wrapper } = await menuOf(ADMINISTRATOR);

        expect(wrapper.get('button[aria-haspopup="menu"]').text()).toContain('Marta Ospina');
        await openTheMenu(wrapper);
        expect(wrapper.get('[role="menu"]').text()).toContain('Administrador de Organización');

        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alertdialog"]').text()).toContain('¿Cerrar la sesión?');
        expect(logout).not.toHaveBeenCalled();

        await button(wrapper, 'Sí, cerrar sesión').trigger('click');
        await flushPromises();
        expect(logout).toHaveBeenCalledOnce();
        expect(router.visit).toHaveBeenCalledWith('/login');
        wrapper.unmount();
    });

    it('keeps the session open when the person cancels', async () => {
        const { wrapper } = await menuOf(SUPER_ADMINISTRATOR, { organization: null });

        await openTheMenu(wrapper);
        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();
        await button(wrapper, 'Cancelar').trigger('click');

        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);
        expect(logout).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('leads a person of an organization to its public site, and not the Super Administrador, who has none', async () => {
        const { wrapper: inOrganization } = await menuOf(ADMINISTRATOR);
        await openTheMenu(inOrganization);
        expect(inOrganization.get('[role="menu"] a[href="/"]').text()).toBe('Ver el sitio público');
        inOrganization.unmount();

        const { wrapper: global } = await menuOf(SUPER_ADMINISTRATOR, { organization: null });
        await openTheMenu(global);
        expect(global.find('[role="menu"] a[href="/"]').exists()).toBe(false);
        global.unmount();
    });

    it('leads everyone to change their password, with the session open (it. 40c)', async () => {
        const { wrapper } = await menuOf(VEEDOR);
        await openTheMenu(wrapper);

        expect(wrapper.get('[role="menu"] a[href="/account/password"]').text()).toBe('Cambiar contraseña');
        wrapper.unmount();
    });

    it('Cerrar sesión con reportes pendientes: warns, and does not log out until confirmed', async () => {
        const { wrapper, outbox } = await menuOf(VEEDOR, { pending: 2 });

        await openTheMenu(wrapper);
        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alertdialog"]').text()).toContain(WARNING);
        expect(logout).not.toHaveBeenCalled();
        await button(wrapper, 'Cancelar').trigger('click');
        expect(await outbox.pending()).toHaveLength(2);

        await openTheMenu(wrapper);
        await button(wrapper, 'Salir').trigger('click');
        await flushPromises();
        await button(wrapper, 'Sí, cerrar sesión').trigger('click');
        await flushPromises();

        expect(logout).toHaveBeenCalledOnce();
        expect(await outbox.pending()).toEqual([]);
        expect(router.visit).toHaveBeenCalledWith('/login');
        wrapper.unmount();
    });

    it('shows nothing on a public screen, where nobody is logged in', async () => {
        const { wrapper } = await menuOf(null);

        expect(wrapper.find('button').exists()).toBe(false);
        wrapper.unmount();
    });
});
