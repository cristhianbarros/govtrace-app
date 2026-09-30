<script setup>
// El panel del Administrador de Organización (it. 18): el nombre de la
// organización arriba y sus pantallas en la barra de abajo, al alcance del
// pulgar en el teléfono.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    { href: '/admin/organization', label: 'Organización' },
];

const isCurrent = (href) => page.url === href || page.url.startsWith(`${href}?`);

// US-021: evidencias en "Falla de Sellado" (solo el Administrador recibe este número).
const failuresBanner = computed(() => {
    const count = page.props.sealingFailures ?? 0;
    if (count === 0) {
        return null;
    }
    const evidences = count === 1 ? '1 evidencia no pudo ser sellada' : `${count} evidencias no pudieron ser selladas`;
    return `Alerta: ${evidences} en blockchain. Se requiere intervención del soporte técnico.`;
});
</script>

<template>
    <Head :title="title" />
    <AppLayout :title="page.props.organization" :logo="page.props.organizationLogo">
        <div class="flex flex-col gap-4">
            <p v-if="failuresBanner" role="alert" class="rounded-lg bg-red-600 p-3 text-sm font-semibold text-white">{{ failuresBanner }}</p>
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <slot />
        </div>

        <template #nav>
            <Link
                v-for="screen in screens"
                :key="screen.href"
                :href="screen.href"
                :aria-current="isCurrent(screen.href) ? 'page' : undefined"
                class="flex-1 px-1 py-3 text-center text-[11px] font-semibold"
                :class="isCurrent(screen.href) ? 'text-slate-900' : 'text-slate-500'"
            >
                {{ screen.label }}
            </Link>
        </template>
    </AppLayout>
</template>
