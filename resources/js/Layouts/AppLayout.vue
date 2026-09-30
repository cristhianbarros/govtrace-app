<script setup>
import AccountMenu from '@/Components/AccountMenu.vue';

defineProps({
    title: {
        type: String,
        default: 'GovTrace',
    },
    // El logo de la organización (US-007), junto al título.
    logo: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <!-- Mobile-first shell: full-height column on phones, centered and wider only from md: up. -->
    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 bg-slate-900 px-4 pt-[env(safe-area-inset-top)] text-white">
            <div class="mx-auto flex h-14 w-full items-center gap-3" :class="$slots.sidebar ? 'md:max-w-5xl lg:max-w-6xl' : 'md:max-w-3xl lg:max-w-5xl'">
                <img v-if="logo" :src="logo" :alt="`Logo de ${title}`" class="size-9 rounded bg-white object-contain p-0.5" />
                <!-- La marca, no el título de la pantalla: ese es el h1 de cada página (it. 40b). -->
                <p class="truncate text-lg font-semibold">{{ title }}</p>
                <AccountMenu />
            </div>
        </header>

        <!-- It. 40c: con barra lateral (los paneles), en el computador la navegación va a la izquierda. -->
        <div v-if="$slots.sidebar" class="mx-auto flex w-full flex-1 md:max-w-5xl lg:max-w-6xl">
            <aside class="hidden w-60 shrink-0 border-r border-slate-200 py-4 pr-2 md:block">
                <div class="sticky top-18">
                    <slot name="sidebar" />
                </div>
            </aside>
            <main class="w-full min-w-0 flex-1 p-4">
                <slot />
            </main>
        </div>
        <main v-else class="mx-auto w-full flex-1 p-4 md:max-w-3xl lg:max-w-5xl">
            <slot />
        </main>

        <!-- Optional bottom navigation: fixed and thumb-reachable on phones. On a computer it is not needed
             there (with a sidebar, it goes; without one, it stays at the end instead of covering content). -->
        <nav
            v-if="$slots.nav"
            aria-label="Navegación"
            class="sticky bottom-0 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]"
            :class="$slots.sidebar ? 'md:hidden' : 'md:static'"
        >
            <div class="mx-auto flex w-full md:max-w-3xl lg:max-w-5xl">
                <slot name="nav" />
            </div>
        </nav>
    </div>
</template>
