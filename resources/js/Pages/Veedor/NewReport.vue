<script setup>
// US-008: "Nuevo Reporte", la pantalla central del veedor, en el teléfono y
// en la obra. Buscar la obra → GPS (con reintento hasta tener buena señal)
// → clasificación y comentario → adjuntos → enviar. El servidor vuelve a
// validar todo (geocerca, hashes…); si rechaza, se muestra su motivo y el
// reporte queda para intentar de nuevo. El formulario, desde la it. 43g, es
// ReportForm; aquí, buscar la obra y qué pasa sin señal (US-018).
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import ReportForm from '@/Components/ReportForm.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import { capturePosition } from '@/lib/geolocation.js';
import { saveOffline, startOutboxSync } from '@/composables/useOutbox.js';
import { registerServiceWorker } from '@/lib/pwa.js';
import { loadDetector } from '@/lib/evidence/faces.js';
import { MESSAGES, OutboxFull } from '@/lib/outbox.js';
import { reportFormData } from '@/lib/report.js';
import { fetchNearbyWorksites, sendReport } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';

const page = usePage();

const SUCCESS_MESSAGE = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = ref(null);
const sending = ref(false);
const serverErrors = ref([]);
const sent = ref(false);
const savedOffline = ref(false); // US-018 e it. 41: por qué quedó en la bandeja de salida (el mensaje), o false
const locationPending = ref(null); // it. 46f: por qué la ubicación de la obra quedó por confirmar

// US-019: las obras cercanas, desde donde está el veedor.
const NO_NEARBY = '📍 No se encontraron obras a menos de 500m. Utilice el buscador para encontrarla por nombre o contrato.';
const nearby = ref({ status: 'idle', list: [] }); // idle | locating | ready | failed

async function findNearby() {
    nearby.value = { status: 'locating', list: [] };
    try {
        const position = await capturePosition();
        nearby.value = { status: 'ready', list: await fetchNearbyWorksites(position.latitude, position.longitude) };
    } catch (error) {
        nearby.value = { status: 'failed', list: [], message: error?.response ? errorMessages(error)[0] : error.message };
    }
}

function chooseWorksite(selected) {
    nearby.value = { status: 'idle', list: [] };
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

            <!-- 1. La obra: una cercana (US-019) o buscada (US-016) -->
            <section v-if="!contract" class="flex flex-col gap-2">
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 bg-white px-3 text-base font-semibold" :disabled="nearby.status === 'locating'" @click="findNearby">📍 Obras cercanas</button>
                <p v-if="nearby.status === 'locating'" class="text-sm text-slate-600">Buscando obras cercanas…</p>
                <p v-else-if="nearby.status === 'failed'" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ nearby.message }}</p>
                <p v-else-if="nearby.status === 'ready' && nearby.list.length === 0" class="rounded-2xl bg-white p-3 text-sm text-slate-700 shadow-soft ring-1 ring-slate-900/5">{{ NO_NEARBY }}</p>
                <ul v-else-if="nearby.status === 'ready'" class="flex flex-col gap-2">
                    <li v-for="item in nearby.list" :key="item.worksite_id">
                        <button type="button" data-test="nearby" class="flex w-full items-center justify-between gap-3 rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-3 text-left active:bg-slate-100" @click="chooseWorksite(item.contract)">
                            <span>
                                <span class="block font-semibold">{{ item.name }}</span>
                                <span class="block text-sm text-slate-600">{{ item.contract.entity_name }}</span>
                            </span>
                            <span data-test="distance" class="shrink-0 text-sm font-semibold text-slate-700">a {{ item.distance_meters }} m</span>
                        </button>
                    </li>
                </ul>
            </section>
            <ContractSearch v-if="!contract" @select="chooseWorksite" />
            <ReportForm v-else :contract="contract" :sending="sending" :server-errors="serverErrors" @submit="submit" @change-worksite="startOver" />
        </div>
        <template #nav>
            <VeedorNav current="/reports/new" />
        </template>
    </AppLayout>
</template>
