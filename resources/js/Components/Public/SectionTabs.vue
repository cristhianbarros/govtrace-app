<script setup>
// It. 40e (US-027): el sitio de la veeduría se recorre con pestañas — Obras,
// Estadísticas y Validar —, con la actual marcada, en vez de botones sueltos
// al pie del mapa. Son enlaces (cada sección es otra página), así que llevan
// aria-current y no el rol "tab", que es para paneles de una misma página.
import { ChartBarIcon, MapIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const SECTIONS = [
    { href: '/', label: 'Obras', icon: MapIcon, owns: (path) => path === '/' || path.startsWith('/worksite/') },
    { href: '/stats', label: 'Estadísticas', icon: ChartBarIcon, owns: (path) => path === '/stats' },
    { href: '/verify', label: 'Validar', icon: ShieldCheckIcon, owns: (path) => path === '/verify' },
];

const path = computed(() => page.url.split('?')[0]);
</script>

<template>
    <nav aria-label="Secciones de la veeduría" class="flex">
        <Link
            v-for="section in SECTIONS"
            :key="section.href"
            :href="section.href"
            :aria-current="section.owns(path) ? 'page' : undefined"
            class="flex min-h-12 flex-1 items-center justify-center gap-2 border-b-4 px-2 text-base font-semibold transition-colors md:flex-none md:px-6"
            :class="section.owns(path) ? 'border-accent-400 text-white' : 'border-transparent text-brand-100 hover:border-brand-200 hover:text-white'"
        >
            <component :is="section.icon" aria-hidden="true" class="size-6 shrink-0" />
            <span>{{ section.label }}</span>
        </Link>
    </nav>
</template>
