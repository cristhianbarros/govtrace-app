// Iteración 62 — US-067 (features/US-067.feature): la ruta arriba de cada
// pantalla, "GovTrace › <veeduría> › <panel> › <pantalla>". Cada veeduría vive
// en su subdominio, así que "GovTrace" lleva al Inicio de GovTrace en su propio
// dominio. En el celular, un solo enlace al nivel anterior.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const ORGANIZATION = 'Veeduría BAF Medellín';
const GOVTRACE = 'https://govtrace.duckdns.org/';

function inTheVeeduria(url) {
    page.url = url;
    page.props = { organization: ORGANIZATION, organizationLogo: null, account: null, govtraceUrl: GOVTRACE };
}

function inGovTrace(url) {
    page.url = url;
    page.props = { organization: null, account: null, govtraceUrl: GOVTRACE };
}

/** The full trail: [label, href of its link or null, whether it is the current screen]. */
function trailOf(wrapper) {
    return wrapper.get('nav[aria-label="Ruta"] ol').findAll('[data-crumb]').map((crumb) => [
        crumb.text(),
        crumb.element.tagName === 'A' ? crumb.attributes('href') : null,
        crumb.attributes('aria-current') === 'page',
    ]);
}

/** On the phone: the single link to the level before. */
const backOf = (wrapper) => wrapper.get('nav[aria-label="Ruta"] [data-test="back"]');

describe('La ruta (US-067)', () => {
    it('En el sitio de una veeduría, la ruta lleva de vuelta a GovTrace', () => {
        inTheVeeduria('/');
        const wrapper = mount(AppLayout, { props: { title: ORGANIZATION, sections: true } });

        expect(trailOf(wrapper)).toEqual([
            ['GovTrace', GOVTRACE, false],
            // Es donde está: no es un enlace.
            [ORGANIZATION, null, true],
        ]);
        expect(wrapper.get('nav[aria-label="Ruta"] ol').element.tagName).toBe('OL');
    });

    it('En una pantalla del sitio, la ruta suma la pantalla', () => {
        inTheVeeduria('/worksite/01j9xq3m7v8k2d4f6g8h0jkmnp');
        const wrapper = mount(AppLayout, { props: { title: ORGANIZATION, sections: true, here: 'Pavimentación Calle 30' } });

        expect(trailOf(wrapper)).toEqual([
            ['GovTrace', GOVTRACE, false],
            [ORGANIZATION, '/', false],
            ['Pavimentación Calle 30', null, true],
        ]);
    });

    it('En el panel de la veeduría, la ruta lleva a su sitio y a GovTrace', () => {
        inTheVeeduria('/admin/contracts?page=2');
        const wrapper = mount(AdminLayout, { props: { title: 'Contratos' } });

        expect(trailOf(wrapper)).toEqual([
            ['GovTrace', GOVTRACE, false],
            [ORGANIZATION, '/', false],
            ['Panel', '/admin/inbox', false],
            ['Contratos', null, true],
        ]);
    });

    it('En el panel global, la ruta lleva al Inicio de GovTrace', () => {
        inGovTrace('/admin/sealing');
        const wrapper = mount(SuperAdminLayout, { props: { title: 'Sellado' } });

        expect(trailOf(wrapper)).toEqual([
            ['GovTrace', '/', false],
            ['Panel global', '/admin/organizations', false],
            ['Sellado', null, true],
        ]);
    });

    it('En el celular, la ruta se resume en un enlace a la pantalla anterior', () => {
        inTheVeeduria('/worksite/01j9xq3m7v8k2d4f6g8h0jkmnp');
        const worksite = mount(AppLayout, { props: { title: ORGANIZATION, sections: true, here: 'Pavimentación Calle 30' } });
        expect(backOf(worksite).text()).toBe(`← ${ORGANIZATION}`);
        expect(backOf(worksite).attributes('href')).toBe('/');
        // El celular ve el enlace; el computador, la ruta completa.
        expect(backOf(worksite).classes()).toContain('md:hidden');
        expect(worksite.get('nav[aria-label="Ruta"] ol').classes()).toEqual(expect.arrayContaining(['hidden', 'md:flex']));

        // En la Bandeja, "Panel" es la misma Bandeja: ni es enlace ni es a donde vuelve el celular.
        inTheVeeduria('/admin/inbox');
        const inbox = mount(AdminLayout, { props: { title: 'Bandeja de entrada' } });
        expect(trailOf(inbox)[2]).toEqual(['Panel', null, false]);
        expect(backOf(inbox).text()).toBe(`← ${ORGANIZATION}`);

        // En el mapa de la veeduría, de vuelta a GovTrace.
        inTheVeeduria('/');
        expect(backOf(mount(AppLayout, { props: { title: ORGANIZATION, sections: true } })).attributes('href')).toBe(GOVTRACE);

        // En Organizaciones, "Panel global" es la misma pantalla: de vuelta al Inicio.
        inGovTrace('/admin/organizations');
        const organizations = mount(SuperAdminLayout, { props: { title: 'Organizaciones' } });
        expect(backOf(organizations).text()).toBe('← GovTrace');
        expect(backOf(organizations).attributes('href')).toBe('/');
    });

    it('has no trail in the GovTrace home, the root, nor on a screen that does not ask for one', () => {
        inGovTrace('/');
        expect(mount(AppLayout, { props: { title: 'GovTrace' } }).find('nav[aria-label="Ruta"]').exists()).toBe(false);

        inTheVeeduria('/reports/new');
        expect(mount(AppLayout, { props: { title: ORGANIZATION } }).find('nav[aria-label="Ruta"]').exists()).toBe(false);
    });
});
