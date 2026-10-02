// Iteración 43k — V10 (US-062-ALT): las solicitudes de alta que las veedurías
// envían desde el Inicio, para el Super Administrador. Aprobar lleva a la
// Nueva organización precargada; rechazar pide un motivo, que le llega por
// correo a quien la pidió. Las reglas las prueba OrganizationRequestsTest.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrganizationRequests from './OrganizationRequests.vue';
import { fetchOrganizationRequestRues, fetchOrganizationRequests, rejectOrganizationRequest } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const pradera = { id: 1, name: 'Veeduría Ciudadana de La Pradera', contact_email: 'contacto@lapradera.org', registration_number: 'Resolución 045 de 2026', registration_authority: 'Personería de Medellín', received_at: '2026-10-01T15:00:00+00:00', has_document: true };

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

// It. 46b: lo que dicen los datos abiertos del RUES, y el PDF que adjuntó la veeduría.
describe('La validación asistida (it. 46b)', () => {
    const found = {
        status: 'found',
        message: null,
        data_date: '2026-09-04',
        records: [{ name: 'VEEDURIA CIUDADANA PAISAJE URBANO', nit: null, legal_form: 'VEEDURIA', status: 'ACTIVA', chamber: 'MEDELLIN PARA ANTIOQUIA', registration: '2183031', registered_on: '2024-07-24', updated_on: '2024-08-14' }],
    };

    async function openWith(answer) {
        fetchOrganizationRequestRues.mockResolvedValue(answer);
        return (await openRequests()).get('[data-test="organization-request"]');
    }

    it('El Super Administrador ve lo que dice el RUES de una veeduría inscrita en una cámara de comercio', async () => {
        const rues = (await openWith(found)).get('[data-test="rues"]');

        expect(fetchOrganizationRequestRues).toHaveBeenCalledWith(1);
        expect(rues.text()).toContain('Encontrada en el RUES');
        expect(rues.text()).toContain('VEEDURIA CIUDADANA PAISAJE URBANO');
        expect(rues.text()).toContain('MEDELLIN PARA ANTIOQUIA');
        expect(rues.text()).toContain('Matrícula 2183031');
        expect(rues.text()).toContain('ACTIVA');
        // La fecha del extracto mensual, y la de la última actualización de ese registro, que puede ser vieja.
        expect(rues.text()).toContain('Datos del RUES al 04/09/2026');
        expect(rues.text()).toContain('Sus datos se actualizaron en el RUES el 14/08/2024');
    });

    it('says which extract of the RUES a veeduría was not found in: one registered after it is not there yet', async () => {
        const rues = (await openWith({ status: 'not_found', message: 'El RUES no trae una organización con ese NIT o esa matrícula. Revise el PDF y los datos.', records: [], data_date: '2026-09-04' })).get('[data-test="rues"]');

        expect(rues.text()).toContain('El RUES no trae una organización con ese NIT o esa matrícula.');
        expect(rues.text()).toContain('Datos del RUES al 04/09/2026');
    });

    it('Una veeduría inscrita en una personería no está en los datos abiertos del RUES: and the PDF is a link away', async () => {
        const request = await openWith({ status: 'personeria', message: 'Inscrita en una personería: los datos abiertos del RUES no la traen. Revise el PDF de la resolución.', records: [], data_date: null });

        expect(request.get('[data-test="rues"]').text()).toContain('Inscrita en una personería: los datos abiertos del RUES no la traen. Revise el PDF de la resolución.');
        const pdf = request.findAll('a').find((link) => link.text() === 'Descargar el PDF que adjuntó');
        expect(pdf.attributes('href')).toBe('/admin/organization-requests/1/document');
    });

    it('Si el RUES no responde, la revisión sigue: the request can still be approved or rejected', async () => {
        const request = await openWith({ status: 'unavailable', message: 'No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde.', records: [], data_date: null });

        expect(request.get('[data-test="rues"]').text()).toContain('No se pudo consultar el RUES ahora.');
        expect(request.findAll('a').some((link) => link.text() === 'Aprobar y dar de alta')).toBe(true);
        expect(request.findAll('button').some((button) => button.text() === 'Rechazar')).toBe(true);
    });

    it('says it is still asking the RUES while the answer comes', async () => {
        fetchOrganizationRequestRues.mockReturnValue(new Promise(() => {}));
        const request = (await openRequests()).get('[data-test="organization-request"]');

        expect(request.get('[data-test="rues"]').text()).toContain('Consultando el RUES…');
    });
});
