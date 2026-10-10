// Iteración 16 — US-008 (UI): "Nuevo Reporte", la pantalla central del
// veedor. Buscar la obra → GPS (con reintento) → clasificación y comentario
// → adjuntos → enviar a POST /reports. El servidor vuelve a validar todo
// (it. 10 y 11); aquí, lo que la app hace y muestra.
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NewReport from './NewReport.vue';
import WorksiteBrowser from '@/Components/WorksiteBrowser.vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import { loadDetector } from '@/lib/evidence/faces.js';
import { configureOutbox, outboxState } from '@/composables/useOutbox.js';
import { createOutbox, memoryStore } from '@/lib/outbox.js';
import { browseContracts, fetchNearbyWorksites, sendReport } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/lib/evidence/faces.js', () => ({ loadDetector: vi.fn(async () => ({})) }));
vi.mock('@/services/api.js', () => ({ browseContracts: vi.fn(), fetchNearbyWorksites: vi.fn(), sendReport: vi.fn(), logout: vi.fn() }));

/** Lo que responde la lista de obras (it. 47a), sin obras ni cercanas. */
const browsed = (more = {}) => ({
    municipality: { code: '47001', name: 'Santa Marta' },
    notice: null,
    municipalities: [{ code: '47001', name: 'Santa Marta', count: 1 }],
    entities: [],
    work_types: [],
    data: [],
    has_more: false,
    nearby: null,
    ...more,
});

const GPS_DENIED =
    'GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.';
const SUCCESS = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = { secop_contract_id: 'CO1.PCCNTR.1234567', object: 'Pavimentación Calle 30', entity_name: 'Alcaldía Distrital de Santa Marta' };
const reading = (accuracy) => ({ coords: { latitude: 11.2419, longitude: -74.199, accuracy }, timestamp: Date.parse('2026-09-28T15:00:00Z') });
const evidence = (n) => ({ kind: 'photo', file: new File([`foto ${n}`], `foto${n}.jpg`, { type: 'image/jpeg' }), sha256: String(n).repeat(64), blurs: { faces: n, dismissed: 0, manual: 1 } });

/**
 * El GPS del teléfono (it. 47b: la pantalla lo sigue con watchPosition). La
 * primera respuesta llega al seguirlo; las demás, a cada lectura puntual
 * (getCurrentPosition), y si se acaban, esa lectura no llega. emit() entrega
 * otra lectura a quien lo sigue.
 */
function phoneGps(...answers) {
    const [first, ...more] = answers;
    const phone = { watchers: [] };
    const reply = (answer, ok, fail) => (answer.code ? fail(answer) : ok(answer));
    phone.geolocation = {
        watchPosition: vi.fn((ok, fail) => {
            phone.watchers.push({ ok, fail });
            if (first) reply(first, ok, fail);
            return phone.watchers.length - 1;
        }),
        clearWatch: vi.fn(),
        getCurrentPosition: vi.fn((ok, fail) => {
            const answer = more.shift();
            if (answer) reply(answer, ok, fail);
        }),
    };
    phone.emit = (answer) => reply(answer, phone.watchers.at(-1).ok, phone.watchers.at(-1).fail);
    Object.defineProperty(window.navigator, 'geolocation', { value: phone.geolocation, configurable: true });
    return phone;
}

/**
 * Con la obra ya elegida en la lista. La lectura del GPS es una para toda la
 * pantalla (it. 47b): la lista y el reporte usan la misma.
 */
