<script setup>
// It. 40c: la navegación de un panel en el computador — todas sus pantallas a
// la vista, agrupadas, con su ícono y su nombre. En el celular la reemplazan
// las pestañas de arriba (PanelTabs).
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isCurrentScreen } from '@/lib/navigation.js';

const props = defineProps({
    screens: { type: Array, required: true }, // [{ href, label, icon, group }]
    badges: { type: Object, default: () => ({}) },
    // Qué dice cada número, para un lector de pantalla: "3 por revisar".
    badgeLabel: { type: String, default: 'pendientes' },
});

const page = usePage();
const groups = computed(() => {
    const byName = new Map();
    for (const screen of props.screens) {
        byName.set(screen.group, [...(byName.get(screen.group) ?? []), screen]);
    }
    return [...byName.entries()];
});
</script>

<template>
    <nav data-test="sidebar" aria-label="Pantallas del panel" class="flex flex-col gap-5">
        <section v-for="([name, screens], index) in groups" :key="name">
            <p :id="`sidebar-group-${index}`" class="px-3 text-sm font-semibold uppercase tracking-wide text-slate-600">{{ name }}</p>
            <ul :aria-labelledby="`sidebar-group-${index}`" class="mt-1 flex flex-col gap-1">
                <li v-for="screen in screens" :key="screen.href">
                    <Link
                        :href="screen.href"
                        :aria-current="isCurrentScreen(page.url, screen.href) ? 'page' : undefined"
                        :aria-label="badges[screen.href] ? `${screen.label}, ${badges[screen.href]} ${badgeLabel}` : undefined"
                        class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-base"
                        :class="isCurrentScreen(page.url, screen.href) ? 'bg-brand-700 font-semibold text-white' : 'text-slate-800 hover:bg-slate-200'"
                    >
                        <component :is="screen.icon" aria-hidden="true" class="size-6 shrink-0" />
                        <span class="flex-1">{{ screen.label }}</span>
                        <span v-if="badges[screen.href]" aria-hidden="true" class="min-w-6 rounded-full bg-red-700 px-1.5 text-center text-sm font-bold text-white">{{ badges[screen.href] }}</span>
                    </Link>
                </li>
            </ul>
        </section>
    </nav>
</template>
