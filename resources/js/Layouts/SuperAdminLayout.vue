<script setup>
// El panel global del Super Administrador. It. 40c: en el celular, tres
// pestañas con ícono y "Más"; en el computador, una barra lateral.
import {
    AdjustmentsHorizontalIcon,
    ArrowPathIcon,
    BuildingLibraryIcon,
    CheckBadgeIcon,
    ClipboardDocumentListIcon,
    PresentationChartLineIcon,
} from '@heroicons/vue/24/outline';
import { Head } from '@inertiajs/vue3';
import PanelSidebar from '@/Components/PanelSidebar.vue';
import PanelTabs from '@/Components/PanelTabs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, required: true },
});

const screens = [
    { href: '/admin/organizations', label: 'Organizaciones', icon: BuildingLibraryIcon, group: 'Organizaciones', primary: true },
    { href: '/admin/sealing', label: 'Sellado', icon: CheckBadgeIcon, group: 'Operación', primary: true },
    { href: '/admin/secop-health', label: 'SECOP', icon: ArrowPathIcon, group: 'Operación', primary: true },
    { href: '/admin/usage', label: 'Uso', icon: PresentationChartLineIcon, group: 'Operación' },
    { href: '/admin/parameters', label: 'Parámetros', icon: AdjustmentsHorizontalIcon, group: 'Configuración' },
    { href: '/admin/audit', label: 'Auditoría', icon: ClipboardDocumentListIcon, group: 'Configuración' },
];
</script>

<template>
    <Head :title="title" />
    <AppLayout title="Panel global">
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <slot />
        </div>

        <template #sidebar>
            <PanelSidebar :screens="screens" />
        </template>
        <template #nav>
            <PanelTabs :screens="screens" />
        </template>
    </AppLayout>
</template>
