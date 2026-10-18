<script setup>
// It. 62f: la foto recién tomada, al instante, mientras se revisan sus rostros.
// Una copia pequeña del cuadro, no la foto entera; se suelta al cerrarse.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { releaseCanvas } from '@/lib/evidence/photos.js';

const props = defineProps({
    source: { type: Object, required: true }, // el canvas del cuadro
});

const WIDTH = 480;
const view = ref(null);

onMounted(() => {
    const scale = Math.min(1, WIDTH / props.source.width);
    view.value.width = Math.round(props.source.width * scale);
    view.value.height = Math.round(props.source.height * scale);
    view.value.getContext('2d')?.drawImage(props.source, 0, 0, view.value.width, view.value.height);
});

onBeforeUnmount(() => releaseCanvas(view.value));
</script>

<template>
    <canvas ref="view" role="img" aria-label="La foto que acaba de tomar" class="w-full rounded-lg bg-slate-100"></canvas>
</template>
