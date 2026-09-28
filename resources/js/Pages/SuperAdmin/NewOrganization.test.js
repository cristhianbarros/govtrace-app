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
