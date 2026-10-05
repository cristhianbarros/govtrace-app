<script setup>
// Una evidencia en la bandeja (US-036, US-037): lo que el Administrador
// revisa — las fotos o el PDF tal como se sellaron, la clasificación, el
// comentario, la marca de hora sospechosa y el sello — y sus decisiones,
// de a una. Rechazar y retirar piden motivo.
// It. 45f: dónde se tomó, sin las coordenadas del veedor. La que fijó la
// ubicación oficial de la obra (First-Touch) llega marcada, con ese punto y
// el enlace para corregirlo en Obras (US-035).
// It. 46f: la del primer reporte que no fijó la ubicación (lejos de su
// municipio, o con mala señal) llega por confirmar: el motivo, el punto, y
// confirmarla ahí mismo o corregirla en Obras.
import { computed, ref } from 'vue';
import LocationMap from '@/Components/LocationMap.vue';
import { formatDateTime } from '@/lib/format.js';
import { correctWorksiteLocation, decideOnEvidence } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

// It. 40e: el mismo color de cada clasificación que en el sitio público.
const CLASSIFICATION = {
    Avance: 'bg-green-100 text-green-800',
    Retraso: 'bg-yellow-100 text-yellow-900',
    Abandono: 'bg-red-100 text-red-800',
};

const props = defineProps({
    evidence: { type: Object, required: true },
});

const emit = defineEmits(['decided']);

const TOMBSTONE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';
const ANCHORED = '📍 Este reporte fijó la ubicación oficial de la obra.';

const location = computed(() => props.evidence.location ?? {});
const anchored = computed(() => location.value.anchored_worksite === true && location.value.point !== null);
const pending = computed(() => (location.value.pending && location.value.point !== null ? location.value.pending : null));
const distance = computed(() =>
    !anchored.value && Number.isFinite(location.value.distance_meters) ? new Intl.NumberFormat('es-CO').format(location.value.distance_meters) : null,
);
// It. 46e (R-PRIV-05): lo que se difuminó en el celular, foto por foto; null si se envió antes.
const blurred = computed(() => props.evidence.files.map((file) => file.blurring).filter(Boolean));
const blurredZones = computed(() => blurred.value.reduce((total, blurring) => total + blurring.faces + blurring.manual, 0));
const dismissedBlurs = computed(() => blurred.value.reduce((total, blurring) => total + blurring.dismissed, 0));
const zonesLabel = computed(() => (blurredZones.value === 0 ? 'Sin zonas difuminadas en el celular' : `${blurredZones.value} ${blurredZones.value === 1 ? 'zona difuminada' : 'zonas difuminadas'} en el celular`));

const correctHref = computed(() => `/admin/worksites?corregir=${props.evidence.worksite_id}`);

// It. 46f: "Confirmar esta ubicación" fija la de la obra en el punto de este reporte (una corrección de US-035).
const confirmation = ref({ status: 'idle' }); // idle | sending | done | failed
const pendingStatus = computed(() => (confirmation.value.status === 'done' ? 'confirmed' : pending.value?.status));

async function confirmLocation() {
    confirmation.value = { status: 'sending' };
    try {
        const answer = await correctWorksiteLocation(props.evidence.worksite_id, location.value.point);
        confirmation.value = { status: 'done', message: answer?.message };
    } catch (failure) {
        confirmation.value = { status: 'failed', message: errorMessage(failure) };
    }
}

const withReason = {
    reject: { confirm: 'Confirmar rechazo', label: 'Motivo del rechazo (lo verá el veedor)', done: 'Evidencia rechazada. Su veedor verá el motivo.' },
    withdraw: { confirm: 'Confirmar retiro', label: 'Motivo del retiro (queda en el log de auditoría)', done: 'Evidencia retirada. En el mapa queda su lápida.' },
};

const asking = ref(null); // "reject" | "withdraw" mientras se escribe el motivo
const reason = ref('');
const busy = ref(false);
const error = ref(null);

const reasonGiven = computed(() => reason.value.trim() !== '');

function ask(decision) {
    asking.value = decision;
    reason.value = '';
    error.value = null;
}

