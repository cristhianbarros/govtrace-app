// Iteración 19 — US-001 y US-011 (UI): el listado de organizaciones del
// Super Administrador, con acceso al alta y a corregir el NIT de una.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Organizations from './Organizations.vue';
import {
    assignAdministrator,
    confirmDecommission,
    fetchOrganizationDetail,
    fetchOrganizations,
    reactivateOrganization,
    resendAdministratorInvitation,
    revokeAdministratorInvitation,
    startDecommission,
    suspendOrganization,
    updateOrganizationLegalData,
} from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const smr = { id: 'tenant-smr', nit: '900123456-8', identification: 'NIT 900123456-8', name: 'Veeduría Ciudadana Santa Marta', subdomain: 'veeduria-smr.govtrace.localhost', status: 'Activa' };
const smrDetail = { id: 'tenant-smr', name: 'Veeduría Ciudadana Santa Marta', nit: '900123456-8', registration_number: null, registration_authority: null, subdomain: 'veeduria-smr.govtrace.localhost', status: 'Activa' };

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
        expect(row).toContain('NIT 900123456-8');
        expect(row).toContain('veeduria-smr.govtrace.localhost');
        expect(row).toContain('Activa');
    });

    it('links to the alta screen', async () => {
        const wrapper = await openOrganizations([]);

        expect(wrapper.get('main').get('a').attributes('href')).toBe('/admin/organizations/new');
    });

    it('Actualización exitosa del NIT: edits the NIT of an organization, with audit', async () => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationLegalData.mockResolvedValue({ message: 'Los datos legales han sido actualizados.' });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar datos legales').trigger('click');
        await flushPromises();

        expect(fetchOrganizationDetail).toHaveBeenCalledWith('tenant-smr');
        expect(wrapper.get('input#nit').element.value).toBe('900123456-8');

        await wrapper.get('input#nit').setValue('901234567-7');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(updateOrganizationLegalData).toHaveBeenCalledWith('tenant-smr', { name: 'Veeduría Ciudadana Santa Marta', nit: '901234567-7', registration_number: null, registration_authority: null });
        expect(wrapper.get('[role="status"]').text()).toBe('Los datos legales han sido actualizados.');
    });

    it.each([
        ['Ya existe una organización registrada con el NIT ingresado.'],
        ['El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.'],
    ])('shows why the server refused the new NIT: %s', async (message) => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationLegalData.mockRejectedValue({ response: { status: 422, data: { message, errors: { nit: [message] } } } });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar datos legales').trigger('click');
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

// Iteración 33 — US-003b (UI): la baja definitiva, con doble confirmación.
describe('Dar de baja (US-003b)', () => {
    const first = {
        token: 'token-de-la-primera',
        summary: { organization: 'Veeduría Ciudadana Santa Marta', subdomain: 'veeduria-smr', sealed_reports: 5, files_kept_until: '2031-09-29' },
        message: 'Para confirmar la baja definitiva, escriba el subdominio de la organización: veeduria-smr.',
    };
    const DONE = 'Organización dada de baja. Sus usuarios ya no tienen acceso; su mapa salió de línea y sus evidencias siguen verificables.';

    it('Baja lógica tras doble confirmación: says what it implies, asks to type the subdomain, and decommissions', async () => {
        const wrapper = await openOrganizations([smr]);
        startDecommission.mockResolvedValue(first);
        confirmDecommission.mockResolvedValue({ message: DONE });
        fetchOrganizations.mockResolvedValue([{ ...smr, status: 'Dada de baja' }]);

        // Primera confirmación: lo que implica.
        await button(wrapper, 'Dar de baja').trigger('click');
        await flushPromises();
        expect(startDecommission).toHaveBeenCalledWith('tenant-smr');
        const dialog = wrapper.get('[data-test="decommission"]');
        expect(dialog.text()).toContain('5 reportes sellados en Stellar siguen verificables');
        expect(dialog.text()).toContain('Sus archivos se conservan hasta el 29/09/2031');
        expect(dialog.text()).toContain(first.message);

        // Segunda: el subdominio escrito.
        const confirm = () => button(wrapper, 'Dar de baja definitivamente');
        expect(confirm().attributes('disabled')).toBeDefined();
        await dialog.get('input').setValue('veeduria-smr');
        expect(confirm().attributes('disabled')).toBeUndefined();
        await confirm().trigger('click');
        await flushPromises();

        expect(confirmDecommission).toHaveBeenCalledWith('tenant-smr', 'token-de-la-primera', 'veeduria-smr');
        expect(wrapper.get('[role="status"]').text()).toBe(DONE);
        expect(wrapper.get('[data-test="organization-row"]').text()).toContain('Dada de baja');
    });

    it('La baja no se ejecuta sin la doble confirmación: cancelling the second leaves it active', async () => {
        const wrapper = await openOrganizations([smr]);
        startDecommission.mockResolvedValue(first);

        await button(wrapper, 'Dar de baja').trigger('click');
        await flushPromises();
        await button(wrapper, 'Cancelar').trigger('click');

        expect(confirmDecommission).not.toHaveBeenCalled();
        expect(wrapper.find('[data-test="decommission"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="organization-row"]').text()).toContain('Activa');
    });

    it('shows why the server refused the decommission', async () => {
        const message = 'La primera confirmación venció o no es válida. Vuelva a solicitar la baja.';
        const wrapper = await openOrganizations([smr]);
        startDecommission.mockResolvedValue(first);
        confirmDecommission.mockRejectedValue({ response: { status: 422, data: { message, errors: { token: [message] } } } });

        await button(wrapper, 'Dar de baja').trigger('click');
        await flushPromises();
        await wrapper.get('[data-test="decommission"] input').setValue('veeduria-smr');
        await button(wrapper, 'Dar de baja definitivamente').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="decommission"] [role="alert"]').text()).toBe(message);
    });

    it('offers nothing else on a decommissioned organization: it is definitive', async () => {
        const wrapper = await openOrganizations([{ ...smr, status: 'Dada de baja' }]);

        const row = wrapper.get('[data-test="organization-row"]');
        expect(row.findAll('button').map((candidate) => candidate.text())).toEqual(['Editar datos legales']);
    });
});

