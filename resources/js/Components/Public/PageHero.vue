<script setup>
// It. 40f: el encabezado de una página pública — su título, en una o dos
// líneas qué se hace en ella, y su ilustración (como el del mapa, it. 40e).
import Illustration from '@/Components/Brand/Illustration.vue';

defineProps({
    title: { type: String, required: true },
    illustration: { type: String, required: true },
});
</script>

<template>
    <section data-test="hero" class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 md:p-6">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-3xl">{{ title }}</h1>
            <div class="mt-1 text-base text-slate-700"><slot /></div>
        </div>
        <Illustration :name="illustration" size="w-24 md:w-36" />
    </section>
</template>