async function decide(decision) {
    busy.value = true;
    error.value = null;
    try {
        const answer = await decideOnEvidence(props.evidence.id, decision, decision === 'publish' ? undefined : reason.value.trim());
        emit('decided', props.evidence.id, answer?.message ?? withReason[decision].done);
    } catch (failure) {
        error.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <article class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm">
        <!-- It. 40b (V4): de qué obra es y quién la envió, para decidir con contexto. -->
        <div v-if="evidence.worksite">
            <p data-test="worksite" class="text-lg font-semibold">{{ evidence.worksite.name }}</p>
            <p class="text-base text-slate-700">
                <span v-if="evidence.worksite.municipality">{{ evidence.worksite.municipality }}</span>
                <span v-if="evidence.worksite.municipality && evidence.observer"> · </span>
                <span v-if="evidence.observer">Enviada por {{ evidence.observer }}</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded px-2 py-0.5 font-semibold" :class="CLASSIFICATION[evidence.classification]">{{ evidence.classification }}</span>
            <span class="text-slate-600">{{ formatDateTime(evidence.captured_at) }}</span>
            <span v-if="evidence.suspicious_capture_time" class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-900">
                Hora de captura sospechosa
            </span>
        </div>

        <p v-if="blurred.length" data-test="blurring" class="text-base text-slate-700">{{ zonesLabel }}</p>
        <p v-if="dismissedBlurs > 0" data-test="dismissed-blur" class="rounded-lg bg-amber-50 p-2 text-base font-semibold text-amber-900">
            ⚠ Quien la envió quitó {{ dismissedBlurs }} {{ dismissedBlurs === 1 ? 'difuminado' : 'difuminados' }} que el detector propuso: revise que no se vea un rostro.
        </p>
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
            <template v-for="file in evidence.files" :key="file.id">
                <img
                    v-if="file.kind === 'photo'"
                    :src="`/evidences/${file.id}/file`"
                    alt="Foto de la evidencia"
                    loading="lazy"
                    class="aspect-square w-full rounded object-cover"
                />
                <a v-else :href="`/evidences/${file.id}/file`" target="_blank" rel="noopener" class="rounded border p-3 text-sm font-semibold">
                    📄 Ver PDF
                </a>
            </template>
        </div>

        <p v-if="distance !== null" data-test="distance" class="text-base text-slate-700">Tomada a {{ distance }} m de la obra.</p>
        <div v-if="anchored" data-test="anchored" class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 ring-1 ring-amber-200">
            <p class="text-base font-semibold text-amber-900">{{ ANCHORED }}{{ location.corrected ? ' Después se corrigió.' : '' }}</p>
            <LocationMap :model-value="location.point" readonly />
            <a
                data-test="correct-location"
                :href="correctHref"
                class="inline-flex min-h-11 items-center self-start rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50"
            >Corregir ubicación</a>
        </div>

        <div v-if="pending" data-test="location-pending" class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 ring-1 ring-amber-200">
            <p class="text-base font-semibold text-amber-900">📍 Ubicación por confirmar: es el primer reporte de la obra, pero {{ pending.reason }}. La obra sigue sin ubicación oficial.</p>
            <LocationMap :model-value="location.point" readonly />
            <p v-if="confirmation.status === 'done' && confirmation.message" role="status" class="text-base text-emerald-800">{{ confirmation.message }}</p>
            <p v-if="confirmation.status === 'failed'" role="alert" class="rounded bg-red-50 p-2 text-base text-red-800">{{ confirmation.message }}</p>
            <p v-if="pendingStatus === 'confirmed'" class="text-base font-semibold text-emerald-800">Ubicación confirmada aquí.</p>
            <p v-else-if="pendingStatus === 'elsewhere'" class="text-base font-semibold text-slate-800">La obra ya tiene ubicación oficial, en otro lugar.</p>
            <div v-else class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :disabled="confirmation.status === 'sending'"
                    class="inline-flex min-h-11 items-center rounded-xl bg-brand-700 px-3 font-semibold text-white hover:bg-brand-800 disabled:opacity-40"
                    @click="confirmLocation"
                >Confirmar esta ubicación</button>
                <a
                    data-test="correct-location"
                    :href="correctHref"
                    class="inline-flex min-h-11 items-center rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50"
                >Corregir ubicación</a>
            </div>
        </div>

        <p class="text-sm">{{ evidence.comment || 'Sin comentario.' }}</p>
        <p class="text-xs text-slate-600"><span aria-hidden="true">✓</span> Con sello digital · bloque {{ evidence.seal.ledger }}</p>

        <p v-if="error" role="alert" class="rounded bg-red-50 p-2 text-sm text-red-800">{{ error }}</p>

        <div v-if="asking" class="flex flex-col gap-2">
            <p v-if="asking === 'withdraw'" class="rounded bg-slate-100 p-2 text-sm">
                En el mapa público quedará una lápida: «{{ TOMBSTONE }}». El retiro es definitivo.
            </p>
            <div v-if="asking === 'reject' && anchored" data-test="anchored-reject-warning" class="flex flex-col gap-2 rounded bg-amber-50 p-3 text-base text-amber-900">
                <p>Este reporte fijó la ubicación oficial de la obra. Rechazarlo no la cambia: si el lugar está mal, corríjalo en Obras.</p>
                <a
                    :href="correctHref"
                    class="inline-flex min-h-11 items-center self-start rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50"
                >Corregir ubicación</a>
            </div>
            <label :for="`reason-${evidence.id}`" class="text-sm font-semibold text-slate-700">{{ withReason[asking].label }}</label>
            <textarea :id="`reason-${evidence.id}`" v-model="reason" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
            <div class="flex gap-2">
                <button
                    type="button"
                    :disabled="!reasonGiven || busy"
                    class="flex-1 rounded-lg bg-red-700 px-3 py-3 font-semibold text-white disabled:opacity-40"
                    @click="decide(asking)"
                >{{ withReason[asking].confirm }}</button>
                <button type="button" class="rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 py-3 font-semibold" @click="asking = null">Cancelar</button>
            </div>
        </div>

        <div v-else class="flex gap-2">
            <button
                v-if="evidence.actions.includes('publish')"
                type="button"
                :disabled="busy"
                class="flex-1 rounded-xl bg-brand-700 px-3 py-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800"
                @click="decide('publish')"
            >Publicar</button>
            <button v-if="evidence.actions.includes('reject')" type="button" class="flex-1 rounded-xl border-2 border-red-700 px-3 py-3 font-semibold text-red-800 hover:bg-red-50" @click="ask('reject')">Rechazar</button>
            <button v-if="evidence.actions.includes('withdraw')" type="button" class="flex-1 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 py-3 font-semibold" @click="ask('withdraw')">Retirar</button>
        </div>
    </article>
</template>
