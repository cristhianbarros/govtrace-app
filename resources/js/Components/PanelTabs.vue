<script setup>
// It. 40c: la navegación de un panel en el celular, arriba, siempre a la vista:
// las pantallas principales como pestañas con su ícono y su nombre (nunca
// solo uno de los dos), y "Más" para el resto. En el computador la
// reemplaza la barra lateral (PanelSidebar).
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { isCurrentScreen } from '@/lib/navigation.js';

const props = defineProps({
    screens: { type: Array, required: true }, // [{ href, label, icon, group, primary }]
    badges: { type: Object, default: () => ({}) }, // { href: cuántos }
});

const page = usePage();
const open = ref(false);
const root = ref(null);

const primary = computed(() => props.screens.filter((screen) => screen.primary));
const others = computed(() => props.screens.filter((screen) => !screen.primary));
const current = (screen) => isCurrentScreen(page.url, screen.href);
const inOthers = computed(() => others.value.some(current));
// It. 65a: lo que espera en las pantallas de "Más" se cuenta en "Más", y en su menú, al lado de cada una.
const othersPending = computed(() => others.value.reduce((total, screen) => total + (props.badges[screen.href] ?? 0), 0));

function closeOnOutsideClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}
document.addEventListener('click', closeOnOutsideClick);
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));

const tabClass = (active) =>
    `flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 border-b-4 text-sm font-semibold ${active ? 'border-brand-700 text-brand-800' : 'border-transparent text-slate-600'}`;
</script>

<template>
    <div ref="root" data-test="tabs" class="relative flex w-full">
        <Link
            v-for="screen in primary"
            :key="screen.href"
            :href="screen.href"
            :aria-current="current(screen) ? 'page' : undefined"
            :class="tabClass(current(screen))"
        >
            <span class="relative">
                <component :is="screen.icon" aria-hidden="true" class="size-6" />
                <span v-if="badges[screen.href]" class="absolute -right-3 -top-1 min-w-5 rounded-full bg-red-700 px-1 text-center text-xs font-bold leading-5 text-white">{{ badges[screen.href] }}</span>
            </span>
            {{ screen.label }}
        </Link>
        <button
            type="button"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-label="othersPending ? `Más, ${othersPending} pendientes` : undefined"
            :data-current="inOthers ? 'true' : 'false'"
            :class="tabClass(inOthers)"
            @click="open = !open"
        >
            <span class="relative">
                <EllipsisHorizontalIcon aria-hidden="true" class="size-6" />
                <span v-if="othersPending" aria-hidden="true" class="absolute -right-3 -top-1 min-w-5 rounded-full bg-red-700 px-1 text-center text-xs font-bold leading-5 text-white">{{ othersPending }}</span>
            </span>
            Más
        </button>

        <div v-if="open" data-test="more" role="menu" class="absolute right-2 top-full mt-1 z-20 w-64 rounded-lg bg-white p-2 shadow-lg ring-1 ring-slate-200">
            <Link
                v-for="screen in others"
                :key="screen.href"
                :href="screen.href"
                role="menuitem"
                :aria-current="current(screen) ? 'page' : undefined"
                class="flex min-h-12 items-center gap-3 rounded-lg px-3 text-base"
                :class="current(screen) ? 'bg-brand-700 font-semibold text-white' : 'text-slate-900 hover:bg-slate-100'"
            >
                <component :is="screen.icon" aria-hidden="true" class="size-6 shrink-0" />
                <span class="flex-1">{{ screen.label }}</span>
                <span v-if="badges[screen.href]" class="min-w-6 rounded-full bg-red-700 px-1.5 text-center text-sm font-bold text-white">{{ badges[screen.href] }}</span>
            </Link>
        </div>
    </div>
</template>
