// Iteración 43g — V7: el Super Administrador reporta en nombre de una
// organización que lo autorizó (US-042-SEC). La regla la prueba el servidor
// (SuperAdminAuthorizationTest, ReportOnBehalfScreenTest); aquí, la pantalla:
// busca la obra en el territorio de esa organización y envía el mismo reporte
// que la PWA a su ruta del panel global.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReportOnBehalf from './ReportOnBehalf.vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import { searchContractsOf, sendReportOnBehalf } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js', () => ({ searchContracts: vi.fn(async () => []), searchContractsOf: vi.fn(() => vi.fn(async () => [])), sendReportOnBehalf: vi.fn(), logout: vi.fn() }));

const REFUSED = 'No cuenta con una autorización activa de la organización para realizar esta acción.';
const organization = { id: 'tenant-smr', name: 'Veeduría Ciudadana Santa Marta' };
const contract = { secop_contract_id: 'CO1.PCCNTR.1234567', object: 'Pavimentación Calle 30', entity_name: 'Alcaldía Distrital de Santa Marta' };

function preciseGps() {
    const getCurrentPosition = vi.fn((ok) => ok({ coords: { latitude: 11.2419, longitude: -74.199, accuracy: 12 }, timestamp: Date.parse('2026-09-28T15:00:00Z') }));
    Object.defineProperty(window.navigator, 'geolocation', { value: { getCurrentPosition }, configurable: true });
}

const open = (props = {}) => mount(ReportOnBehalf, { props: { organization, authorizedUntil: '2026-10-25T15:00:00+00:00', refusal: null, ...props } });

beforeEach(() => vi.clearAllMocks());

describe('Reportar en nombre de una organización (it. 43g, V7)', () => {
    it('says in whose name it reports, until when, and that the report goes to the audit log', () => {
        const text = open().text();

        expect(text).toContain('Veeduría Ciudadana Santa Marta');
        expect(text).toContain('25/10/2026');
        expect(text).toContain('El reporte queda a nombre de la organización y en el registro de auditoría. La veeduría lo revisa antes de publicarlo, como los demás.');
    });

    it('searches the worksite in the territory of that organization', () => {
        open();

        expect(searchContractsOf).toHaveBeenCalledWith('tenant-smr');
    });

    it('sends the report to the route of that organization in the global panel, and confirms it', async () => {
        preciseGps();
        sendReportOnBehalf.mockResolvedValue({ id: 42 });
        const wrapper = open();

        wrapper.findComponent(ContractSearch).vm.$emit('select', contract);
        await flushPromises();
        await wrapper.get('input[value="Avance"]').setValue(true);
        wrapper.findComponent(EvidencePicker).vm.$emit('update:modelValue', [{ kind: 'photo', file: new File(['foto'], 'foto.jpg', { type: 'image/jpeg' }), sha256: 'a'.repeat(64) }]);
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sendReportOnBehalf).toHaveBeenCalledTimes(1);
        const [id, form] = sendReportOnBehalf.mock.calls[0];
        expect(id).toBe('tenant-smr');
        expect(form.get('secop_contract_id')).toBe('CO1.PCCNTR.1234567');
        expect(form.get('classification')).toBe('Avance');
        expect(form.getAll('hashes[]')).toEqual(['a'.repeat(64)]);
        expect(wrapper.get('[role="status"]').text()).toBe('Reporte recibido en nombre de Veeduría Ciudadana Santa Marta. Se sella como los demás, y la veeduría lo revisa antes de publicarlo.');
    });

    it('says why the location of the worksite was left to confirm, when it was (it. 46f)', async () => {
        preciseGps();
        const pending = 'La ubicación de esta obra queda por confirmar: su reporte se tomó a 42.3 km de Santa Marta, y para fijar una obra hay que estar a menos de 30 km de su municipio. La veeduría la revisará.';
        sendReportOnBehalf.mockResolvedValue({ id: 42, location_pending: pending });
        const wrapper = open();

        wrapper.findComponent(ContractSearch).vm.$emit('select', contract);
        await flushPromises();
        await wrapper.get('input[value="Avance"]').setValue(true);
        wrapper.findComponent(EvidencePicker).vm.$emit('update:modelValue', [{ kind: 'photo', file: new File(['foto'], 'foto.jpg', { type: 'image/jpeg' }), sha256: 'a'.repeat(64) }]);
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="location-pending"]').text()).toBe(pending);
    });

    it('shows the message of US-042-SEC, and no form, when the authorization is not in force', () => {
        const wrapper = open({ authorizedUntil: null, refusal: REFUSED });

        expect(wrapper.get('[role="alert"]').text()).toBe(REFUSED);
        expect(wrapper.findComponent(ContractSearch).exists()).toBe(false);
        expect(wrapper.findAll('a').find((link) => link.text() === 'Volver a las organizaciones').attributes('href')).toBe('/admin/organizations');
    });
});
