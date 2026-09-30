<script setup>
// Una acción sobre una fila de un listado (desactivar un veedor, suspender
// una organización…). Con `confirmLabel`, pide confirmar antes, mostrando
// `warning`; si el servidor dice que no, el motivo queda en la fila.
import { ref } from 'vue';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    label: { type: String, required: true },
    run: { type: Function, required: true }, // devuelve la respuesta del servidor, con su `message`
    confirmLabel: { type: String, default: null },
    warning: { type: String, default: null },
});

const emit = defineEmits(['done']);

const asking = ref(false);
const busy = ref(false);
const error = ref(null);

async function go() {
    if (props.confirmLabel && !asking.value) {
        asking.value = true;
        error.value = null;
        return;
    }

    busy.value = true;
    error.value = null;
    try {
        const answer = await props.run();
        asking.value = false;
        emit('done', answer?.message);
    } catch (failure) {
        error.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <p v-if="asking && warning" class="rounded bg-amber-50 p-2 text-xs text-amber-900">{{ warning }}</p>
        <div class="flex gap-2">
            <button
                type="button"
                :disabled="busy"
                class="min-h-11 rounded-lg border px-3 text-sm font-semibold disabled:opacity-40"
                :class="asking ? 'border-red-700 bg-red-700 text-white' : 'bg-white'"
                @click="go"
            >{{ asking ? confirmLabel : label }}</button>
            <button v-if="asking" type="button" class="min-h-11 rounded-lg border bg-white px-3 text-sm font-semibold" @click="asking = false">Cancelar</button>
        </div>
        <p v-if="error" role="alert" class="text-xs text-red-700">{{ error }}</p>
    </div>
</template>
