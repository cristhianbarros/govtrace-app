<script setup>
// US-029: el visor de una foto de la línea de tiempo, a pantalla completa.
// Se cierra con "Cerrar", tocando fuera de la foto o con Escape.
import { nextTick, onMounted, ref } from 'vue';

defineProps({
    src: { type: String, required: true },
    alt: { type: String, required: true },
});

const emit = defineEmits(['close']);
const dialog = ref(null);

onMounted(async () => {
    await nextTick();
    dialog.value?.focus();
});
</script>

<template>
    <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-label="alt"
        tabindex="-1"
        class="fixed inset-0 z-50 flex flex-col bg-black/90 p-4 pt-[max(1rem,env(safe-area-inset-top))]"
        @keydown.esc="emit('close')"
        @click.self="emit('close')"
    >
        <button type="button" class="ml-auto min-h-11 rounded-lg bg-white px-4 text-sm font-semibold text-slate-900" @click="emit('close')">Cerrar</button>
        <img :src="src" :alt="alt" class="m-auto max-h-full max-w-full object-contain" />
    </div>
</template>
