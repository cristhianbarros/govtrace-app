<script setup>
// Las pestañas de la app del veedor, al alcance del pulgar. "Salir" vive en
// el menú de cuenta de la cabecera (it. 40b), donde siempre pregunta antes y
// avisa si quedan reportes sin enviar (US-018).
import { PlusCircleIcon, QueueListIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';

defineProps({
    current: { type: String, required: true }, // la ruta de la pantalla abierta
});

const TABS = [
    { href: '/reports/new', label: 'Nuevo Reporte', icon: PlusCircleIcon },
    { href: '/my-reports', label: 'Mis Reportes', icon: QueueListIcon },
];
</script>

<template>
    <Link
        v-for="tab in TABS"
        :key="tab.href"
        :href="tab.href"
        :aria-current="tab.href === current ? 'page' : undefined"
        class="flex min-h-16 flex-1 flex-col items-center justify-center gap-0.5 text-base font-semibold"
        :class="tab.href === current ? 'border-t-4 border-brand-700 text-brand-800' : 'border-t-4 border-transparent text-slate-600'"
    ><component :is="tab.icon" aria-hidden="true" class="size-6" />{{ tab.label }}</Link>
</template>
