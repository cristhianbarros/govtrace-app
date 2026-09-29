<script setup>
// El panel global del Super Administrador: sus pantallas en la barra de
// abajo, como el panel de cada organización.
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

const screens = [
    { href: '/admin/organizations', label: 'Organizaciones' },
    { href: '/admin/parameters', label: 'Parámetros' },
    { href: '/admin/audit', label: 'Auditoría' },
    { href: '/admin/secop-health', label: 'SECOP' },
    { href: '/admin/sealing', label: 'Sellado' },
];

const isCurrent = (href) => page.url === href || page.url.startsWith(`${href}?`) || page.url.startsWith(`${href}/`);
</script>

<template>
    <Head :title="title" />
    <AppLayout title="Panel global">
        <div class="flex flex-col gap-4">
            <h2 class="text-xl font-semibold">{{ title }}</h2>
            <slot />
        </div>

        <template #nav>
            <Link
                v-for="screen in screens"
                :key="screen.href"
                :href="screen.href"
                :aria-current="isCurrent(screen.href) ? 'page' : undefined"
                class="flex-1 py-3 text-center text-xs font-semibold"
                :class="isCurrent(screen.href) ? 'text-slate-900' : 'text-slate-500'"
            >
                {{ screen.label }}
            </Link>
        </template>
    </AppLayout>
</template>