async function onWorksite(...gpsAnswers) {
    phoneGps(...gpsAnswers);
    const wrapper = mount(NewReport);
    await flushPromises();
    wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
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
    // El reloj, fijo unos minutos después de la captura de ejemplo: la bandeja de salida descarta lo
    // capturado hace más de 7 días, y con la fecha real estos reportes "vencían" una semana después.
    // It. 47b: 10 s después de la lectura de ejemplo, que así es reciente (el reporte usa una de 30 s o menos).
    vi.useFakeTimers({ toFake: ['Date'], now: new Date('2026-09-28T15:00:10Z') });
    sendReport.mockReset();
    browseContracts.mockReset();
    browseContracts.mockResolvedValue(browsed());
    configureOutbox({ store: memoryStore() });
});
afterEach(() => {
    vi.useRealTimers();
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
        // It. 46e: lo que se difuminó en cada foto, para la Bandeja.
        expect(JSON.parse(sent.get('blurs'))).toEqual([{ faces: 1, dismissed: 0, manual: 1 }, { faces: 2, dismissed: 0, manual: 1 }]);
        expect(wrapper.text()).toContain(SUCCESS);
    });

    it('says, after sending, why the location of the worksite was left to confirm (it. 46f)', async () => {
        const pending = 'La ubicación de esta obra queda por confirmar: su reporte se tomó a 42.3 km de Santa Marta, y para fijar una obra hay que estar a menos de 30 km de su municipio. La veeduría la revisará.';
        sendReport.mockResolvedValue({ id: 42, location_pending: pending });
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain(SUCCESS);
        expect(wrapper.get('[data-test="location-pending"]').text()).toBe(pending);

        // El siguiente reporte empieza sin ese aviso.
        wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
        await flushPromises();
        expect(wrapper.find('[data-test="location-pending"]').exists()).toBe(false);
    });

    it('Permiso de GPS denegado: the report cannot be created, and the veedor sees why', async () => {
        const wrapper = await onWorksite({ code: 1, PERMISSION_DENIED: 1 });

        expect(wrapper.text()).toContain(GPS_DENIED);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('Precisión mínima del GPS de 50 m: with 51 m the app waits until the signal is good (it. 47b), and only then can send', async () => {
        phoneGps(reading(51));
        const wrapper = mount(NewReport);
        await flushPromises();
        wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
        await flushPromises();

        expect(wrapper.get('[data-test="gps-waiting"]').text()).toBe('Buscando señal GPS: 51 m. Se necesitan 50 m o menos.');
        await fillReport(wrapper);
        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();

        window.navigator.geolocation.watchPosition.mock.calls.at(-1)[0](reading(15));
        await flushPromises();

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

    it.each([
        ['Un veedor en campo intenta enviar un reporte con su organización suspendida', 'La organización veedora ha sido temporalmente suspendida. Contacte a soporte'],
        ['El veedor desactivado intenta sincronizar su cola local', 'Su cuenta ha sido desactivada. No es posible sincronizar nuevos reportes.'],
    ])('%s: shows why, and keeps the report in the phone', async (_scenario, message) => {
        sendReport.mockRejectedValue({ response: { status: 403, data: { message } } });
        const wrapper = await onWorksite(reading(10));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain(message);
        expect(wrapper.text()).not.toContain(SUCCESS);
        expect(wrapper.text()).toContain('foto1.jpg');
    });

    it('shows the name and logo the organization chose, as soon as it changes them (US-007)', async () => {
        page.props.organization = 'Ojo Ciudadano SMR';
        page.props.organizationLogo = '/organization/logo?v=logo-abc';
        const wrapper = mount(NewReport);

        expect(wrapper.get('header').text()).toContain('Ojo Ciudadano SMR');
        expect(wrapper.get('header img').attributes('src')).toBe('/organization/logo?v=logo-abc');
        expect(wrapper.get('h1').text()).toBe('Nuevo Reporte');

        page.props.organization = 'Veeduría Ciudadana Santa Marta';
        page.props.organizationLogo = null;
    });
});

describe('Nuevo Reporte sin conexión (US-018)', () => {
    const SAVED = '📵 Sin conexión. Reporte guardado en el dispositivo. Se enviará automáticamente cuando recupere la señal.';
    const FULL = '⚠️ Almacenamiento local lleno. Conéctese a internet para sincronizar los reportes pendientes antes de crear uno nuevo.';
    const noSignal = Object.assign(new Error('Network Error'), { request: {} });

    /** A shared outbox that already holds `count` reports. */
    async function outboxHolding(count) {
        const store = memoryStore();
        const outbox = createOutbox(store);
        for (let i = 0; i < count; i++) {
            await outbox.add({ fields: { captured_at: new Date().toISOString() }, hashes: ['ab'.repeat(32)], files: [new File(['x'], 'x.jpg')] });
        }
        configureOutbox({ store });
        return outbox;
    }

    it('Guardado sin conexión: keeps the report in the phone, as captured, and says so', async () => {
        const outbox = await outboxHolding(1);
        sendReport.mockRejectedValue(noSignal);
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe(SAVED);
        const pending = await outbox.pending();
        expect(pending).toHaveLength(2);
        const saved = pending.find((record) => record.fields.captured_at === '2026-09-28T15:00:00.000Z');
        expect(saved.fields).toMatchObject({ secop_contract_id: 'CO1.PCCNTR.1234567', latitude: '11.2419', longitude: '-74.199', classification: 'Retraso' });
        expect(saved.files.map((file) => file.name)).toEqual(['foto1.jpg', 'foto2.jpg']);
        expect(outboxState.count).toBe(2);
    });

    it('does not even try when the phone knows it has no signal', async () => {
        const outbox = await outboxHolding(0);
        const wrapper = await onWorksite(reading(15));
        Object.defineProperty(window.navigator, 'onLine', { value: false, configurable: true });

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sendReport).not.toHaveBeenCalled();
        expect(await outbox.pending()).toHaveLength(1);
        delete window.navigator.onLine;
    });

    it('keeps the report in the phone when the veedor went past the reports allowed per hour (it. 41), and sends it later', async () => {
        const RATE_LIMITED = '⏳ Llegó al límite de reportes por hora. Su reporte quedó guardado en el dispositivo y se enviará automáticamente más tarde.';
        const outbox = await outboxHolding(0);
        sendReport.mockRejectedValue({ response: { status: 429, data: { message: 'Llegó al límite de 30 reportes por hora. Los siguientes se pueden enviar más tarde.' } } });
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe(RATE_LIMITED);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        const pending = await outbox.pending();
        expect(pending).toHaveLength(1);
        expect(pending[0].fields).toMatchObject({ secop_contract_id: 'CO1.PCCNTR.1234567', captured_at: '2026-09-28T15:00:00.000Z' });
    });

    it.each([502, 503, 504])('keeps the report in the phone when GovTrace is being updated (%i, it. 42c), and sends it later', async (status) => {
        const UPDATING = '🔄 GovTrace se está actualizando. Su reporte quedó guardado en el dispositivo y se enviará automáticamente en unos minutos.';
        const outbox = await outboxHolding(0);
        sendReport.mockRejectedValue({ response: { status, data: { message: 'Estamos actualizando GovTrace. Vuelva a intentar en un minuto.' } } });
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe(UPDATING);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(await outbox.pending()).toHaveLength(1);
    });

    it('Mensaje de almacenamiento lleno: with 10 pending, the new one is not kept, and the report stays on screen', async () => {
        const outbox = await outboxHolding(10);
        sendReport.mockRejectedValue(noSignal);
        const wrapper = await onWorksite(reading(15));

        await fillReport(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain(FULL);
        expect(await outbox.pending()).toHaveLength(10);
        expect(wrapper.find('form').exists()).toBe(true);
    });
});

describe('Obras cercanas (US-019)', () => {
    const NONE = '📍 No se encontraron obras a menos de 500m. Utilice el buscador para encontrarla por nombre o contrato.';
    const suggestion = (meters) => ({
        worksite_id: meters,
        name: `Obra a ${meters} m`,
        distance_meters: meters,
        contract: { secop_contract_id: `CO1.PCCNTR.${meters}`, object: `Obra a ${meters} m`, entity_name: 'Alcaldía Distrital de Santa Marta' },
    });

    async function openNearby(nearby, ...gpsAnswers) {
        browseContracts.mockResolvedValue(browsed({ nearby }));
        phoneGps(...gpsAnswers);
        const wrapper = mount(NewReport);
        await flushPromises();
        return wrapper;
    }

    it('Hasta 5 obras dentro de 500 m ordenadas por distancia: from where the veedor is, the closest first', async () => {
        const wrapper = await openNearby([50, 120, 200, 310, 420].map(suggestion), reading(15), reading(15));

        expect(browseContracts).toHaveBeenCalledWith({ latitude: 11.2419, longitude: -74.199, accuracy: 15, page: 1 });
        const offered = wrapper.findAll('[data-test="nearby"]');
        expect(offered.map((item) => item.get('[data-test="distance"]').text())).toEqual(['a 50 m', 'a 120 m', 'a 200 m', 'a 310 m', 'a 420 m']);
        expect(offered[0].text()).toContain('Obra a 50 m');

        await offered[1].trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Obra a 120 m');
        expect(wrapper.find('[data-test="nearby"]').exists()).toBe(false);
    });

    it('Ninguna obra cercana: says so, and the search is still there', async () => {
        const wrapper = await openNearby([], reading(15));

        expect(wrapper.text()).toContain(NONE);
        expect(wrapper.find('input#work-search').exists()).toBe(true);
    });

    it('opens the list even when the GPS is denied: without the location, and without nearby works (it. 47a)', async () => {
        const wrapper = await openNearby(null, { code: 1, PERMISSION_DENIED: 1 });

        expect(browseContracts).toHaveBeenCalledWith({ page: 1 });
        expect(wrapper.find('[data-test="nearby-section"]').exists()).toBe(false);
        expect(wrapper.find('input#work-search').exists()).toBe(true);
    });
});

describe('El botón de enviar dice qué falta (it. 40b)', () => {
    const missing = (wrapper) => wrapper.find('[data-test="missing"]');

    it('El botón de enviar dice qué falta: next to the button, in words, until nothing is missing', async () => {
        const wrapper = await onWorksite(reading(15));

        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
        expect(missing(wrapper).text()).toContain('Para enviar falta:');
        expect(missing(wrapper).text()).toContain('decir qué vio en la obra');
        expect(missing(wrapper).text()).toContain('adjuntar al menos una foto o un PDF');
        expect(submitButton(wrapper).attributes('aria-describedby')).toBe('send-missing');

        await fillReport(wrapper, { evidences: [] });
        expect(missing(wrapper).text()).not.toContain('decir qué vio en la obra');
        expect(missing(wrapper).text()).toContain('adjuntar al menos una foto o un PDF');

        wrapper.findComponent(EvidencePicker).vm.$emit('update:modelValue', [evidence(1)]);
        await flushPromises();
        expect(missing(wrapper).exists()).toBe(false);
        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('says it is waiting for the GPS while there is no position yet', async () => {
        Object.defineProperty(window.navigator, 'geolocation', { value: { watchPosition: vi.fn(), clearWatch: vi.fn(), getCurrentPosition: vi.fn() }, configurable: true });
        const wrapper = mount(NewReport);
        wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
        await flushPromises();

        expect(missing(wrapper).text()).toContain('la ubicación del GPS');
    });
});

describe('Qué vio en la obra (it. 40c)', () => {
    it('asks what the veedor saw, and explains each answer in a line', async () => {
        const wrapper = await onWorksite(reading(15));
        const question = wrapper.get('fieldset');

        expect(question.get('legend').text()).toBe('¿Qué vio en la obra?');
        expect(question.text()).toContain('La obra avanza: hay trabajo o cambios desde la última vez.');
        expect(question.text()).toContain('Va más lenta de lo previsto, o está detenida por ahora.');
        expect(question.text()).toContain('No hay nadie trabajando y la obra parece dejada.');
    });
});

// It. 46e: con señal, el detector de rostros se descarga al abrir la pantalla, para tenerlo sin señal.
describe('El detector de rostros, listo antes de la foto (it. 46e)', () => {
    it('downloads it when the screen opens with signal, and not without it', async () => {
        mount(NewReport);
        await flushPromises();
        expect(loadDetector).toHaveBeenCalledTimes(1);

        loadDetector.mockClear();
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        mount(NewReport);
        await flushPromises();
        expect(loadDetector).not.toHaveBeenCalled();
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
    });
});

// It. 46e: mientras una foto espera su revisión, el formulario dice qué hacer.
describe('Una foto por revisar (it. 46e)', () => {
    it('asks to finish reviewing the photos before sending', async () => {
        const wrapper = await onWorksite(reading(15));
        await fillReport(wrapper);

        wrapper.findComponent(EvidencePicker).vm.$emit('update:processing', true);
        await flushPromises();

        expect(wrapper.text()).toContain('terminar de revisar las fotos');
        expect(wrapper.text()).not.toContain('terminen de prepararse');
    });
});

describe('El GPS, una sola vez por pantalla (it. 47b)', () => {
    const at = (seconds) => ({ coords: { latitude: 11.2419, longitude: -74.199, accuracy: 15 }, timestamp: Date.parse('2026-09-28T15:00:10Z') - seconds * 1000 });

    it('La app espera una lectura de 50 m o menos y muestra la precisión mientras tanto', async () => {
        fetchNearbyWorksites.mockResolvedValue([]);
        const phone = phoneGps(reading(2000));
        const wrapper = mount(NewReport);
        await flushPromises();

        const nearby = () => wrapper.get('[data-test="nearby-section"]').text();
        expect(nearby()).toContain('Buscando señal GPS: 2000 m. Se necesitan 50 m o menos.');

        phone.emit(reading(120));
        await flushPromises();
        expect(nearby()).toContain('Buscando señal GPS: 120 m. Se necesitan 50 m o menos.');
        expect(fetchNearbyWorksites).not.toHaveBeenCalled();

        phone.emit(reading(18));
        await flushPromises();
        expect(fetchNearbyWorksites).toHaveBeenCalledWith(11.2419, -74.199);
        expect(nearby()).not.toContain('Buscando señal GPS');

        // La misma lectura, en el reporte: la ubicación queda lista.
        wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
        await flushPromises();
        expect(wrapper.text()).toContain('Precisión del GPS: 18 m');
        expect(phone.geolocation.watchPosition).toHaveBeenCalledTimes(1);
    });

    it('Sin una buena lectura en un minuto, la app explica qué hacer', async () => {
        vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'], now: new Date('2026-09-28T15:00:10Z') });
        const phone = phoneGps(reading(300));
        const wrapper = mount(NewReport);
        await vi.advanceTimersByTimeAsync(0);

        await vi.advanceTimersByTimeAsync(60_000);

        expect(wrapper.get('[data-test="slow-gps"] [role="alert"]').text()).toBe(
            'No se consiguió una buena señal del GPS en 1 minuto. Salga a un lugar abierto y revise que su celular tenga activada la ubicación precisa. Luego toque «Intentar de nuevo».',
        );
        await wrapper.get('[data-test="slow-gps"] button').trigger('click');

        expect(wrapper.find('[data-test="slow-gps"]').exists()).toBe(false);
        expect(phone.geolocation.watchPosition).toHaveBeenCalledTimes(2);
    });

    it.each([
        [10, true],
        [30, true],
        [31, false],
    ])('El reporte usa la última lectura del GPS si tiene 30 segundos o menos: %i s', async (seconds, used) => {
        const phone = phoneGps(at(seconds));
        const wrapper = mount(NewReport);
        await flushPromises();
        wrapper.findComponent(WorksiteBrowser).vm.$emit('select', contract);
        await flushPromises();

        if (used) {
            expect(wrapper.text()).toContain('Precisión del GPS: 15 m');
            expect(phone.geolocation.getCurrentPosition).not.toHaveBeenCalled();
            sendReport.mockResolvedValue({});
            await fillReport(wrapper);
            await wrapper.get('form').trigger('submit');
            await flushPromises();
            expect(sendReport.mock.calls[0][0].get('captured_at')).toBe(new Date(at(seconds).timestamp).toISOString());
        } else {
            expect(wrapper.find('[data-test="gps-waiting"]').exists()).toBe(true);
            expect(phone.geolocation.getCurrentPosition).toHaveBeenCalledTimes(1);
        }
    });
});
