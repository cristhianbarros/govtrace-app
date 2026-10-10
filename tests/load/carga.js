// It. 50 — prueba de carga (make load-test): k6 contra una organización de
// pruebas (la que deja tests/e2e/fixture.php). Dos escenarios a la vez:
//   publico: ciudadanos mirando el mapa, la lista de obras y las estadísticas;
//   veedor:  veedores que entran, buscan su obra y envían un reporte con foto.
// Los límites de la app (PUBLIC_REQUESTS_PER_MINUTE por IP, REPORTS_PER_VEEDOR_PER_HOUR)
// existen para esto: un solo generador sale por una IP y los alcanza pronto. Un 429
// se cuenta aparte (`limitadas`), no como error. Para medir la capacidad sin
// ellos, el entorno de la prueba los sube (docs/prueba-de-carga.md).
import crypto from 'k6/crypto';
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://veeduria-e2e.govtrace.localhost:8080';
const EMAIL = __ENV.VEEDOR_EMAIL || 'e2e.veedor@correo.co';
const PASSWORD = __ENV.VEEDOR_PASSWORD || 'Veeduria#2026';
const CONTRACT = __ENV.CONTRACT_ID || 'CO1.PCCNTR.9990001';
const PUBLIC_RATE = Number(__ENV.PUBLIC_RATE || 5); // peticiones por segundo
const VEEDORES = Number(__ENV.VEEDORES || 3);
const DURATION = __ENV.DURATION || '2m';

const photo = open('../fixtures/evidence/rostros-lejanos.jpg', 'b');
const photoHash = crypto.sha256(photo, 'hex');

const limitadas = new Counter('limitadas');
const reportes = new Counter('reportes_recibidos');
const envioDeReporte = new Trend('envio_de_reporte', true);

export const options = {
    scenarios: {
        publico: { executor: 'constant-arrival-rate', rate: PUBLIC_RATE, timeUnit: '1s', duration: DURATION, preAllocatedVUs: 20, maxVUs: 100, exec: 'publico' },
        veedor: { executor: 'constant-vus', vus: VEEDORES, duration: DURATION, exec: 'veedor' },
    },
    thresholds: {
        // Lo que importa: que lo que la app acepta no falle ni se arrastre.
        'http_req_failed{expected_response:true}': ['rate<0.01'],
        'http_req_duration{scenario:publico}': ['p(95)<1500'],
        'http_req_duration{name:reporte}': ['p(95)<5000'],
        checks: ['rate>0.99'],
    },
};

// Un 429 es la app defendiéndose: se cuenta, no es una falla.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 429));

function watch(response) {
    if (response.status === 429) limitadas.add(1);
    return response;
}

export function publico() {
    const route = ['/public/worksites', '/public/worksites/list', '/public/stats', '/public/worksites/filters'][Math.floor(Math.random() * 4)];
    const response = watch(http.get(`${BASE}${route}`, { headers: { Accept: 'application/json' }, tags: { name: route } }));
    check(response, { 'público: 200 o limitada': (r) => r.status === 200 || r.status === 429 });
}

function xsrf() {
    const jar = http.cookieJar().cookiesForURL(BASE);
    return jar['XSRF-TOKEN'] ? decodeURIComponent(jar['XSRF-TOKEN'][0]) : '';
}

function logIn() {
    http.get(`${BASE}/login`);
    const response = http.post(`${BASE}/login`, JSON.stringify({ email: EMAIL, password: PASSWORD }), {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
        tags: { name: 'login' },
    });
    return check(response, { 'veedor: entró': (r) => r.status < 400 });
}

export function veedor() {
    if (!logIn()) {
        sleep(5);
        return;
    }
    group('un reporte', () => {
        const headers = { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() };
        const browse = watch(http.post(`${BASE}/contracts/browse`, JSON.stringify({ latitude: 11.2419, longitude: -74.199, accuracy: 12 }), {
            headers: { ...headers, 'Content-Type': 'application/json' }, tags: { name: 'browse' },
        }));
        check(browse, { 'veedor: lista de obras': (r) => r.status === 200 || r.status === 429 });

        const sent = watch(http.post(`${BASE}/reports`, {
            secop_contract_id: CONTRACT,
            classification: 'Avance',
            comment: `Carga ${__VU}-${__ITER}`,
            latitude: '11.2419',
            longitude: '-74.1990',
            accuracy_meters: '12',
            captured_at: new Date().toISOString(),
            'files[]': http.file(photo, 'foto.jpg', 'image/jpeg'),
            'hashes[]': photoHash,
        }, { headers, tags: { name: 'reporte' } }));
        envioDeReporte.add(sent.timings.duration);
        if (sent.status === 201 || sent.status === 200) reportes.add(1);
        check(sent, { 'veedor: reporte recibido o limitado': (r) => r.status === 201 || r.status === 200 || r.status === 429 });
    });
    sleep(5 + Math.random() * 5);
}
