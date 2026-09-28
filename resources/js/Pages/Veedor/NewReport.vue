<script setup>
// US-008: "Nuevo Reporte", la pantalla central del veedor, en el teléfono y
// en la obra. Buscar la obra → GPS (con reintento hasta tener buena señal)
// → clasificación y comentario → adjuntos → enviar. El servidor vuelve a
// validar todo (geocerca, hashes…); si rechaza, se muestra su motivo y el
// reporte queda para intentar de nuevo.
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import { cannotUpload } from '@/lib/evidence/attachments.js';
import { capturePosition, formatMeters, imprecisionMessage, isPreciseEnough } from '@/lib/geolocation.js';
import { sendReport } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';

const CLASSIFICATIONS = ['Avance', 'Retraso', 'Abandono'];
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

async function chooseWorksite(selected) {
    contract.value = selected;
    sent.value = false;
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
        await sendReport(form);
        startOver();
        sent.value = true;
    } catch (error) {
        serverErrors.value = errorMessages(error);
    } finally {
        sending.value = false;
    }
}

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
    <AppLayout title="Nuevo Reporte">
        <div class="flex flex-col gap-5">
            <p v-if="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ SUCCESS_MESSAGE }}</p>

            <!-- 1. La obra -->
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
                        <legend class="mb-2 text-sm font-semibold text-slate-700">Clasificación</legend>
                        <label
                            v-for="option in CLASSIFICATIONS"
                            :key="option"
                            class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-base"
                        >
                            <input v-model="classification" type="radio" name="classification" :value="option" class="size-5" />
                            {{ option }}
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

                    <button
                        type="submit"
                        :disabled="!canSend"
                        class="rounded-lg bg-slate-900 px-3 py-4 text-base font-semibold text-white disabled:opacity-40"
                    >
                        {{ sending ? 'Enviando…' : 'Enviar Reporte' }}
                    </button>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
