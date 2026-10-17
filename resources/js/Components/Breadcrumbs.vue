<script setup>
// It. 62 (US-067): dónde está la persona y cómo volver a cada nivel —
// "GovTrace › <veeduría> › <panel> › <pantalla>". Cada veeduría vive en su
// subdominio, así que "GovTrace" puede ser otro dominio: ese enlace es un <a>
// de verdad, no una visita de Inertia. En el celular, un solo enlace al nivel
// anterior que lleva a otra pantalla.
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    crumbs: { type: Array, required: true }, // [{ label, href? }]; el último es la pantalla actual
});

const page = usePage();
const path = computed(() => (page.url ?? '').split(/[?#]/)[0]);
const external = (href) => /^https?:\/\//.test(href);

// Un nivel es enlace, salvo el último (donde está) y uno que lleva a esta misma pantalla.
const levels = computed(() =>
    props.crumbs.map((crumb, index) => {
        const current = index === props.crumbs.length - 1;
        const toThisScreen = !external(crumb.href ?? '') && crumb.href === path.value;
        return { label: crumb.label, current, link: !current && crumb.href && !toThisScreen ? crumb.href : null };
    }),
);
const back = computed(() => [...levels.value].reverse().find((level) => level.link) ?? null);
</script>

<template>
    <nav aria-label="Ruta" class="mb-2 text-base">
        <ol class="hidden flex-wrap items-center md:flex">
            <li v-for="(level, index) in levels" :key="index" class="flex min-w-0 items-center">
                <span v-if="index > 0" aria-hidden="true" class="px-2 text-slate-500">›</span>
                <a v-if="level.link && external(level.link)" data-crumb :href="level.link" class="inline-flex min-h-11 items-center font-semibold text-brand-800 underline hover:text-brand-900">{{ level.label }}</a>
                <Link v-else-if="level.link" data-crumb :href="level.link" class="inline-flex min-h-11 items-center font-semibold text-brand-800 underline hover:text-brand-900">{{ level.label }}</Link>
                <span v-else data-crumb :aria-current="level.current ? 'page' : undefined" class="block max-w-md truncate py-2.5" :class="level.current ? 'font-semibold text-slate-900' : 'text-slate-700'">{{ level.label }}</span>
            </li>
        </ol>
        <template v-if="back">
            <a v-if="external(back.link)" data-test="back" :href="back.link" class="inline-flex min-h-11 items-center font-semibold text-slate-700 md:hidden">← {{ back.label }}</a>
            <Link v-else data-test="back" :href="back.link" class="inline-flex min-h-11 items-center font-semibold text-slate-700 md:hidden">← {{ back.label }}</Link>
        </template>
    </nav>
</template>
