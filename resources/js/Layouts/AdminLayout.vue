<script setup>
// El panel del Administrador de Organización (it. 18): el nombre de la
// organización arriba y sus pantallas en la barra de abajo, al alcance del
// pulgar en el teléfono.
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

const screens = [
    { href: '/admin/inbox', label: 'Bandeja' },
    { href: '/admin/observers', label: 'Veedores' },
    { href: '/admin/territory', label: 'Territorio' },
    { href: '/admin/contracts', label: 'Contratos' },
    { href: '/admin/worksites', label: 'Obras' },
];

const isCurrent = (href) => page.url === href || page.url.startsWith(`${href}?`);
</script>

<template>
    <Head :title="title" />
    <AppLayout :title="page.props.organization">
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
