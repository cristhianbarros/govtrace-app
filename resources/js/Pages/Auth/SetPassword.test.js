// Iteración 17 — US-030 (UI): la pantalla del enlace de invitación, donde el
// veedor (o el Administrador inicial) crea su contraseña. El servidor decide
// si el enlace vale (AcceptInvitationTest, it. 5); aquí, lo que la pantalla
// muestra y envía.
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SetPassword from './SetPassword.vue';
import { respondWith, resetInertia, submissions } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => { const inertia = await import('@/testing/inertia.js'); return { Head: { render: () => null }, useForm: inertia.useForm, usePage: inertia.usePage, Link: inertia.Link, router: inertia.router }; });

const RULES = 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.';
const EXPIRED = 'El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.';

const validLink = { valid: true, email: 'carlos@correo.co', token: 'token-del-correo', action: '/set-password/7' };

// It. 44e: quien activa su cuenta autoriza también el tratamiento de sus datos, salvo que el test diga lo contrario.
// It. 45c: y escribe su nombre, salvo que el test diga otro.
async function definePassword(wrapper, password, confirmation = password, { authorize = true, name = 'Carlos Rojas' } = {}) {
    if (name !== null) {
        await wrapper.get('input#name').setValue(name);
    }
    if (authorize) {
        await wrapper.get('input#data-authorization').setValue(true);
    }
    await wrapper.get('input#password').setValue(password);
    await wrapper.get('input#password_confirmation').setValue(confirmation);
    await wrapper.get('form').trigger('submit');
}

beforeEach(resetInertia);

describe('Crear la contraseña', () => {
    it('Activación exitosa de la cuenta: sends the password, its confirmation and the token of the link', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        expect(wrapper.text()).toContain('carlos@correo.co');
        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions).toEqual([
            { url: '/set-password/7', data: { token: 'token-del-correo', name: 'Carlos Rojas', password: 'Veeduria#2026', password_confirmation: 'Veeduria#2026', data_authorization: true } },
        ]);
        expect(wrapper.get('input#password').attributes('autocomplete')).toBe('new-password');
    });

    it('Enlace de invitación vencido: the password cannot be created, and the screen says why', () => {
        const wrapper = mount(SetPassword, { props: { valid: false, message: EXPIRED } });

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it.each([
        ['menos de 8 caracteres', 'Ve#2026'],
        ['sin mayúscula', 'veeduria#2026'],
        ['sin minúscula', 'VEEDURIA#2026'],
        ['sin número', 'Veeduria#abc'],
        ['sin símbolo', 'Veeduria2026'],
    ])('La contraseña debe cumplir las reglas mínimas: %s', async (_rule, password) => {
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, password);

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(RULES);
    });

    it('asks for the same password twice', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, 'Veeduria#2026', 'Veeduria#2025');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Las contraseñas no coinciden.');
    });

    it('shows the server rejection when the link expires while the form is open', async () => {
        respondWith({ token: EXPIRED });
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, 'Veeduria#2026');

        expect(wrapper.get('[role="alert"]').text()).toBe(EXPIRED);
    });
});

// It. 44c — US-057-LEG: el veedor declara, al activar su cuenta, que no tiene impedimentos para serlo.
describe('La declaración de impedimentos del veedor (it. 44c)', () => {
    const REQUIRED = 'Para ser veedor, declare que no está en ninguno de estos casos.';
    const veedorLink = { ...validLink, declaration: true };

    it('El veedor declara sus impedimentos al activar su cuenta: the cases of the law, a checkbox, and the declaration goes with the password', async () => {
        const wrapper = mount(SetPassword, { props: veedorLink });
        const declaration = wrapper.get('[data-test="impediments"]');

        expect(declaration.text()).toContain('Ley 850 de 2003, artículo 19');
        expect(declaration.findAll('li')).toHaveLength(5);
        expect(declaration.text()).toContain('Soy contratista, interventor, proveedor o trabajador de una obra que voy a vigilar');
        await wrapper.get('input#declaration').setValue(true);
        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions).toEqual([
            { url: '/set-password/7', data: { token: 'token-del-correo', name: 'Carlos Rojas', password: 'Veeduria#2026', password_confirmation: 'Veeduria#2026', data_authorization: true, declaration: true } },
        ]);
    });

    it('Sin la declaración no se activa la cuenta de un veedor: nothing is sent, and the screen says what is missing', async () => {
        const wrapper = mount(SetPassword, { props: veedorLink });

        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(REQUIRED);
    });

    it('shows what the server says when it refuses the declaration', async () => {
        respondWith({ declaration: REQUIRED });
        const wrapper = mount(SetPassword, { props: veedorLink });

        await wrapper.get('input#declaration').setValue(true);
        await definePassword(wrapper, 'Veeduria#2026');

        expect(wrapper.get('[role="alert"]').text()).toBe(REQUIRED);
    });

    it('El Administrador activa su cuenta sin declarar impedimentos de veedor: no list, no checkbox', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        expect(wrapper.find('[data-test="impediments"]').exists()).toBe(false);
        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions[0].data).not.toHaveProperty('declaration');
    });
});

// It. 44e — US-058-LEG: al activar su cuenta, cada persona autoriza el tratamiento de sus datos.
describe('La autorización del tratamiento de datos (it. 44e)', () => {
    const AUTHORIZATION_REQUIRED = 'Para crear su cuenta, autorice el tratamiento de sus datos personales.';
    const link = { ...validLink, dataPolicyUrl: '/privacidad' };

    it('Autorizo el tratamiento de mis datos al activar mi cuenta: the link to the policy, the checkbox, and it goes with the password', async () => {
        const wrapper = mount(SetPassword, { props: link });
        const policy = wrapper.get('[data-test="data-policy"]');

        expect(policy.attributes()).toMatchObject({ href: '/privacidad', target: '_blank' });
        await wrapper.get('input#data-authorization').setValue(true);
        await definePassword(wrapper, 'Veeduria#2026');

        expect(submissions[0].data).toMatchObject({ data_authorization: true });
    });

    it('Sin la autorización no se activa la cuenta: nothing is sent, and the screen says what is missing', async () => {
        const wrapper = mount(SetPassword, { props: link });

        await definePassword(wrapper, 'Veeduria#2026', 'Veeduria#2026', { authorize: false });

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain(AUTHORIZATION_REQUIRED);
    });
});

describe('Su nombre (it. 45c)', () => {
    it('asks for the name, and says who will see it', () => {
        const wrapper = mount(SetPassword, { props: validLink });

        expect(wrapper.get('label[for="name"]').text()).toBe('Su nombre');
        expect(wrapper.get('input#name').attributes('autocomplete')).toBe('name');
        expect(wrapper.text()).toContain('Lo ve su veeduría. En el sitio público no aparece su nombre: los reportes llevan un seudónimo.');
    });

    it('starts with the name the account already has', () => {
        expect(mount(SetPassword, { props: { ...validLink, name: 'Carlos Rojas' } }).get('input#name').element.value).toBe('Carlos Rojas');
    });

    it('does not send without a name of at least 2 letters, and says it beside the field', async () => {
        const wrapper = mount(SetPassword, { props: validLink });

        await definePassword(wrapper, 'Veeduria#2026', 'Veeduria#2026', { name: ' C ' });

        expect(submissions).toEqual([]);
        expect(wrapper.text()).toContain('Escriba su nombre: al menos 2 letras.');
    });
});

