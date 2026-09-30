<script setup>
// US-047-MNT: las evidencias en "Falla de Sellado" de todas las
// organizaciones (tras el quinto intento, US-021). Se eligen una o varias
// y vuelven a la cola, con 5 intentos nuevos.
import { computed, ref } from 'vue';
import { formatDateTime } from '@/lib/format.js';
import { requeueSeals } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    failures: { type: Array, required: true },
});

const emit = defineEmits(['requeued']);

const keyOf = (failure) => `${failure.organization_id}:${failure.report_id}`;

const selected = ref([]);
const sending = ref(false);
const done = ref(null);
const rejection = ref(null);

const chosen = computed(() => props.failures.filter((failure) => selected.value.includes(keyOf(failure))));

function selectAll() {
    selected.value = props.failures.map(keyOf);
}

async function requeue() {
    sending.value = true;
    done.value = null;
    rejection.value = null;
    try {
        const { message } = await requeueSeals(chosen.value.map(({ organization_id, report_id }) => ({ organization_id, report_id })));
        selected.value = [];
        done.value = message;
        emit('requeued');
    } catch (error) {
        rejection.value = errorMessage(error);
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <section data-test="failures" class="flex flex-col gap-3">
        <h3 class="text-base font-semibold">Falla de Sellado</h3>

        <p v-if="done" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ done }}</p>
        <p v-if="rejection" data-test="requeue-error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ rejection }}</p>

        <p v-if="failures.length === 0" class="rounded-2xl bg-white p-4 text-sm text-slate-600 shadow-soft ring-1 ring-slate-900/5">No hay evidencias en "Falla de Sellado".</p>
        <template v-else>
            <ul class="flex flex-col gap-2">
                <li v-for="failure in failures" :key="keyOf(failure)" data-test="failure">
                    <label class="flex items-start gap-3 rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-3 text-sm">
                        <input v-model="selected" type="checkbox" :value="keyOf(failure)" class="mt-0.5 size-5 shrink-0" />
                        <span class="flex min-w-0 flex-col gap-1">
                            <span class="font-semibold">{{ failure.organization }} · Reporte #{{ failure.report_id }}</span>
                            <span class="text-xs text-slate-600">Falló el {{ formatDateTime(failure.failed_at) }} · {{ failure.attempts }} intentos</span>
                            <span class="break-words text-xs text-red-800">{{ failure.last_error }}</span>
                        </span>
                    </label>
                </li>
            </ul>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 bg-white px-3 text-sm font-semibold" @click="selectAll">Seleccionar todas</button>
                <button
                    type="button"
                    class="min-h-11 flex-1 rounded-xl bg-brand-700 px-3 text-sm font-semibold text-white disabled:opacity-40 hover:bg-brand-800"
                    :disabled="chosen.length === 0 || sending"
                    @click="requeue"
                >
                    Volver a encolar{{ chosen.length ? ` (${chosen.length})` : '' }}
                </button>
            </div>
        </template>
    </section>
</template>
