<script setup>
// US-008: "Nuevo Reporte", la pantalla central del veedor, en el teléfono y
// en la obra. Elegir la obra → GPS (con reintento hasta tener buena señal)
// → clasificación y comentario → adjuntos → enviar. El servidor vuelve a
// validar todo (geocerca, hashes…); si rechaza, se muestra su motivo y el
// reporte queda para intentar de nuevo. El formulario, desde la it. 43g, es
// ReportForm; la obra, desde la it. 47a, WorksiteBrowser (las de su municipio,
// con filtros, y las cercanas encima); aquí, el GPS al abrir y qué pasa sin
// señal (US-018).
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import LocationMap from '@/Components/LocationMap.vue';
import RowAction from '@/Components/RowAction.vue';
import ReportForm from '@/Components/ReportForm.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import WorksiteBrowser from '@/Components/WorksiteBrowser.vue';
import { useGps } from '@/composables/useGps.js';
import { useLeaveGuard } from '@/composables/useLeaveGuard.js';
import { saveOffline, startOutboxSync } from '@/composables/useOutbox.js';
import { registerServiceWorker } from '@/lib/pwa.js';
import { loadDetector } from '@/lib/evidence/faces.js';
import { MESSAGES, OutboxFull } from '@/lib/outbox.js';
import { reportFormData } from '@/lib/report.js';
import { sendReport, unableToVisit } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';
import { vReveal } from '@/lib/reveal.js';

const page = usePage();

const SUCCESS_MESSAGE = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = ref(null);
const sending = ref(false);
const serverErrors = ref([]);
const sent = ref(false);
const savedOffline = ref(false); // US-018 e it. 41: por qué quedó en la bandeja de salida (el mensaje), o false
const locationPending = ref(null); // it. 46f: por qué la ubicación de la obra quedó por confirmar

// It. 62e (US-067): con la obra elegida, el reporte está empezado: salir de la pantalla pregunta antes.
useLeaveGuard(computed(() => contract.value !== null));

// It. 47b: el GPS, una sola vez por pantalla (useGps): la lista (el municipio y
// las obras cercanas) y el reporte usan la misma lectura. Para la lista:
// undefined mientras el GPS responde; null si no hay ubicación (no dio permiso,
// o un minuto sin señal): abre igual, en el primer municipio del territorio.
const gps = useGps();
const location = computed(() => {
    const reading = gps.latest.value;
    if (reading) {
        return { latitude: reading.latitude, longitude: reading.longitude, accuracy: reading.accuracy };
    }
    return ['failed', 'slow'].includes(gps.status.value) ? null : undefined;
});

// It. 65b (US-068): las visitas que la veeduría le pidió, por un informe ciudadano. Reportar la obra la documenta.
const visits = ref([...(page.props.visits ?? [])]);
const visiting = ref(null); // la visita desde la que se abrió el reporte
const visitNotice = ref(null);

function reportVisit(visit) {
    chooseWorksite(visit.contract);
    visiting.value = visit;
}

function leftVisit(visit, message) {
    visits.value = visits.value.filter((candidate) => candidate.id !== visit.id);
    visitNotice.value = message;
}

function chooseWorksite(selected) {
    visiting.value = null;
    visitNotice.value = null;
    contract.value = selected;
    sent.value = false;
    savedOffline.value = false;
    locationPending.value = null;
}

// It. 65b: como en el servidor, un reporte de la obra documenta las visitas que el veedor tenía en ella.
function documentVisits(secopContractId) {
    visits.value = visits.value.filter((visit) => visit.contract?.secop_contract_id !== secopContractId);
}

async function submit(report) {
    sending.value = true;
    serverErrors.value = [];
    const reported = contract.value?.secop_contract_id;

    try {
        // US-018: sin señal, ni se intenta: va a la bandeja de salida.
        if (navigator.onLine === false) {
            await keepOffline(report);
            documentVisits(reported);
            return;
        }
        const answer = await sendReport(reportFormData(report));
        documentVisits(reported);
        startOver();
        sent.value = true;
        locationPending.value = answer?.location_pending ?? null;
    } catch (error) {
        if (error?.response?.status === 429) {
            // It. 41: pasado el límite por hora, a la bandeja de salida: se envía sola después.
            await keepOffline(report, MESSAGES.rateLimited);
        } else if ([502, 503, 504].includes(error?.response?.status)) {
            // It. 42c: GovTrace se está desplegando; tampoco se pierde.
            await keepOffline(report, MESSAGES.updating);
        } else if (error?.response) {
            serverErrors.value = errorMessages(error);
        } else {
            await keepOffline(report);
        }
    } finally {
        sending.value = false;
    }
}

/** US-018: guardado en el teléfono tal como se capturó — el lugar y la hora quedan congelados. */
async function keepOffline(report, message = MESSAGES.saved) {
    try {
        await saveOffline(report);
        startOver();
        savedOffline.value = message;
    } catch (error) {
        serverErrors.value = [error instanceof OutboxFull ? error.message : MESSAGES.full];
    }
}

