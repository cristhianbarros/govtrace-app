<script setup>
// Las dos pestañas de la app del veedor, al alcance del pulgar.
import { Link } from '@inertiajs/vue3';

defineProps({
    current: { type: String, required: true }, // la ruta de la pantalla abierta
});

const TABS = [
    { href: '/reports/new', label: 'Nuevo Reporte' },
    { href: '/my-reports', label: 'Mis Reportes' },
];
</script>

<template>
    <Link
        v-for="tab in TABS"
        :key="tab.href"
        :href="tab.href"
        :aria-current="tab.href === current ? 'page' : undefined"
        class="flex min-h-14 flex-1 items-center justify-center text-sm font-semibold"
        :class="tab.href === current ? 'text-slate-900' : 'text-slate-500'"
    >{{ tab.label }}</Link>
</template>
