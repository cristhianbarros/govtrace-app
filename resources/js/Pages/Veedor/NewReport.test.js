// Iteración 16 — US-008 (UI): "Nuevo Reporte", la pantalla central del
// veedor. Buscar la obra → GPS (con reintento) → clasificación y comentario
// → adjuntos → enviar a POST /reports. El servidor vuelve a validar todo
// (it. 10 y 11); aquí, lo que la app hace y muestra.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NewReport from './NewReport.vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import { sendReport } from '@/services/api.js';

vi.mock('@inertiajs/vue3', () => ({ Head: { render: () => null } }));
vi.mock('@/services/api.js', () => ({ searchContracts: vi.fn(async () => []), sendReport: vi.fn() }));

const GPS_DENIED =
    'GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.';
const SUCCESS = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = { secop_contract_id: 'CO1.PCCNTR.1234567', object: 'Pavimentación Calle 30', entity_name: 'Alcaldía Distrital de Santa Marta' };
const reading = (accuracy) => ({ coords: { latitude: 11.2419, longitude: -74.199, accuracy }, timestamp: Date.parse('2026-09-28T15:00:00Z') });
const evidence = (n) => ({ kind: 'photo', file: new File([`foto ${n}`], `foto${n}.jpg`, { type: 'image/jpeg' }), sha256: String(n).repeat(64) });

/** El GPS del teléfono: cada llamada responde con la siguiente lectura (o el error). */
function phoneGps(...answers) {
    const getCurrentPosition = vi.fn((ok, fail) => {
        const answer = answers.shift();
        return answer.code ? fail(answer) : ok(answer);
    });
    Object.defineProperty(window.navigator, 'geolocation', { value: { getCurrentPosition }, configurable: true });
    return getCurrentPosition;
}

/** Con la obra ya elegida en "Buscar Obra". */
async function onWorksite(...gpsAnswers) {
    phoneGps(...gpsAnswers);
    const wrapper = mount(NewReport);
    wrapper.findComponent(ContractSearch).vm.$emit('select', contract);
    await flushPromises();
    return wrapper;
}

async function fillReport(wrapper, { classification = 'Retraso', comment = 'Obra detenida hace 2 meses', evidences = [evidence(1), evidence(2)] } = {}) {
    if (classification) {
        await wrapper.get(`input[type="radio"][value="${classification}"]`).setValue(true);
    }
    await wrapper.get('textarea#comment').setValue(comment);
    wrapper.findComponent(EvidencePicker).vm.$emit('update:modelValue', evidences);
    await flushPromises();
}

const submitButton = (wrapper) => wrapper.get('button[type="submit"]');

beforeEach(() => {
    sendReport.mockReset();
});
afterEach(() => {
    delete window.navigator.geolocation;
});

describe('Nuevo Reporte', () => {
    it('Reporte exitoso cerca de la obra: sends the files, their hashes, the position, the classification and the comment', async () => {
        sendReport.mockResolvedValue({ id: 42 });
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const sent = sendReport.mock.calls[0][0];
        expect(sent.get('secop_contract_id')).toBe('CO1.PCCNTR.1234567');
        expect(sent.get('classification')).toBe('Retraso');
        expect(sent.get('comment')).toBe('Obra detenida hace 2 meses');
        expect(sent.get('latitude')).toBe('11.2419');
        expect(sent.get('longitude')).toBe('-74.199');
        expect(sent.get('accuracy_meters')).toBe('15');
        expect(sent.get('captured_at')).toBe('2026-09-28T15:00:00.000Z');
        expect(sent.getAll('files[]').map((file) => file.name)).toEqual(['foto1.jpg', 'foto2.jpg']);
        expect(sent.getAll('hashes[]')).toEqual(['1'.repeat(64), '2'.repeat(64)]);
        expect(wrapper.text()).toContain(SUCCESS);
    });

    it('Permiso de GPS denegado: the report cannot be created, and the veedor sees why', async () => {
        const wrapper = await onWorksite({ code: 1, PERMISSION_DENIED: 1 });

        expect(wrapper.text()).toContain(GPS_DENIED);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('Precisión mínima del GPS de 50 m: with 51 m the app asks to retry until the signal is good', async () => {
        const wrapper = await onWorksite(reading(51), reading(15));

        expect(wrapper.text()).toContain('La precisión del GPS es de 51 m y se requieren 50 m o menos. Espere a tener mejor señal y vuelva a intentarlo.');
        await fillReport(wrapper);
        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();

        await wrapper.get('[data-test="retry-gps"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('La precisión del GPS es de 51 m');
        expect(wrapper.text()).toContain('Precisión del GPS: 15 m');
        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('sends a position of exactly 50 m', async () => {
        const wrapper = await onWorksite(reading(50));

        await fillReport(wrapper);

        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('La clasificación es obligatoria: without it the report is not sent', async () => {
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper, { classification: null });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
        expect(sendReport).not.toHaveBeenCalled();
        expect(wrapper.findAll('input[type="radio"]').map((radio) => radio.element.value)).toEqual(['Avance', 'Retraso', 'Abandono']);
    });

    it.each([
        [0, true],
        [500, true],
        [501, false],
    ])('El comentario es opcional y tiene máximo 500 caracteres: %i characters', async (length, sendable) => {
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper, { comment: 'a'.repeat(length) });

        expect(submitButton(wrapper).attributes('disabled') === undefined).toBe(sendable);
        expect(wrapper.text()).toContain(`${length}/500`);
        expect(wrapper.get('textarea#comment').attributes('maxlength')).toBe('500');
    });

    it('does not send a report with no file', async () => {
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper, { evidences: [] });

        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it.each([
        ['Reporte fuera de la geocerca', 'location', 'Se encuentra a 2.3 km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de 500 metros del proyecto.'],
        [
            'El hash del servidor no coincide con el del teléfono',
            'files',
            'Alerta de seguridad: El archivo fue alterado o corrompido durante la transmisión (el hash del servidor no coincide con el de su celular). Por favor, intente de nuevo.',
        ],
    ])('%s: shows the reason the server gives, and keeps the report to try again', async (_scenario, field, message) => {
        sendReport.mockRejectedValue({ response: { status: 422, data: { message, errors: { [field]: [message] } } } });
        const wrapper = await onWorksite(reading(10));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain(message);
        expect(wrapper.text()).not.toContain(SUCCESS);
        expect(wrapper.find('form').exists()).toBe(true);
    });
});
