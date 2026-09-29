<script setup>
// US-024: el veredicto del validador — verde gigante si es auténtico, rojo
// si está alterado; ámbar si no se encontró o no se pudo verificar.
import { computed } from 'vue';

const props = defineProps({
    result: { type: Object, required: true }, // { verdict, message, explorerUrl?, archived? }
});

const look = computed(() => ({
    authentic: 'bg-green-600 text-white text-lg',
    altered: 'bg-red-600 text-white text-lg',
}[props.result.verdict] ?? 'bg-amber-100 text-amber-900'));

const role = computed(() => (props.result.verdict === 'authentic' ? 'status' : 'alert'));
</script>

<template>
    <div data-test="verdict" :role="role" class="mt-4 flex flex-col gap-2 rounded-lg p-4 font-semibold" :class="look">
        <p>{{ result.message }}</p>
        <p v-if="result.archived" class="text-sm font-normal">El sello está archivado por su vigencia en la red (TTL): sus datos siguen ahí y la verificación vale igual.</p>
        <a
            v-if="result.explorerUrl"
            :href="result.explorerUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 items-center self-start rounded-lg bg-white px-3 text-sm text-slate-900"
        >Ver en Stellar Expert</a>
    </div>
</template>