// US-018: al abrir "Nuevo Reporte", lo que quedó pendiente se intenta subir.
// It. 46e: con señal, el detector de rostros se descarga de una vez: el Service
// Worker lo guarda, y la primera foto sin señal también se revisa. Si falla, la
// foto se revisará a mano.
onMounted(() => {
    registerServiceWorker();
    startOutboxSync();
    gps.start();
    if (navigator.onLine !== false) {
        loadDetector().catch(() => {});
    }
});

function startOver() {
    contract.value = null;
    visiting.value = null;
    serverErrors.value = [];
}
</script>

<template>
    <Head title="Nuevo Reporte" />
    <!-- El nombre y el logo que la organización eligió (US-007). -->
    <!-- It. 62e (US-067): la ruta también aquí; salir con un reporte empezado pregunta antes (useLeaveGuard). -->
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo" here="Nuevo Reporte">
        <div class="flex flex-col gap-5">
            <h1 class="text-xl font-semibold">Nuevo Reporte</h1>

            <!-- It. 62d: el resultado sale aquí arriba, y "Enviar Reporte" está abajo: se trae a la vista. -->
            <p v-if="sent" v-reveal="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ SUCCESS_MESSAGE }}</p>
            <p v-if="locationPending" v-reveal="locationPending" data-test="location-pending" role="status" class="rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-200">{{ locationPending }}</p>
            <p v-if="savedOffline" v-reveal="savedOffline" role="status" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">{{ savedOffline }}</p>

            <!-- 1. La obra: las de su municipio, con filtros, y las cercanas encima (it. 47a) -->
            <!-- It. 47b: un minuto sin una lectura de 50 m o menos -->
            <div v-if="gps.status.value === 'slow'" data-test="slow-gps" class="flex flex-col gap-2">
                <p role="alert" class="rounded-lg bg-amber-50 p-3 text-base text-amber-900 ring-1 ring-amber-200">{{ gps.message.value }}</p>
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800" @click="gps.start()">Intentar de nuevo</button>
            </div>

            <!-- It. 65b (US-068): las visitas que le pidió la veeduría, encima de las obras. -->
            <p v-if="visitNotice" v-reveal="visitNotice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ visitNotice }}</p>
            <section v-if="!contract && visits.length" aria-labelledby="visits-title" class="flex flex-col gap-3">
                <h2 id="visits-title" class="text-lg font-semibold">Visitas pendientes</h2>
                <p class="text-base text-slate-700">La veeduría le pidió documentar lo que informó un ciudadano. Vaya a la obra y repórtela como siempre: su reporte responde al informe.</p>
                <article v-for="visit in visits" :key="visit.id" data-test="visit" class="flex flex-col gap-2 rounded-2xl border-l-8 border-sky-500 bg-white p-3 text-base shadow-soft ring-1 ring-slate-900/5">
                    <p class="font-semibold">{{ visit.worksite }}</p>
                    <p class="text-sm text-slate-600">Informe ciudadano n.º {{ visit.reference }}</p>
                    <p class="whitespace-pre-line">«{{ visit.message }}»</p>
                    <div v-if="visit.photo_urls.length" class="flex flex-wrap gap-2">
                        <a v-for="(url, index) in visit.photo_urls" :key="url" :href="url" target="_blank" rel="noopener">
                            <img :src="url" :alt="`Foto ${index + 1} de ${visit.photo_urls.length} que envió el ciudadano`" class="size-24 rounded-lg object-cover" />
                        </a>
                    </div>
                    <template v-if="visit.proposed_location">
                        <p class="font-semibold text-amber-900">📍 El ciudadano dice que la obra queda aquí. Es una pista: la obra aún no tiene ubicación oficial.</p>
                        <LocationMap :model-value="visit.proposed_location" readonly label="Donde el ciudadano dice que queda la obra" />
                    </template>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="visit.contract" type="button" data-test="visit-report" class="min-h-12 rounded-xl bg-brand-700 px-4 font-semibold text-white hover:bg-brand-800" @click="reportVisit(visit)">Reportar esta obra</button>
                        <RowAction label="No puedo ir" confirm-label="Sí, no puedo ir" warning="La visita vuelve a la veeduría, para que se la pida a otro veedor." :run="() => unableToVisit(visit.id)" @done="(message) => leftVisit(visit, message)" />
                    </div>
                </article>
            </section>

            <WorksiteBrowser v-if="!contract" :location="location" @select="chooseWorksite" />
            <template v-else>
                <div v-if="visiting" data-test="visit-context" class="rounded-lg bg-sky-50 p-3 text-base ring-1 ring-sky-200">
                    <p class="font-semibold text-sky-900">Lo que informó el ciudadano (n.º {{ visiting.reference }})</p>
                    <p class="whitespace-pre-line">«{{ visiting.message }}»</p>
                </div>
                <ReportForm :contract="contract" :gps="gps" :sending="sending" :server-errors="serverErrors" @submit="submit" @change-worksite="startOver" />
            </template>
        </div>
        <template #nav>
            <VeedorNav current="/reports/new" />
        </template>
    </AppLayout>
</template>
