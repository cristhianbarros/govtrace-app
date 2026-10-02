// Iteración 36, it. 40d — la página del dominio central. No hay un mapa global
// (R-MAP-01): es la puerta de entrada — qué es GovTrace, cómo funciona, y el
// directorio de veedurías, cada una con el enlace a su mapa (V5).
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Home from './Home.vue';
import { requestOrganization } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

beforeEach(() => vi.clearAllMocks());

const ORGANIZATIONS = [
    { name: 'Ojo Ciudadano Ciénaga', territory: 'Ciénaga', url: 'http://ojo-cienaga.govtrace.localhost:8080/', suspended: true },
    { name: 'Veeduría Ciudadana Santa Marta', territory: 'Magdalena', url: 'http://veeduria-smr.govtrace.localhost:8080/', suspended: false },
];

describe('Inicio del dominio central', () => {
    it('says what GovTrace is and how it works, in three steps and plain words', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });

        expect(wrapper.get('header').text()).toContain('GovTrace');
        expect(wrapper.get('h1').text()).toBe('Veeduría ciudadana de obras públicas');
        const steps = wrapper.findAll('[data-test="how"] li').map((step) => step.text());
        expect(steps).toHaveLength(3);
        expect(steps[1]).toContain('sello digital');
        expect(wrapper.text()).not.toContain('subdominio');
    });

    it('Llegar al mapa de una veeduría desde el Inicio: each veeduría with its territory and the way to its map', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });
        const listed = wrapper.findAll('[data-test="organization"]');

        expect(listed.map((row) => row.get('a').attributes('href'))).toEqual(['http://ojo-cienaga.govtrace.localhost:8080/', 'http://veeduria-smr.govtrace.localhost:8080/']);
        expect(listed[1].text()).toContain('Veeduría Ciudadana Santa Marta');
        expect(listed[1].text()).toContain('Magdalena');
        expect(listed[1].get('a').text()).toBe('Ver su mapa de obras');
        expect(listed[0].text()).toContain('Suspendida por ahora: sus evidencias siguen a la vista.');
    });

    it('says so when there is no veeduría yet, and keeps the way in for the administrators at the bottom', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.text()).toContain('Aún no hay veedurías publicando en GovTrace.');
        expect(wrapper.get('footer a[href="/login"]').text()).toBe('Acceso para administradores de GovTrace');
    });
});

describe('La política de datos (it. 44e)', () => {
    it('La política está enlazada donde se entra: the footer of the Home', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.findAll('a').find((link) => link.text() === 'Política de tratamiento de datos').attributes('href')).toBe('/privacidad');
    });
});

describe('Más imágenes en el Inicio (it. 40f)', () => {
    it('draws each step of how it works: the photo, its seal and the map', () => {
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });

        expect(wrapper.findAll('[data-test="how"] li svg').map((art) => art.attributes('data-illustration'))).toEqual(['evidence', 'validator', 'map']);
    });

    it('draws the neighbors waiting when there is no veeduría yet', () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        expect(wrapper.get('#veedurias [data-test="empty"]').text()).toBe('Aún no hay veedurías publicando en GovTrace.');
        expect(wrapper.get('#veedurias [data-test="empty"] svg').attributes('data-illustration')).toBe('team');
    });
});

describe('Una veeduría pide su alta (it. 43k, V10)', () => {
    // It. 46b: la resolución o el certificado de inscripción, en PDF.
    const RESOLUTION = new File(['%PDF-1.7'], 'resolucion.pdf', { type: 'application/pdf' });
    const NEEDS_THE_PDF = 'Adjunte la resolución o el certificado de inscripción en PDF, de hasta 10 MB.';

    async function attach(form, file) {
        const input = form.get('input#request-document');
        Object.defineProperty(input.element, 'files', { value: file ? [file] : [], configurable: true });
        await input.trigger('change');
    }

    async function fillRequest(wrapper, { authorize = true, document = RESOLUTION } = {}) {
        const form = wrapper.get('form[data-test="organization-request"]');
        await form.get('input#request-name').setValue('Veeduría Ciudadana de La Pradera');
        await form.get('input#request-email').setValue('contacto@lapradera.org');
        await form.get('input#request-resolution').setValue('Resolución 045 de 2026');
        await form.get('input#request-authority').setValue('Personería de Medellín');
        await attach(form, document);
        if (authorize) {
            await form.get('input#request-authorization').setValue(true);
        }
        await form.trigger('submit');
        await flushPromises();
        return form;
    }

    it('Una veeduría pide su alta desde el Inicio: sends the four data and its authorization, and says what follows', async () => {
        requestOrganization.mockResolvedValue({ message: 'Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a contacto@lapradera.org.' });
        const wrapper = mount(Home, { props: { organizations: ORGANIZATIONS } });

        await fillRequest(wrapper);

        expect(requestOrganization).toHaveBeenCalledWith({
            name: 'Veeduría Ciudadana de La Pradera',
            contact_email: 'contacto@lapradera.org',
            registration_number: 'Resolución 045 de 2026',
            registration_authority: 'Personería de Medellín',
            document: RESOLUTION,
            data_authorization: true,
            website: '',
        });
        expect(wrapper.get('[data-test="request-sent"]').text()).toBe('Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a contacto@lapradera.org.');
    });

    it('Sin autorizar el tratamiento de datos no se envía la solicitud', async () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        const form = await fillRequest(wrapper, { authorize: false });

        expect(requestOrganization).not.toHaveBeenCalled();
        expect(form.get('[role="alert"]').text()).toBe('Para enviar la solicitud, autorice el tratamiento de sus datos personales.');
    });

    it('La solicitud necesita el PDF de la resolución o del certificado de inscripción: without it, nothing is sent', async () => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        const form = await fillRequest(wrapper, { document: null });

        expect(requestOrganization).not.toHaveBeenCalled();
        expect(form.get('#request-document-hint').text()).toBe(NEEDS_THE_PDF);
    });

    it.each([
        ['a file that is not a PDF', new File(['hola'], 'foto.jpg', { type: 'image/jpeg' })],
        ['a PDF of more than 10 MB', Object.defineProperty(new File(['%PDF-1.7'], 'grande.pdf', { type: 'application/pdf' }), 'size', { value: 10 * 1024 * 1024 + 1 })],
    ])('does not send %s', async (_case, file) => {
        const wrapper = mount(Home, { props: { organizations: [] } });

        const form = await fillRequest(wrapper, { document: file });

        expect(requestOrganization).not.toHaveBeenCalled();
        expect(form.get('#request-document-hint').text()).toBe(NEEDS_THE_PDF);
    });

    it('asks for the resolution or the registration of a personería or a chamber of commerce, and only takes PDF', () => {
        const form = mount(Home, { props: { organizations: [] } }).get('form[data-test="organization-request"]');

        expect(form.get('label[for="request-resolution"]').text()).toBe('Número de la resolución o de la matrícula');
        expect(form.get('label[for="request-authority"]').text()).toBe('Personería o cámara de comercio que la registró');
        expect(form.get('input#request-document').attributes('accept')).toBe('application/pdf,.pdf');
    });

    it('links the data policy beside the checkbox, and hides from people the field only robots fill', () => {
        const form = mount(Home, { props: { organizations: [] } }).get('form[data-test="organization-request"]');

        expect(form.get('a[href="/privacidad"]').text()).toBe('Lea la política de tratamiento de datos');
        const trap = form.get('input[name="website"]');
        expect(trap.attributes('tabindex')).toBe('-1');
        expect(trap.element.closest('[aria-hidden="true"]')).not.toBeNull();
    });
});

