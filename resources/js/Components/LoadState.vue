<script setup>
// Los tres estados de una pantalla que carga datos: cargando, error (con
// "Reintentar") y vacío. It. 40f (R-UX-10): el vacío y el error, ilustrados —
// cada pantalla elige el dibujo de su vacío (Components/Brand/Illustration).
import Illustration from '@/Components/Brand/Illustration.vue';

defineProps({
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    empty: { type: Boolean, default: false },
    loadingText: { type: String, required: true },
    emptyText: { type: String, required: true },
    illustration: { type: String, default: 'empty' },
});
defineEmits(['retry']);
</script>

<template>
    <p v-if="loading" class="flex items-center justify-center gap-3 py-6 text-base text-slate-700">
        <span aria-hidden="true" class="size-5 rounded-full border-[3px] border-brand-100 border-t-brand-700 motion-safe:animate-spin"></span>
        {{ loadingText }}
    </p>
    <div v-else-if="error" role="alert" class="flex flex-col items-center gap-3 rounded-2xl bg-red-50 p-4 text-center text-base text-red-800 md:flex-row md:text-left">
        <Illustration name="error" size="w-28" :backdrop="false" />
        <div class="flex flex-col items-center gap-3 md:items-start">
            <p>{{ error }}</p>
            <button type="button" class="min-h-11 rounded-xl border border-red-300 bg-white px-4 py-2 font-semibold hover:bg-red-100" @click="$emit('retry')">Reintentar</button>
        </div>
    </div>
    <div v-else-if="empty" data-test="empty" class="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-6 text-center shadow-soft ring-1 ring-slate-900/5">
        <Illustration :name="illustration" size="w-36" />
        <p class="max-w-prose text-base text-slate-700">{{ emptyText }}</p>
    </div>
    <slot v-else />
</template>
