<script setup>
// Elegir o arrastrar un archivo — el validador lo lee en el navegador, sin
// subirlo a ningún lado (R-VER-01).
import { ref } from 'vue';

defineProps({
    label: { type: String, required: true },
    accept: { type: String, required: true },
    test: { type: String, default: 'file' },
    chosen: { type: Object, default: null }, // el File elegido
});

const emit = defineEmits(['choose']);
const dragging = ref(false);

function pick(files) {
    if (files?.length) {
        emit('choose', files[0]);
    }
}
</script>

<template>
    <label
        class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed p-4 text-center text-sm"
        :class="dragging ? 'border-brand-700 bg-slate-100' : 'border-slate-300 bg-white'"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="dragging = false; pick($event.dataTransfer?.files)"
    >
        <span class="font-semibold">{{ label }}</span>
        <span v-if="chosen" class="break-all text-slate-600">{{ chosen.name }}</span>
        <input :data-test="test" type="file" :accept="accept" class="sr-only" @change="pick($event.target.files)" />
    </label>
</template>
