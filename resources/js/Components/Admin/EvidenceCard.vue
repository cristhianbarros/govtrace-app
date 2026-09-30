<script setup>
// Una evidencia en la bandeja (US-036, US-037): lo que el Administrador
// revisa — las fotos o el PDF tal como se sellaron, la clasificación, el
// comentario, la marca de hora sospechosa y el sello — y sus decisiones,
// de a una. Rechazar y retirar piden motivo.
import { computed, ref } from 'vue';
import { formatDateTime } from '@/lib/format.js';
import { decideOnEvidence } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    evidence: { type: Object, required: true },
});

const emit = defineEmits(['decided']);

const TOMBSTONE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';

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
            <span class="rounded bg-slate-900 px-2 py-0.5 font-semibold text-white">{{ evidence.classification }}</span>
            <span class="text-slate-600">{{ formatDateTime(evidence.captured_at) }}</span>
            <span v-if="evidence.suspicious_capture_time" class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-900">
                Hora de captura sospechosa
            </span>
        </div>

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

        <p class="text-sm">{{ evidence.comment || 'Sin comentario.' }}</p>
        <p class="text-xs text-slate-500">Sellada en el ledger {{ evidence.seal.ledger }}</p>

        <p v-if="error" role="alert" class="rounded bg-red-50 p-2 text-sm text-red-800">{{ error }}</p>

        <div v-if="asking" class="flex flex-col gap-2">
            <p v-if="asking === 'withdraw'" class="rounded bg-slate-100 p-2 text-sm">
                En el mapa público quedará una lápida: «{{ TOMBSTONE }}». El retiro es definitivo.
            </p>
            <label :for="`reason-${evidence.id}`" class="text-sm font-semibold text-slate-700">{{ withReason[asking].label }}</label>
            <textarea :id="`reason-${evidence.id}`" v-model="reason" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
            <div class="flex gap-2">
                <button
                    type="button"
                    :disabled="!reasonGiven || busy"
                    class="flex-1 rounded-lg bg-red-700 px-3 py-3 font-semibold text-white disabled:opacity-40"
                    @click="decide(asking)"
                >{{ withReason[asking].confirm }}</button>
                <button type="button" class="rounded-lg border px-3 py-3 font-semibold" @click="asking = null">Cancelar</button>
            </div>
        </div>

        <div v-else class="flex gap-2">
            <button
                v-if="evidence.actions.includes('publish')"
                type="button"
                :disabled="busy"
                class="flex-1 rounded-lg bg-slate-900 px-3 py-3 font-semibold text-white disabled:opacity-40"
                @click="decide('publish')"
            >Publicar</button>
            <button v-if="evidence.actions.includes('reject')" type="button" class="flex-1 rounded-lg border px-3 py-3 font-semibold" @click="ask('reject')">Rechazar</button>
            <button v-if="evidence.actions.includes('withdraw')" type="button" class="flex-1 rounded-lg border px-3 py-3 font-semibold" @click="ask('withdraw')">Retirar</button>
        </div>
    </article>
</template>
