<script setup>
// El reporte de una obra ya elegida (US-008): el GPS (con reintento hasta tener
// buena señal) → qué vio y el comentario → los adjuntos → enviar. It. 43g: lo
// comparten "Nuevo Reporte" del veedor y la pantalla del Super Administrador
// que reporta en nombre de una organización (US-042-SEC). Quién lo envía, y
// qué hace sin señal, lo decide cada pantalla con lo que emite `submit`.
import { computed, onMounted, ref } from 'vue';
import EvidencePicker from '@/Components/EvidencePicker.vue';
import { cannotUpload } from '@/lib/evidence/attachments.js';
import { capturePosition, formatMeters, imprecisionMessage, isPreciseEnough } from '@/lib/geolocation.js';
import { CLASSIFICATIONS, MAX_COMMENT_LENGTH, MEANING } from '@/lib/report.js';

const props = defineProps({
    contract: { type: Object, required: true },
    sending: { type: Boolean, default: false },
    serverErrors: { type: Array, default: () => [] },
});

// submit: { fields, files, hashes } — el reporte tal como lo recibe el servidor.
const emit = defineEmits(['submit', 'change-worksite']);

const gps = ref({ status: 'idle' }); // idle | locating | ready | imprecise | failed
const classification = ref('');
const comment = ref('');
const evidences = ref([]);
const preparingFiles = ref(false);

async function locate() {
    gps.value = { status: 'locating' };
    try {
        const position = await capturePosition();
        gps.value = { status: isPreciseEnough(position.accuracy) ? 'ready' : 'imprecise', position };
    } catch (error) {
        gps.value = { status: 'failed', message: error.message };
    }
}

onMounted(locate);

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
        items.push('terminar de revisar las fotos'); // it. 46e: cada foto se revisa antes de adjuntarla
    } else if (cannotUpload(evidences.value) !== null) {
        items.push('adjuntar al menos una foto o un PDF');
    }
    if (commentTooLong.value) {
        items.push(`acortar el comentario a ${MAX_COMMENT_LENGTH} caracteres`);
    }
    return items;
});

const canSend = computed(() => missing.value.length === 0 && !props.sending);

function submit() {
    if (!canSend.value) {
        return;
    }
    const { position } = gps.value;
    emit('submit', {
        fields: {
            secop_contract_id: props.contract.secop_contract_id,
            classification: classification.value,
            comment: comment.value,
            latitude: String(position.latitude),
            longitude: String(position.longitude),
            accuracy_meters: String(position.accuracy),
            captured_at: position.capturedAt,
            // It. 46e: lo que se difuminó en cada foto (null en un PDF), para la Bandeja.
            blurs: JSON.stringify(evidences.value.map((evidence) => evidence.blurs ?? null)),
        },
        files: evidences.value.map((evidence) => evidence.file),
        hashes: evidences.value.map((evidence) => evidence.sha256),
    });
}
</script>

<template>
    <section class="flex items-start justify-between gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
        <div>
            <p class="font-semibold">{{ contract.object }}</p>
            <p class="text-sm text-slate-600">{{ contract.entity_name }}</p>
        </div>
        <button type="button" class="shrink-0 text-sm font-semibold text-slate-700 underline" @click="emit('change-worksite')">Cambiar obra</button>
    </section>

    <!-- El GPS -->
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
                class="rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 bg-white px-3 py-3 text-base font-semibold"
                @click="locate"
            >
                Reintentar GPS
            </button>
        </template>
    </section>

    <!-- Clasificación, comentario y adjuntos -->
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
            class="rounded-xl bg-brand-700 px-3 py-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800"
        >
            {{ sending ? 'Enviando…' : 'Enviar Reporte' }}
        </button>
    </form>
</template>
