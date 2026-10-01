// Iteración 43k — V10 (US-062-ALT): las solicitudes de alta que las veedurías
// envían desde el Inicio, para el Super Administrador. Aprobar lleva a la
// Nueva organización precargada; rechazar pide un motivo, que le llega por
// correo a quien la pidió. Las reglas las prueba OrganizationRequestsTest.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrganizationRequests from './OrganizationRequests.vue';
import { fetchOrganizationRequests, rejectOrganizationRequest } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const pradera = { id: 1, name: 'Veeduría Ciudadana de La Pradera', contact_email: 'contacto@lapradera.org', registration_number: 'Resolución 045 de 2026', registration_authority: 'Personería de Medellín', received_at: '2026-10-01T15:00:00+00:00' };

async function openRequests(pending = [pradera]) {
    fetchOrganizationRequests.mockResolvedValue(pending);
    const wrapper = mount(OrganizationRequests);
    await flushPromises();
    return wrapper;
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => vi.resetAllMocks());

describe('Solicitudes de alta (it. 43k, V10)', () => {
    it('El Super Administrador ve las solicitudes pendientes: its name, email, resolution and Personería', async () => {
        const request = (await openRequests()).get('[data-test="organization-request"]');

        expect(request.text()).toContain('Veeduría Ciudadana de La Pradera');
        expect(request.text()).toContain('contacto@lapradera.org');
        expect(request.text()).toContain('Resolución 045 de 2026, Personería de Medellín');
        expect(request.text()).toContain('01/10/2026');
    });

    it('El Super Administrador aprueba una solicitud: leads to the new organization, pre-filled', async () => {
        const approve = (await openRequests()).findAll('a').find((link) => link.text() === 'Aprobar y dar de alta');

        expect(approve.attributes('href')).toBe('/admin/organizations/new?request=1');
    });

    it('El Super Administrador rechaza una solicitud con un motivo: asks for it, and says it was sent', async () => {
        rejectOrganizationRequest.mockResolvedValue({ message: 'Solicitud rechazada. Le escribimos a contacto@lapradera.org con el motivo.' });
        const wrapper = await openRequests();
        fetchOrganizationRequests.mockResolvedValue([]);

        await button(wrapper, 'Rechazar').trigger('click');
        await wrapper.get('textarea#reject-reason-1').setValue('La resolución no corresponde a una veeduría inscrita.');
        await button(wrapper, 'Confirmar rechazo').trigger('click');
        await flushPromises();

        expect(rejectOrganizationRequest).toHaveBeenCalledWith(1, 'La resolución no corresponde a una veeduría inscrita.');
        expect(wrapper.get('[role="status"]').text()).toBe('Solicitud rechazada. Le escribimos a contacto@lapradera.org con el motivo.');
    });

    it('does not reject without a reason', async () => {
        const wrapper = await openRequests();

        await button(wrapper, 'Rechazar').trigger('click');
        await button(wrapper, 'Confirmar rechazo').trigger('click');

        expect(rejectOrganizationRequest).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('Escriba el motivo: le llega a quien pidió el alta.');
    });

    it('says when there is no request waiting', async () => {
        expect((await openRequests([])).get('[data-test="empty"]').text()).toBe('No hay solicitudes de alta pendientes.');
    });
});
