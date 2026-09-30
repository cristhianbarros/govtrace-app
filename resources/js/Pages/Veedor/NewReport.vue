<script setup>
// US-008: "Nuevo Reporte", la pantalla central del veedor, en el teléfono y
// en la obra. Buscar la obra → GPS (con reintento hasta tener buena señal)
// → clasificación y comentario → adjuntos → enviar. El servidor vuelve a
// validar todo (geocerca, hashes…); si rechaza, se muestra su motivo y el
// reporte queda para intentar de nuevo.
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import { cannotUpload } from '@/lib/evidence/attachments.js';
import { capturePosition, formatMeters, imprecisionMessage, isPreciseEnough } from '@/lib/geolocation.js';
import { saveOffline, startOutboxSync } from '@/composables/useOutbox.js';
import { registerServiceWorker } from '@/lib/pwa.js';
import { MESSAGES, OutboxFull } from '@/lib/outbox.js';
import { fetchNearbyWorksites, sendReport } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';

const page = usePage();

const CLASSIFICATIONS = ['Avance', 'Retraso', 'Abandono'];
// It. 40c: qué significa cada una, en una línea. Provisional: la valida una veeduría,
// porque cambia el color del mapa (US-027) — docs/ux-analisis.md, decisión 4.
const MEANING = {
    Avance: 'La obra avanza: hay trabajo o cambios desde la última vez.',
    Retraso: 'Va más lenta de lo previsto, o está detenida por ahora.',
    Abandono: 'No hay nadie trabajando y la obra parece dejada.',
};
const MAX_COMMENT_LENGTH = 500;
const SUCCESS_MESSAGE = 'Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar.';

const contract = ref(null);
const gps = ref({ status: 'idle' }); // idle | locating | ready | imprecise | failed
const classification = ref('');
const comment = ref('');
const evidences = ref([]);
const preparingFiles = ref(false);
const sending = ref(false);
const serverErrors = ref([]);
const sent = ref(false);
const savedOffline = ref(false); // US-018 e it. 41: por qué quedó en la bandeja de salida (el mensaje), o false

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

async function chooseWorksite(selected) {
    nearby.value = { status: 'idle', list: [] };
    contract.value = selected;
    sent.value = false;
    savedOffline.value = false;
    await locate();
}

async function locate() {
    gps.value = { status: 'locating' };
    try {
        const position = await capturePosition();
        gps.value = { status: isPreciseEnough(position.accuracy) ? 'ready' : 'imprecise', position };
    } catch (error) {
        gps.value = { status: 'failed', message: error.message };
    }
}

const commentTooLong = computed(() => comment.value.length > MAX_COMMENT_LENGTH);

// It. 40b: lo que le falta al reporte, en palabras, junto al botón. Un botón gris
// sin explicación deja a la persona sin saber qué hacer.
const missing = computed(() => {
    const items = [];
    if (gps.value.status !== 'ready') {
        items.push('esperar la ubicación del GPS');
    }
    if (classification.value === '') {
        items.push('decir qué vio en la obra');
    }
    if (preparingFiles.value) {
        items.push('esperar a que las fotos terminen de prepararse');
    } else if (cannotUpload(evidences.value) !== null) {
        items.push('adjuntar al menos una foto o un PDF');
    }
    if (commentTooLong.value) {
        items.push(`acortar el comentario a ${MAX_COMMENT_LENGTH} caracteres`);
    }
    return items;
});

const canSend = computed(
    () =>
        gps.value.status === 'ready' &&
        classification.value !== '' &&
        !commentTooLong.value &&
        cannotUpload(evidences.value) === null &&
        !preparingFiles.value &&
        !sending.value,
);

async function submit() {
    if (!canSend.value) {
        return;
    }
    sending.value = true;
    serverErrors.value = [];

    const { position } = gps.value;
    const form = new FormData();
    form.append('secop_contract_id', contract.value.secop_contract_id);
    form.append('classification', classification.value);
    form.append('comment', comment.value);
    form.append('latitude', String(position.latitude));
    form.append('longitude', String(position.longitude));
    form.append('accuracy_meters', String(position.accuracy));
    form.append('captured_at', position.capturedAt);
    for (const evidence of evidences.value) {
        form.append('files[]', evidence.file);
        form.append('hashes[]', evidence.sha256);
    }

    try {
        // US-018: sin señal, ni se intenta: va a la bandeja de salida.
        if (navigator.onLine === false) {
            await keepOffline(position);
            return;
        }
        await sendReport(form);
        startOver();
        sent.value = true;
    } catch (error) {
        if (error?.response?.status === 429) {
            // It. 41: pasado el límite por hora, a la bandeja de salida: se envía sola después.
            await keepOffline(position, MESSAGES.rateLimited);
        } else if (error?.response) {
            serverErrors.value = errorMessages(error);
        } else {
            await keepOffline(position);
        }
    } finally {
        sending.value = false;
    }
}

/** US-018: guardado en el teléfono tal como se capturó — el lugar y la hora quedan congelados. */
async function keepOffline(position, message = MESSAGES.saved) {
    try {
        await saveOffline({
            fields: {
                secop_contract_id: contract.value.secop_contract_id,
                classification: classification.value,
                comment: comment.value,
                latitude: String(position.latitude),
                longitude: String(position.longitude),
                accuracy_meters: String(position.accuracy),
                captured_at: position.capturedAt,
            },
            hashes: evidences.value.map((evidence) => evidence.sha256),
            files: evidences.value.map((evidence) => evidence.file),
        });
        startOver();
        savedOffline.value = message;
    } catch (error) {
        serverErrors.value = [error instanceof OutboxFull ? error.message : MESSAGES.full];
    }
}

