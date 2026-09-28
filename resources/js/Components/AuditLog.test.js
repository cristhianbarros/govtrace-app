// Iteración 21 — US-043-MON (UI): el log de auditoría, el mismo para el
// Super Administrador (todo) y para el Administrador (lo de su organización).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AuditLog from './AuditLog.vue';

const entry = (overrides = {}) => ({
    id: 3,
    created_at: '2026-09-28T15:00:00+00:00',
    organization: 'Veeduría Ciudadana Santa Marta',
    actor: 'Root · Super Administrador',
    action: 'Cambió el NIT',
    before: { nit: '900123456-8' },
    after: { nit: '901234567-7' },
    ...overrides,
});

const page = (data, meta = { current_page: 1, last_page: 1, total: data.length }) => ({ data, meta });

let fetchPage;

async function openLog(response, props = {}) {
    fetchPage.mockResolvedValue(response);
    const wrapper = mount(AuditLog, { props: { fetchPage, ...props } });
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    fetchPage = vi.fn();
});

describe('AuditLog', () => {
    it('shows that it is loading, says when it could not load, and when it is empty', async () => {
        fetchPage.mockReturnValue(new Promise(() => {}));
        expect(mount(AuditLog, { props: { fetchPage } }).text()).toContain('Cargando el registro…');

        fetchPage = vi.fn().mockRejectedValue(new Error('Network Error'));
        const failed = mount(AuditLog, { props: { fetchPage } });
        await flushPromises();
        expect(failed.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');

        expect((await openLog(page([]))).text()).toContain('Aún no hay nada registrado.');
    });

    it('each entry shows who, when, the action, the value before and the new one', async () => {
        const wrapper = await openLog(page([entry()]), { showOrganization: true });

        const row = wrapper.get('[data-test="audit-entry"]').text();
        for (const part of ['Cambió el NIT', 'Root · Super Administrador', 'Veeduría Ciudadana Santa Marta', 'nit: 900123456-8', 'nit: 901234567-7']) {
            expect(row).toContain(part);
        }
        expect(row).toMatch(/28\/09\/2026/);
    });

    it('does not repeat the organization in the log of one organization', async () => {
        const wrapper = await openLog(page([entry()]));

        expect(wrapper.get('[data-test="audit-entry"]').text()).not.toContain('Veeduría Ciudadana Santa Marta');
    });

    it('moves between pages', async () => {
        const wrapper = await openLog(page([entry()], { current_page: 1, last_page: 2, total: 27 }));

        expect(wrapper.text()).toContain('Página 1 de 2 · 27 entradas');
        await wrapper.findAll('button').find((button) => button.text() === 'Siguiente').trigger('click');
        await flushPromises();

        expect(fetchPage).toHaveBeenLastCalledWith(2);
    });
});
