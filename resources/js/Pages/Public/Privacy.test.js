// Iteración 44e — US-058-LEG: la política de tratamiento de datos personales
// (Ley 1581 de 2012), con el contenido mínimo del Decreto 1074 de 2015 (art.
// 2.2.2.25.3.1). El servidor da la versión y los datos del responsable
// (DataPolicyTest); aquí, lo que la pantalla dice.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Privacy from './Privacy.vue';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));

const controller = { name: 'Fundación GovTrace', identification: 'NIT 901555888-3', address: 'Calle 10 # 20-30, Medellín', email: 'datos@govtrace.org', phone: '604 000 0000' };
const policy = (changes = {}) => ({ version: '2026-09-30', effective_date: '30/09/2026', controller, missing: [], hosting: null, ...changes });
const open = (changes) => mount(Privacy, { props: { policy: policy(changes) } });

describe('La política de tratamiento de datos', () => {
    it('Cualquiera lee la política de tratamiento de datos: every part the law asks for, with a heading', () => {
        const wrapper = open();
        const headings = wrapper.findAll('h2').map((heading) => heading.text());

        expect(wrapper.get('h1').text()).toBe('Política de tratamiento de datos personales');
        expect(headings).toEqual([
            'Quién responde por sus datos',
            'Qué datos tratamos y para qué',
            'Qué queda en la red pública',
            'Sus derechos',
            'Cómo ejercerlos',
            'Desde cuándo rige y cuánto tiempo se guardan',
        ]);
        for (const value of Object.values(controller)) {
            expect(wrapper.text()).toContain(value);
        }
        expect(wrapper.text()).toContain('Rige desde el 30/09/2026');
        // Plazos de la Ley 1581, y la queja ante la SIC.
        expect(wrapper.text()).toContain('10 días hábiles');
        expect(wrapper.text()).toContain('15 días hábiles');
        expect(wrapper.text()).toContain('Superintendencia de Industria y Comercio');
        expect(wrapper.find('[data-test="draft"]').exists()).toBe(false);
    });

    it('La política dice qué queda en la red pública: only fingerprints, and never the exact place of a veedor', () => {
        const text = open().text();

        expect(text).toContain('su huella (un hash SHA-256)');
        expect(text).toContain('La huella no contiene datos personales');
        expect(text).toContain('nunca la exacta');
        expect(text).toContain('OpenStreetMap');
    });

    it('Sin los datos del responsable la política es un borrador: it says what is missing', () => {
        const wrapper = open({ controller: { ...controller, identification: null, phone: null }, missing: ['la identificación (NIT)', 'el teléfono'] });

        expect(wrapper.get('[data-test="draft"]').text()).toContain('Borrador: faltan la identificación (NIT) y el teléfono del responsable.');
        expect(wrapper.text()).toContain('Por completar');
    });

    it('says where the servers are, when production says so', () => {
        expect(open({ hosting: 'Amazon Web Services, en Estados Unidos' }).text()).toContain('Los datos se guardan en servidores de Amazon Web Services, en Estados Unidos.');
    });

    it('says who is responsible, and what happens to the email of a citizen who informs a veeduría (it. 44f)', () => {
        const text = open().text();

        expect(text).toContain('Es el operador central de GovTrace. Las veedurías usan la plataforma como usuarios autorizados.');
        expect(text).toContain('De quien informa a una veeduría');
        expect(text).toContain('se guarda cifrado y la veeduría no lo ve');
    });
});


describe('La retención de los informes de los ciudadanos (it. 45c)', () => {
    it('says the email of a citizen is erased 30 days after the veeduría handles the report', () => {
        const retention = open().findAll('#validity ~ ul li').map((item) => item.text());

        expect(retention).toContain('El correo de quien informa a una veeduría, hasta 30 días después de que la veeduría atiende su informe. El informe atendido queda, sin el correo; el descartado se borra entero.');
        expect(retention.join(' ')).not.toContain('mientras la veeduría esté en GovTrace');
    });
});