// US-018: al abrir "Nuevo Reporte", lo que quedó pendiente se intenta subir.
onMounted(() => {
    registerServiceWorker();
    startOutboxSync();
});

function startOver() {
    contract.value = null;
    gps.value = { status: 'idle' };
    classification.value = '';
    comment.value = '';
    evidences.value = [];
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
            <p v-if="savedOffline" role="status" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">{{ savedOffline }}</p>

            <!-- 1. La obra: una cercana (US-019) o buscada (US-016) -->
            <section v-if="!contract" class="flex flex-col gap-2">
                <button type="button" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base font-semibold" :disabled="nearby.status === 'locating'" @click="findNearby">📍 Obras cercanas</button>
                <p v-if="nearby.status === 'locating'" class="text-sm text-slate-600">Buscando obras cercanas…</p>
                <p v-else-if="nearby.status === 'failed'" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ nearby.message }}</p>
                <p v-else-if="nearby.status === 'ready' && nearby.list.length === 0" class="rounded-lg bg-white p-3 text-sm text-slate-700">{{ NO_NEARBY }}</p>
                <ul v-else-if="nearby.status === 'ready'" class="flex flex-col gap-2">
                    <li v-for="item in nearby.list" :key="item.worksite_id">
                        <button type="button" data-test="nearby" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left active:bg-slate-100" @click="chooseWorksite(item.contract)">
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
            <section v-else class="flex items-start justify-between gap-3 rounded-lg bg-white p-3">
                <div>
                    <p class="font-semibold">{{ contract.object }}</p>
                    <p class="text-sm text-slate-600">{{ contract.entity_name }}</p>
                </div>
                <button type="button" class="shrink-0 text-sm font-semibold text-slate-700 underline" @click="startOver">Cambiar obra</button>
            </section>

            <template v-if="contract">
                <!-- 2. El GPS -->
                <section class="flex flex-col gap-2">
                    <p v-if="gps.status === 'locating'" class="text-sm text-slate-600">Obteniendo su ubicación…</p>
                    <p v-else-if="gps.status === 'ready'" class="text-sm text-emerald-800">
                        Precisión del GPS: {{ formatMeters(gps.position.accuracy) }} m
                    </p>
                    <template v-else-if="gps.status === 'imprecise' || gps.status === 'failed'">
                        <p role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            {{ gps.status === 'failed' ? gps.message : imprecisionMessage(gps.position.accuracy) }}
                        </p>
                        <button
                            type="button"
                            data-test="retry-gps"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-3 text-base font-semibold"
                            @click="locate"
                        >
                            Reintentar GPS
                        </button>
                    </template>
                </section>

                <!-- 3 y 4. Clasificación, comentario y adjuntos -->
                <form v-if="gps.status !== 'failed'" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-2 text-base font-semibold">¿Qué vio en la obra?</legend>
                        <label
                            v-for="option in CLASSIFICATIONS"
                            :key="option"
                            class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-base"
                        >
                            <input v-model="classification" type="radio" name="classification" :value="option" class="size-5" />
                            <span>
                                <span class="block font-semibold">{{ option }}</span>
                                <span class="block text-sm text-slate-700">{{ MEANING[option] }}</span>
                            </span>
                        </label>
                    </fieldset>

                    <div class="flex flex-col gap-1">
                        <label for="comment" class="text-sm font-semibold text-slate-700">Comentario (opcional)</label>
                        <textarea
                            id="comment"
                            v-model="comment"
                            rows="3"
                            :maxlength="MAX_COMMENT_LENGTH"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base"
                        ></textarea>
                        <p class="text-right text-xs" :class="commentTooLong ? 'text-red-700' : 'text-slate-500'">
                            {{ comment.length }}/{{ MAX_COMMENT_LENGTH }}
                        </p>
                        <p v-if="commentTooLong" role="alert" class="text-sm text-red-700">
                            El comentario admite máximo {{ MAX_COMMENT_LENGTH }} caracteres.
                        </p>
                    </div>

                    <EvidencePicker v-model="evidences" v-model:processing="preparingFiles" />

                    <ul v-if="serverErrors.length" role="alert" class="flex flex-col gap-1 rounded-lg bg-red-50 p-3 text-sm text-red-800">
                        <li v-for="message in serverErrors" :key="message">{{ message }}</li>
                    </ul>

                    <div v-if="missing.length && !sending" id="send-missing" data-test="missing" class="rounded-lg bg-slate-100 p-3 text-base text-slate-800">
                        <p class="font-semibold">Para enviar falta:</p>
                        <ul class="mt-1 list-disc pl-5">
                            <li v-for="item in missing" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                    <button
                        type="submit"
                        :disabled="!canSend"
                        :aria-describedby="missing.length ? 'send-missing' : undefined"
                        class="rounded-lg bg-slate-900 px-3 py-4 text-base font-semibold text-white disabled:opacity-40"
                    >
                        {{ sending ? 'Enviando…' : 'Enviar Reporte' }}
                    </button>
                </form>
            </template>
        </div>
        <template #nav>
            <VeedorNav current="/reports/new" />
        </template>
    </AppLayout>
</template>
