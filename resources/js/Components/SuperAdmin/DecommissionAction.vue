<script setup>
// US-003b: la baja definitiva de una organización, con doble confirmación.
// La primera pide al servidor lo que implica (y un token); la segunda exige
// escribir su subdominio. Cancelar en la segunda no cambia nada.
import { ref } from 'vue';
import { formatDate } from '@/lib/format.js';
import { confirmDecommission, startDecommission } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    organizationId: { type: String, required: true },
});

const emit = defineEmits(['done']);

const first = ref(null); // la primera confirmación: {token, summary, message}
const typed = ref('');
const busy = ref(false);
const error = ref(null);

async function begin() {
    busy.value = true;
    error.value = null;
    try {
        first.value = await startDecommission(props.organizationId);
    } catch (failure) {
        error.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

async function confirm() {
    busy.value = true;
    error.value = null;
    try {
        const { message } = await confirmDecommission(props.organizationId, first.value.token, typed.value.trim());
        cancel();
        emit('done', message);
    } catch (failure) {
        error.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

function cancel() {
    first.value = null;
    typed.value = '';
    error.value = null;
}

const matches = () => typed.value.trim().toLowerCase() === first.value.summary.subdomain;
</script>

<template>
    <div class="flex flex-col gap-2">
        <template v-if="!first">
            <button type="button" :disabled="busy" class="min-h-11 self-start rounded-lg border border-red-300 bg-white px-3 text-sm font-semibold text-red-800" @click="begin">
                Dar de baja
            </button>
            <p v-if="error" role="alert" class="text-xs text-red-700">{{ error }}</p>
        </template>

        <section v-else data-test="decommission" class="flex flex-col gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900">
            <p class="text-sm font-semibold">Baja definitiva de {{ first.summary.organization }}</p>
            <ul class="list-disc pl-4">
                <li>Sus usuarios pierden el acceso y su mapa público sale de línea. No se puede deshacer.</li>
                <li>{{ first.summary.sealed_reports }} reportes sellados en Stellar siguen verificables en el validador.</li>
                <li>Sus archivos se conservan hasta el {{ formatDate(first.summary.files_kept_until) }}; después se borran. Sus sellos, nunca.</li>
            </ul>
            <label for="decommission-subdomain" class="font-semibold">{{ first.message }}</label>
            <input
                id="decommission-subdomain"
                v-model="typed"
                type="text"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                class="rounded-lg border border-red-300 bg-white px-3 py-2 text-base text-slate-900"
            />
            <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" :disabled="!matches() || busy" class="min-h-11 rounded-lg bg-red-700 px-3 py-2 font-semibold text-white disabled:opacity-40" @click="confirm">
                    Dar de baja definitivamente
                </button>
                <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold" @click="cancel">Cancelar</button>
            </div>
        </section>
    </div>
</template>
