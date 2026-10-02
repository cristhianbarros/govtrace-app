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
    sentence: 'Root cambió el NIT',
    before: { nit: '900123456-8' },
    after: { nit: '901234567-7' },
    ...overrides,
});

const OPTIONS = {
    groups: [
        { key: 'organizaciones', label: 'Organizaciones y obras' },
        { key: 'evidencias', label: 'Evidencias e informes' },
    ],
    organizations: [
        { id: 'smr', name: 'Veeduría Ciudadana Santa Marta' },
        { id: 'cienaga', name: 'Veeduría Ciénaga' },
    ],
};
const page = (data, meta = { current_page: 1, last_page: 1, total: data.length }, options = OPTIONS) => ({ data, meta, options });

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
        for (const part of ['Root cambió el NIT', 'Root · Super Administrador', 'Veeduría Ciudadana Santa Marta', 'nit: 900123456-8', 'nit: 901234567-7']) {
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

        expect(fetchPage).toHaveBeenLastCalledWith(2, {});
    });
});

// It. 46d (US-043-MON): filtros, y cada entrada como una frase.
describe('AuditLog, con filtros', () => {
    const filter = async (wrapper, values) => {
        for (const [id, value] of Object.entries(values)) {
            await wrapper.get(`#audit-${id}`).setValue(value);
        }
        await wrapper.get('form[data-test="audit-filters"]').trigger('submit');
        await flushPromises();
    };

    it('El log se filtra por fecha, por quién lo hizo, por tipo de acción y, en el panel global, por organización', async () => {
        const wrapper = await openLog(page([entry()]), { showOrganization: true });

        await filter(wrapper, { from: '2026-09-01', to: '2026-09-30', actor: 'ana', group: 'evidencias', organization: 'smr' });

        expect(fetchPage).toHaveBeenLastCalledWith(1, { from: '2026-09-01', to: '2026-09-30', actor: 'ana', group: 'evidencias', organization: 'smr' });
    });

    it('offers the action types as a list, and the organizations only in the global panel', async () => {
        const global = await openLog(page([entry()]), { showOrganization: true });
        const own = await openLog(page([entry()]));

        expect(global.findAll('#audit-group option').map((option) => option.text())).toEqual(['Todos los tipos', 'Organizaciones y obras', 'Evidencias e informes']);
        expect(global.findAll('#audit-organization option').map((option) => option.text())).toEqual(['Todas las organizaciones', 'Veeduría Ciudadana Santa Marta', 'Veeduría Ciénaga']);
        expect(own.find('#audit-organization').exists()).toBe(false);
    });

    it('sends only the filters that were filled, and keeps them when it pages', async () => {
        const wrapper = await openLog(page([entry()], { current_page: 1, last_page: 2, total: 27 }));

        await filter(wrapper, { group: 'evidencias' });
        await wrapper.findAll('button').find((button) => button.text() === 'Siguiente').trigger('click');
        await flushPromises();

        expect(fetchPage).toHaveBeenNthCalledWith(2, 1, { group: 'evidencias' });
        expect(fetchPage).toHaveBeenLastCalledWith(2, { group: 'evidencias' });
    });

    it('"Quitar filtros" shows only with a filter on, and shows everything again', async () => {
        const wrapper = await openLog(page([entry()]));
        const remove = () => wrapper.findAll('button').find((button) => button.text() === 'Quitar filtros');
        expect(remove()).toBeUndefined();

        await filter(wrapper, { actor: 'ana' });
        expect(remove()).toBeDefined();
        await remove().trigger('click');
        await flushPromises();

        expect(fetchPage).toHaveBeenLastCalledWith(1, {});
        expect(wrapper.get('#audit-actor').element.value).toBe('');
        expect(remove()).toBeUndefined();
    });

    it('says no entry meets the filters, without offering the empty log message', async () => {
        const wrapper = await openLog(page([entry()]));
        fetchPage.mockResolvedValue(page([]));

        await filter(wrapper, { actor: 'nadie' });

        expect(wrapper.text()).toContain('Ninguna entrada cumple esos filtros.');
        expect(wrapper.text()).not.toContain('Aún no hay nada registrado.');
        expect(wrapper.find('form[data-test="audit-filters"]').exists()).toBe(true);
    });

    it('keeps the filters on screen when the server refuses one, and says why', async () => {
        const wrapper = await openLog(page([entry()]));
        fetchPage.mockRejectedValue({ response: { status: 422, data: { message: 'La fecha «hasta» no puede ser anterior a «desde».' } } });

        await filter(wrapper, { from: '2026-09-30', to: '2026-09-01' });

        expect(wrapper.get('[role="alert"]').text()).toContain('La fecha «hasta» no puede ser anterior a «desde».');
        expect(wrapper.get('#audit-to').element.value).toBe('2026-09-01');
    });

    it('Cada entrada se lee como una frase, con el detalle desplegable', async () => {
        const wrapper = await openLog(page([entry()]));
        const row = wrapper.get('[data-test="audit-entry"]');

        expect(row.get('p').text()).toBe('Root cambió el NIT');
        const detail = row.get('details');
        expect(detail.get('summary').text()).toBe('Ver el antes y el después');
        expect(detail.text()).toContain('nit: 900123456-8');
        expect(detail.text()).toContain('nit: 901234567-7');
    });
});