describe('Administradores de cada organización (it. 43a, V2)', () => {
    const pending = { id: 7, name: 'Marta Ospina', email: 'marta@veeduria.org', status: 'pending', label: 'Invitación pendiente' };
    const expired = { ...pending, status: 'expired', label: 'Invitación vencida' };
    const active = { ...pending, status: 'active', label: 'Activo' };
    const block = (wrapper) => wrapper.get('[data-test="administrators"]');

    it('Reenviar la invitación del Administrador inicial: shows who administers each organization, and resends an unanswered invitation', async () => {
        const wrapper = await openOrganizations([{ ...smr, administrators: [expired] }]);
        expect(block(wrapper).text()).toContain('Marta Ospina');
        expect(block(wrapper).text()).toContain('marta@veeduria.org');
        expect(block(wrapper).text()).toContain('Invitación vencida');

        resendAdministratorInvitation.mockResolvedValue({ message: 'Invitación reenviada a marta@veeduria.org. El nuevo enlace vence en 48 horas.' });
        fetchOrganizations.mockResolvedValue([{ ...smr, administrators: [pending] }]);
        await button(block(wrapper), 'Reenviar invitación').trigger('click');
        await flushPromises();

        expect(resendAdministratorInvitation).toHaveBeenCalledWith('tenant-smr', 7);
        expect(wrapper.text()).toContain('Invitación reenviada a marta@veeduria.org.');
        expect(block(wrapper).text()).toContain('Invitación pendiente');
    });

    it('revokes an invitation sent to a wrong address, after asking', async () => {
        const wrapper = await openOrganizations([{ ...smr, administrators: [pending] }]);
        revokeAdministratorInvitation.mockResolvedValue({ message: 'Invitación revocada.' });
        fetchOrganizations.mockResolvedValue([{ ...smr, administrators: [] }]);

        await button(block(wrapper), 'Revocar invitación').trigger('click');
        expect(revokeAdministratorInvitation).not.toHaveBeenCalled();
        await button(block(wrapper), 'Confirmar revocación').trigger('click');
        await flushPromises();

        expect(revokeAdministratorInvitation).toHaveBeenCalledWith('tenant-smr', 7);
        expect(block(wrapper).text()).toContain('Sin Administrador');
    });

    it('Asignar el Administrador inicial después del alta: to an organization that has none, with a name and an email', async () => {
        const wrapper = await openOrganizations([{ ...smr, administrators: [] }]);
        assignAdministrator.mockResolvedValue({ message: 'Invitación enviada a ana@veeduria.org.' });
        fetchOrganizations.mockResolvedValue([{ ...smr, administrators: [{ ...pending, name: 'Ana Pérez', email: 'ana@veeduria.org' }] }]);

        await button(block(wrapper), 'Asignar Administrador').trigger('click');
        await block(wrapper).get('input[name="administrator-name"]').setValue('Ana Pérez');
        await block(wrapper).get('input[name="administrator-email"]').setValue('ana@veeduria.org');
        await block(wrapper).get('form').trigger('submit');
        await flushPromises();

        expect(assignAdministrator).toHaveBeenCalledWith('tenant-smr', { name: 'Ana Pérez', email: 'ana@veeduria.org' });
        expect(block(wrapper).text()).toContain('Ana Pérez');
    });

    it('offers nothing to an active Administrador: replacing one is a pending decision (V3)', async () => {
        const wrapper = await openOrganizations([{ ...smr, administrators: [active] }]);

        expect(block(wrapper).text()).toContain('Activo');
        expect(block(wrapper).findAll('button')).toHaveLength(0);
    });
});

