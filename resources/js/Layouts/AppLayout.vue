<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import Logo from '@/Components/Brand/Logo.vue';
import Skyline from '@/Components/Brand/Skyline.vue';
import SectionTabs from '@/Components/Public/SectionTabs.vue';

const page = usePage();
// It. 43h (V13): el contacto público de la veeduría, en el pie de su sitio.
const contact = computed(() => page.props.organizationContact ?? null);
const telephone = (phone) => `tel:${phone.replace(/[^\d+]/g, '')}`;

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
    // It. 40e: en el sitio de una veeduría, las pestañas de sus secciones bajo la cabecera.
    sections: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <!-- Mobile-first shell: full-height column on phones, centered and wider only from md: up. -->
    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 bg-linear-to-r from-brand-800 to-brand-700 px-4 pt-[env(safe-area-inset-top)] text-white shadow-md">
            <div class="mx-auto flex h-14 w-full items-center gap-3" :class="$slots.sidebar ? 'md:max-w-5xl lg:max-w-6xl' : 'md:max-w-3xl lg:max-w-5xl'">
                <img v-if="logo" :src="logo" :alt="`Logo de ${title}`" class="size-9 rounded-lg bg-white object-contain p-0.5" />
                <Logo v-else />
                <!-- La marca, no el título de la pantalla: ese es el h1 de cada página (it. 40b). -->
                <p class="truncate font-display text-lg font-semibold tracking-tight">{{ title }}</p>
                <AccountMenu />
            </div>
            <div v-if="sections" class="mx-auto w-full md:max-w-3xl lg:max-w-5xl">
                <SectionTabs />
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

        <!-- It. 40f: el sitio de una veeduría termina con su barrio en silueta, GovTrace y la política (US-058-LEG). -->
        <footer v-if="sections" class="mt-8 text-brand-900">
            <Skyline />
            <div class="bg-brand-900 px-4 py-6 text-brand-50">
                <div class="mx-auto flex w-full flex-col gap-3 md:max-w-3xl md:flex-row md:items-center md:justify-between lg:max-w-5xl">
                    <p class="flex items-center gap-3 text-base">
                        <Logo />
                        <span><span class="font-display text-lg font-semibold text-white">GovTrace</span> · Evidencia ciudadana que nadie puede cambiar.</span>
                    </p>
                    <p v-if="contact" data-test="contact" class="flex flex-wrap items-center gap-x-4 text-base">
                        <span class="font-semibold text-white">Contacto de la veeduría:</span>
                        <a v-if="contact.email" :href="`mailto:${contact.email}`" class="inline-flex min-h-11 items-center text-white underline">{{ contact.email }}</a>
                        <a v-if="contact.phone" :href="telephone(contact.phone)" class="inline-flex min-h-11 items-center text-white underline">{{ contact.phone }}</a>
                    </p>
                    <Link href="/privacidad" class="inline-flex min-h-11 items-center self-start text-base font-semibold text-white underline md:self-auto">Política de tratamiento de datos</Link>
                </div>
            </div>
        </footer>

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
