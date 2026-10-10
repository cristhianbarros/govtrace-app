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
import ReportForm from '@/Components/ReportForm.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import WorksiteBrowser from '@/Components/WorksiteBrowser.vue';
import { useGps } from '@/composables/useGps.js';
import { saveOffline, startOutboxSync } from '@/composables/useOutbox.js';
import { registerServiceWorker } from '@/lib/pwa.js';
import { loadDetector } from '@/lib/evidence/faces.js';
import { MESSAGES, OutboxFull } from '@/lib/outbox.js';
import { reportFormData } from '@/lib/report.js';
import { sendReport } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';

const page = usePage();

const SUCCESS_MESSAGE = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = ref(null);
const sending = ref(false);
const serverErrors = ref([]);
const sent = ref(false);
const savedOffline = ref(false); // US-018 e it. 41: por qué quedó en la bandeja de salida (el mensaje), o false
const locationPending = ref(null); // it. 46f: por qué la ubicación de la obra quedó por confirmar

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

function chooseWorksite(selected) {
    contract.value = selected;
    sent.value = false;
    savedOffline.value = false;
    locationPending.value = null;
}

async function submit(report) {
    sending.value = true;
    serverErrors.value = [];

    try {
        // US-018: sin señal, ni se intenta: va a la bandeja de salida.
        if (navigator.onLine === false) {
            await keepOffline(report);
            return;
        }
        const answer = await sendReport(reportFormData(report));
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
    serverErrors.value = [];
}
</script>

<template>
    <Head title="Nuevo Reporte" />
    <!-- El nombre y el logo que la organización eligió (US-007). -->
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <div class="flex flex-col gap-5">
            <h1 class="text-xl font-semibold">Nuevo Reporte</h1>

            <p v-if="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ SUCCESS_MESSAGE }}</p>
            <p v-if="locationPending" data-test="location-pending" role="status" class="rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-200">{{ locationPending }}</p>
            <p v-if="savedOffline" role="status" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">{{ savedOffline }}</p>

            <!-- 1. La obra: las de su municipio, con filtros, y las cercanas encima (it. 47a) -->
            <!-- It. 47b: un minuto sin una lectura de 50 m o menos -->
            <div v-if="gps.status.value === 'slow'" data-test="slow-gps" class="flex flex-col gap-2">
                <p role="alert" class="rounded-lg bg-amber-50 p-3 text-base text-amber-900 ring-1 ring-amber-200">{{ gps.message.value }}</p>
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800" @click="gps.start()">Intentar de nuevo</button>
            </div>

            <WorksiteBrowser v-if="!contract" :location="location" @select="chooseWorksite" />
            <ReportForm v-else :contract="contract" :gps="gps" :sending="sending" :server-errors="serverErrors" @submit="submit" @change-worksite="startOver" />
        </div>
        <template #nav>
            <VeedorNav current="/reports/new" />
        </template>
    </AppLayout>
</template>