// It. 44d — R-LEG-06: el NIT, la inscripción o los dos.
describe('Los datos legales de una veeduría sin NIT (it. 44d)', () => {
    const trupillos = { id: 'tenant-trupillos', nit: null, identification: 'Resolución 012 de 2026, Personería de Santa Marta', name: 'Veeduría del Parque Los Trupillos', subdomain: 'trupillos.govtrace.localhost', status: 'Activa' };

    it('lists a veeduría without NIT by its registration', async () => {
        const wrapper = await openOrganizations([trupillos]);

        expect(wrapper.get('[data-test="organization-row"]').text()).toContain('Resolución 012 de 2026, Personería de Santa Marta');
    });

    it('Actualización de la inscripción con registro de auditoría: adds the registration to an organization', async () => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationLegalData.mockResolvedValue({ message: 'Los datos legales han sido actualizados.' });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar datos legales').trigger('click');
        await flushPromises();
        await wrapper.get('input#registration-number').setValue('Acta 45 de 2025');
        await wrapper.get('input#registration-authority').setValue('Cámara de Comercio de Santa Marta');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(updateOrganizationLegalData).toHaveBeenCalledWith('tenant-smr', { name: 'Veeduría Ciudadana Santa Marta', nit: '900123456-8', registration_number: 'Acta 45 de 2025', registration_authority: 'Cámara de Comercio de Santa Marta' });
    });
});

describe('La razón social (it. 43f, V8)', () => {
    it('Actualización de la razón social con registro de auditoría: corrects the legal name with the other legal data', async () => {
        fetchOrganizationDetail.mockResolvedValue(smrDetail);
        updateOrganizationLegalData.mockResolvedValue({ message: 'Los datos legales han sido actualizados.' });
        const wrapper = await openOrganizations([smr]);

        await button(wrapper, 'Editar datos legales').trigger('click');
        await flushPromises();
        expect(wrapper.get('label[for="legal-name"]').text()).toBe('Razón social');
        expect(wrapper.get('input#legal-name').element.value).toBe('Veeduría Ciudadana Santa Marta');

        await wrapper.get('input#legal-name').setValue('Veeduría Ciudadana del Distrito de Santa Marta');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(updateOrganizationLegalData).toHaveBeenCalledWith('tenant-smr', { name: 'Veeduría Ciudadana del Distrito de Santa Marta', nit: '900123456-8', registration_number: null, registration_authority: null });
    });
});

