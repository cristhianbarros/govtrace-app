// Iteración 19 — US-001 y US-011 (UI): el listado de organizaciones del
// Super Administrador, con acceso al alta y a corregir el NIT de una.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Organizations from './Organizations.vue';
import { fetchOrganizationDetail, fetchOrganizations, reactivateOrganization, suspendOrganization, updateOrganizationNit } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const smr = { id: 'tenant-smr', nit: '900123456-8', name: 'Veeduría Ciudadana Santa Marta', subdomain: 'veeduria-smr.govtrace.localhost', status: 'Activa' };
const smrDetail = { id: 'tenant-smr', name: 'Veeduría Ciudadana Santa Marta', nit: '900123456-8', subdomain: 'veeduria-smr.govtrace.localhost', status: 'Activa' };

async function openOrganizations(rows = [smr]) {
    fetchOrganizations.mockResolvedValue(rows);
    const wrapper = mount(Organizations);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Organizaciones', () => {
    it('shows that it is loading the organizations', () => {
        fetchOrganizations.mockReturnValue(new Promise(() => {}));

        expect(mount(Organizations).text()).toContain('Cargando organizaciones…');
    });

    it('says when it could not load them, and lets retry', async () => {
        fetchOrganizations.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce([smr]);
        const wrapper = mount(Organizations);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await button(wrapper, 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Veeduría Ciudadana Santa Marta');
    });

    it('says when there are no organizations yet', async () => {
        const wrapper = await openOrganizations([]);

        expect(wrapper.text()).toContain('Aún no hay organizaciones registradas.');
    });

    it('lists each organization with its NIT, subdomain and status', async () => {
        const wrapper = await openOrganizations([smr]);

        const row = wrapper.get('[data-test="organization-row"]').text();
        expect(row).toContain('Veeduría Ciudadana Santa Marta');
        expect(row).toContain('900123456-8');
        expect(row).toContain('veeduria-smr.govtrace.localhost');
        expect(row).toContain('Activa');
    });

    it('links to the alta screen', async () => {
        const wrapper = await openOrganizations([]);

        expect(wrapper.get('a').attributes('href')).toBe('/admin/organizations/new');
    });

    it('Actualización exitosa del NIT: edits the NIT of an organization, with audit', async () => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationNit.mockResolvedValue({ message: 'El NIT ha sido actualizado.' });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar NIT').trigger('click');
        await flushPromises();

        expect(fetchOrganizationDetail).toHaveBeenCalledWith('tenant-smr');
        expect(wrapper.get('input#nit').element.value).toBe('900123456-8');

        await wrapper.get('input#nit').setValue('901234567-7');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(updateOrganizationNit).toHaveBeenCalledWith('tenant-smr', '901234567-7');
        expect(wrapper.get('[role="status"]').text()).toBe('El NIT ha sido actualizado.');
    });

    it.each([
        ['Ya existe una organización registrada con el NIT ingresado.'],
        ['El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.'],
    ])('shows why the server refused the new NIT: %s', async (message) => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationNit.mockRejectedValue({ response: { status: 422, data: { message, errors: { nit: [message] } } } });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar NIT').trigger('click');
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('form [role="alert"]').text()).toBe(message);
    });
});

describe('Suspender y reactivar (US-003a)', () => {
    const suspended = { ...smr, status: 'Suspendida' };

    it('Suspensión de una organización activa: asks to confirm, then suspends and refreshes the list', async () => {
        const wrapper = await openOrganizations([smr]);
        suspendOrganization.mockResolvedValue({ message: 'Organización suspendida. Sus usuarios ya no pueden entrar; su mapa público sigue disponible.' });
        fetchOrganizations.mockResolvedValue([suspended]);

        await button(wrapper, 'Suspender').trigger('click');
        expect(suspendOrganization).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Sus usuarios no podrán entrar ni enviar reportes; su mapa público seguirá disponible, con un aviso.');

        await button(wrapper, 'Confirmar suspensión').trigger('click');
        await flushPromises();

        expect(suspendOrganization).toHaveBeenCalledWith('tenant-smr');
        expect(wrapper.get('[role="status"]').text()).toBe('Organización suspendida. Sus usuarios ya no pueden entrar; su mapa público sigue disponible.');
        expect(wrapper.get('[data-test="organization-row"]').text()).toContain('Suspendida');
    });

    it('Reactivación inmediata de una organización suspendida', async () => {
        const wrapper = await openOrganizations([suspended]);
        reactivateOrganization.mockResolvedValue({ message: 'Organización reactivada. Sus usuarios ya pueden volver a entrar.' });
        fetchOrganizations.mockResolvedValue([smr]);

        expect(button(wrapper, 'Suspender')).toBeUndefined();
        await button(wrapper, 'Reactivar').trigger('click');
        await flushPromises();

        expect(reactivateOrganization).toHaveBeenCalledWith('tenant-smr');
        expect(wrapper.get('[role="status"]').text()).toBe('Organización reactivada. Sus usuarios ya pueden volver a entrar.');
    });

    it('No se puede suspender una organización ya suspendida: shows the message', async () => {
        const message = 'La organización seleccionada ya se encuentra en estado suspendido.';
        suspendOrganization.mockRejectedValue({ response: { status: 422, data: { message, errors: { status: [message] } } } });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Suspender').trigger('click');
        await button(wrapper, 'Confirmar suspensión').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="organization-row"] [role="alert"]').text()).toBe(message);
    });
});
