<script setup>
// Las pestañas de la app del veedor, al alcance del pulgar. "Salir" vive en
// el menú de cuenta de la cabecera (it. 40b), donde siempre pregunta antes y
// avisa si quedan reportes sin enviar (US-018).
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
        class="flex min-h-14 flex-1 items-center justify-center text-base font-semibold"
        :class="tab.href === current ? 'border-t-4 border-slate-900 text-slate-900' : 'border-t-4 border-transparent text-slate-600'"
    >{{ tab.label }}</Link>
</template>
