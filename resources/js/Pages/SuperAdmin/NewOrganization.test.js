// Iteración 19 — US-001 y US-002 (UI): dar de alta una organización y,
// opcionalmente en el mismo paso, asignar su Administrador inicial.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import NewOrganization from './NewOrganization.vue';
import { registerOrganization } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const SUCCESS = 'Organización registrada. El subdominio veeduria-smr.govtrace.localhost ya está activo.';

async function fillAndSubmit(wrapper, { name = 'Veeduría Ciudadana Santa Marta', nit = '900123456-8', subdomain = 'veeduria-smr', administratorName = '', administratorEmail = '' } = {}) {
    await wrapper.get('input#name').setValue(name);
    await wrapper.get('input#nit').setValue(nit);
    await wrapper.get('input#subdomain').setValue(subdomain);
    if (administratorName) {
        await wrapper.get('input#administrator-name').setValue(administratorName);
    }
    if (administratorEmail) {
        await wrapper.get('input#administrator-email').setValue(administratorEmail);
    }
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Alta de organización', () => {
    it('Alta exitosa de una organización: registers it with name, NIT and subdomain', async () => {
        registerOrganization.mockResolvedValue({ message: SUCCESS });
        const wrapper = mount(NewOrganization);

        await fillAndSubmit(wrapper);

        expect(registerOrganization).toHaveBeenCalledWith({
            name: 'Veeduría Ciudadana Santa Marta',
            nit: '900123456-8',
            registration_number: null,
            registration_authority: null,
            subdomain: 'veeduria-smr',
            administrator_name: null,
            administrator_email: null,
        });
        expect(wrapper.get('[role="status"]').text()).toBe(SUCCESS);
        expect(wrapper.get('input#name').element.value).toBe('');
    });

    it('Asignación exitosa del Administrador inicial: sends the administrator in the same request', async () => {
        registerOrganization.mockResolvedValue({ message: SUCCESS });
        const wrapper = mount(NewOrganization);

        await fillAndSubmit(wrapper, { administratorName: 'Ana Pérez', administratorEmail: 'ana.perez@veeduria-smr.org' });

        expect(registerOrganization).toHaveBeenCalledWith({
            name: 'Veeduría Ciudadana Santa Marta',
            nit: '900123456-8',
            registration_number: null,
            registration_authority: null,
            subdomain: 'veeduria-smr',
            administrator_name: 'Ana Pérez',
            administrator_email: 'ana.perez@veeduria-smr.org',
        });
    });

    it.each([
        ['nit', 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.'],
        ['subdomain', 'El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.'],
        ['name', 'El nombre de la organización debe tener entre 3 y 150 caracteres.'],
        ['administrator_email', 'El correo electrónico no tiene un formato válido.'],
    ])('shows the server rejection under the right field: %s', async (field, message) => {
        registerOrganization.mockRejectedValue({ response: { status: 422, data: { message, errors: { [field]: [message] } } } });
        const wrapper = mount(NewOrganization);

        await fillAndSubmit(wrapper);

        expect(wrapper.get('[role="alert"]').text()).toBe(message);
    });

    it('does not send the administrator email alone, without a name', async () => {
        const wrapper = mount(NewOrganization);

        await fillAndSubmit(wrapper, { administratorEmail: 'ana.perez@veeduria-smr.org' });

        expect(registerOrganization).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Escriba también el nombre del Administrador inicial.');
    });
});

// It. 44d — R-LEG-06: una veeduría sin NIT se identifica con su inscripción.
describe('Una veeduría sin NIT (it. 44d)', () => {
    it('Alta de una veeduría sin NIT, con su inscripción: sends the registration instead of the NIT', async () => {
        registerOrganization.mockResolvedValue({ message: SUCCESS });
        const wrapper = mount(NewOrganization);

        await wrapper.get('input#registration-number').setValue('Resolución 012 de 2026');
        await wrapper.get('input#registration-authority').setValue('Personería de Santa Marta');
        await fillAndSubmit(wrapper, { name: 'Veeduría del Parque Los Trupillos', nit: '', subdomain: 'trupillos' });

        expect(registerOrganization).toHaveBeenCalledWith({
            name: 'Veeduría del Parque Los Trupillos',
            nit: null,
            registration_number: 'Resolución 012 de 2026',
            registration_authority: 'Personería de Santa Marta',
            subdomain: 'trupillos',
            administrator_name: null,
            administrator_email: null,
        });
    });

    it('says that a veeduría without NIT is identified by its registration', () => {
        const wrapper = mount(NewOrganization);

        expect(wrapper.text()).toContain('Si no tiene NIT, su inscripción: el número de la resolución o el acta, y la personería o la cámara de comercio que la registró.');
        expect(wrapper.get('label[for="nit"]').text()).toBe('NIT (con dígito de verificación, si tiene)');
    });
});


describe('Desde una solicitud de alta (it. 43k, V10)', () => {
    const request = { id: 1, name: 'Veeduría Ciudadana de La Pradera', contact_email: 'contacto@lapradera.org', registration_number: 'Resolución 045 de 2026', registration_authority: 'Personería de Medellín' };

    it('El Super Administrador aprueba una solicitud: the form comes with its name, registration and contact as the initial Administrador', () => {
        const wrapper = mount(NewOrganization, { props: { request } });

        expect(wrapper.get('[data-test="from-request"]').text()).toContain('Viene de la solicitud de alta de Veeduría Ciudadana de La Pradera.');
        expect(wrapper.get('input#name').element.value).toBe('Veeduría Ciudadana de La Pradera');
        expect(wrapper.get('input#registration-number').element.value).toBe('Resolución 045 de 2026');
        expect(wrapper.get('input#registration-authority').element.value).toBe('Personería de Medellín');
        expect(wrapper.get('input#administrator-email').element.value).toBe('contacto@lapradera.org');
    });

    it('registers it with the request, so the request is approved', async () => {
        registerOrganization.mockResolvedValue({ message: SUCCESS });
        const wrapper = mount(NewOrganization, { props: { request } });

        await wrapper.get('input#subdomain').setValue('la-pradera');
        await wrapper.get('input#administrator-name').setValue('Contacto de La Pradera');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(registerOrganization).toHaveBeenCalledWith(expect.objectContaining({ name: 'Veeduría Ciudadana de La Pradera', subdomain: 'la-pradera', administrator_email: 'contacto@lapradera.org', request_id: 1 }));
    });
});
