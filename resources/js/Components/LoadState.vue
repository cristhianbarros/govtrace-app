<script setup>
// Los estados de una pantalla que carga datos: cargando, error con
// "Reintentar", vacío, y el contenido cuando hay datos.
defineProps({
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    empty: { type: Boolean, default: false },
    loadingText: { type: String, required: true },
    emptyText: { type: String, required: true },
});

defineEmits(['retry']);
</script>

<template>
    <p v-if="loading" class="py-6 text-center text-sm text-slate-500">{{ loadingText }}</p>
    <div v-else-if="error" role="alert" class="flex flex-col gap-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">
        <p>{{ error }}</p>
        <button type="button" class="min-h-11 self-start rounded-lg border border-red-300 bg-white px-3 py-2 font-semibold" @click="$emit('retry')">
            Reintentar
        </button>
    </div>
    <p v-else-if="empty" class="rounded-2xl bg-white p-4 text-sm text-slate-600 shadow-soft ring-1 ring-slate-900/5">{{ emptyText }}</p>
    <slot v-else />
</template>
