<script setup>
// It. 52: "Ver 20 más", el mismo botón en cada listado que llega de a una página
// (la lista del veedor, la bandeja, los veedores, las obras…).
defineProps({ loading: { type: Boolean, default: false } });
defineEmits(['more']);
</script>

<template>
    <button
        type="button"
        data-test="more"
        :disabled="loading"
        class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40"
        @click="$emit('more')"
    >
        {{ loading ? 'Cargando…' : 'Ver 20 más' }}
    </button>
</template>
