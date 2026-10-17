// Iteración 58 — US-059-LEG: la página de un contrato del territorio que ningún
// veedor ha reportado, sin ficha ni ubicación todavía, para informar de él.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Contract from './Contract.vue';
import CitizenReportForm from '@/Components/Public/CitizenReportForm.vue';
import { fetchPublicContract } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const SEAT = { latitude: 11.204679, longitude: -74.199829 };
const MINCA = {
    contract_id: 'CO1.PCCNTR.7654321',
    worksite_id: null,
    name: 'Construcción de la vía a Minca',
    contracts: [{
        secop_contract_id: 'CO1.PCCNTR.7654321',
        object: 'Construcción de la vía a Minca',
        entity_name: 'Gobernación del Magdalena',
        contractor_name: 'Vías del Caribe S.A.S.',
        value: '3400000000.00',
        term_months: 10,
        secop_url: null,
        cancelled: false,
        cancelled_notice: null,
    }],
    seat: SEAT,
};

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', organizationLogo: null, organizationNotice: null };
});

describe('Un contrato sin ficha (it. 58)', () => {
    it('El ciudadano encuentra una obra sin ubicación y le informa a la veeduría: its contract, that it is not on the map, and the form for the contract', async () => {
        fetchPublicContract.mockResolvedValue(MINCA);
        const wrapper = mount(Contract, { props: { contractId: 'CO1.PCCNTR.7654321' } });
        await flushPromises();

        expect(fetchPublicContract).toHaveBeenCalledWith('CO1.PCCNTR.7654321');
        expect(wrapper.get('h1').text()).toBe('Construcción de la vía a Minca');
        expect(wrapper.get('[data-test="unlocated"]').text()).toBe('Esta obra aún no está en el mapa: ningún veedor la ha visitado. Si sabe algo de ella, cuénteselo a la veeduría.');
        expect(wrapper.get('[data-test="contract"]').text()).toContain('Vías del Caribe S.A.S.');
        expect(wrapper.findComponent(CitizenReportForm).props()).toMatchObject({ worksiteId: null, contractId: 'CO1.PCCNTR.7654321', proposeFrom: SEAT });
        expect(wrapper.text()).toContain('Informar a esta veeduría');
    });

    it('says it could not load the work, and tries again', async () => {
        fetchPublicContract.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(MINCA);
        const wrapper = mount(Contract, { props: { contractId: 'CO1.PCCNTR.7654321' } });
        await flushPromises();

        await wrapper.findAll('button').find((button) => button.text() === 'Reintentar').trigger('click');
        await flushPromises();

        expect(wrapper.get('h1').text()).toBe('Construcción de la vía a Minca');
    });

    it('En una pantalla del sitio, la ruta suma la pantalla: the page of a contract ends the trail with its name, and the trail takes back to the map', async () => {
        page.url = '/contract/CO1.PCCNTR.7654321';
        fetchPublicContract.mockResolvedValue(MINCA);
        const wrapper = mount(Contract, { props: { contractId: 'CO1.PCCNTR.7654321' } });
        await flushPromises();

        const crumbs = wrapper.get('nav[aria-label="Ruta"] ol').findAll('[data-crumb]');
        expect(crumbs.at(-1).text()).toBe('Construcción de la vía a Minca');
        expect(wrapper.get('nav[aria-label="Ruta"] [data-test="back"]').attributes('href')).toBe('/');
        expect(wrapper.findAll('a').some((link) => link.text().includes('Volver al mapa'))).toBe(false);
    });
});
