// Iteración 21 — US-007 (UI): el nombre de fantasía y el logo de la veeduría.
// El NIT y el nombre legal se ven, pero no se editan aquí (US-011).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Organization from './Organization.vue';
import { fetchProfile, saveProfile } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const profile = {
    display_name: 'Veeduría Ciudadana Santa Marta',
    legal_name: 'Veeduría Ciudadana Santa Marta',
    nit: '900123456-8',
    subdomain: 'veeduria-smr.govtrace.localhost',
    logo_url: null,
};
const SAVED = 'Los cambios fueron guardados. Sus veedores ya ven el nuevo nombre y logo.';

async function openSettings(current = profile) {
    fetchProfile.mockResolvedValue(current);
    const wrapper = mount(Organization);
    await flushPromises();
    return wrapper;
}

async function chooseLogo(wrapper, file) {
    const input = wrapper.get('input#logo');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
}

const logo = (name, type, bytes = 300 * 1024) => new File([new Uint8Array(bytes)], name, { type });

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Organización', () => {
    it('shows that it is loading, and says when it could not load, with a retry', async () => {
        fetchProfile.mockReturnValue(new Promise(() => {}));
        expect(mount(Organization).text()).toContain('Cargando organización…');

        fetchProfile.mockReset();
        fetchProfile.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(profile);
        const wrapper = mount(Organization);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No se pudo conectar con el servidor.');
        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();
        expect(wrapper.get('input#display-name').element.value).toBe('Veeduría Ciudadana Santa Marta');
    });

    it('Actualización exitosa del nombre y el logo: sends both and confirms', async () => {
        saveProfile.mockResolvedValue({ message: SAVED });
        const wrapper = await openSettings();
        fetchProfile.mockResolvedValue({ ...profile, display_name: 'Ojo Ciudadano SMR', logo_url: '/organization/logo?v=logo-abc' });

        await wrapper.get('input#display-name').setValue('Ojo Ciudadano SMR');
        await chooseLogo(wrapper, logo('logo.png', 'image/png'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const sent = saveProfile.mock.calls[0][0];
        expect(sent.get('display_name')).toBe('Ojo Ciudadano SMR');
        expect(sent.get('logo').name).toBe('logo.png');
        expect(wrapper.get('[role="status"]').text()).toBe(SAVED);
        expect(wrapper.get('img').attributes('src')).toBe('/organization/logo?v=logo-abc');
    });

    it.each([
        ['Logo con formato no permitido', logo('logo.gif', 'image/gif'), 'El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG.'],
        ['Logo que supera los 2 MB', logo('logo.png', 'image/png', Math.round(2.5 * 1024 * 1024)), 'El tamaño de la imagen supera el límite permitido de 2 MB.'],
    ])('%s: says so before uploading it', async (_scenario, file, message) => {
        const wrapper = await openSettings();

        await chooseLogo(wrapper, file);
        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).toContain(message);
        expect(saveProfile).not.toHaveBeenCalled();
    });

    it.each([
        ['display_name', 'El nombre de fantasía debe tener entre 3 y 100 caracteres.'],
        ['logo', 'La imagen es demasiado pequeña. Las dimensiones mínimas requeridas son de al menos 128x128 píxeles.'],
    ])('shows why the server refused the %s', async (field, message) => {
        saveProfile.mockRejectedValue({ response: { status: 422, data: { message, errors: { [field]: [message] } } } });
        const wrapper = await openSettings();

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('form [role="alert"]').text()).toBe(message);
    });

    it('El NIT no es editable desde la configuración de la veeduría: it is shown as text only', async () => {
        const wrapper = await openSettings();

        expect(wrapper.text()).toContain('900123456-8');
        expect(wrapper.text()).toContain('Solo el Super Administrador lo cambia, a solicitud formal.');
        expect(wrapper.find('input#nit').exists()).toBe(false);
        expect(wrapper.findAll('input').map((input) => input.attributes('id'))).toEqual(['display-name', 'logo']);
    });

    it('leads to the audit log of the organization', async () => {
        const wrapper = await openSettings();

        expect(wrapper.get('main').find('a[href="/admin/audit"]').text()).toBe('Ver el registro de auditoría');
    });
});

describe('Más de la organización (it. 29)', () => {
    it('leads to the summary of the territory and to the authorization of the Super Administrador', async () => {
        const wrapper = await openSettings();

        expect(wrapper.get('main').get('a[href="/admin/summary"]').text()).toBe('Resumen del territorio');
        expect(wrapper.get('main').get('a[href="/admin/authorization"]').text()).toBe('Autorización al Super Administrador');
    });
});

// It. 44d — R-LEG-06.
describe('Una veeduría sin NIT (it. 44d)', () => {
    it('shows its registration, and no NIT', async () => {
        const wrapper = await openSettings({ ...profile, nit: null, registration_number: 'Resolución 012 de 2026', registration_authority: 'Personería de Santa Marta' });

        expect(wrapper.text()).toContain('Inscripción');
        expect(wrapper.text()).toContain('Resolución 012 de 2026, Personería de Santa Marta');
        expect(wrapper.text()).toContain('Sin NIT');
    });
});

