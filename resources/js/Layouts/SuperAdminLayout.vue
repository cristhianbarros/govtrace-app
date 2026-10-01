<script setup>
// El panel global del Super Administrador. It. 40c: en el celular, tres
// pestañas con ícono y "Más"; en el computador, una barra lateral.
import {
    AdjustmentsHorizontalIcon,
    ArrowPathIcon,
    BuildingLibraryIcon,
    CheckBadgeIcon,
    ClipboardDocumentListIcon,
    InboxArrowDownIcon,
    PresentationChartLineIcon,
} from '@heroicons/vue/24/outline';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelSidebar from '@/Components/PanelSidebar.vue';
import PanelTabs from '@/Components/PanelTabs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, required: true },
});

const screens = [
    { href: '/admin/organizations', label: 'Organizaciones', icon: BuildingLibraryIcon, group: 'Organizaciones', primary: true },
    // It. 43k (V10): las veedurías que pidieron su alta desde el Inicio.
    { href: '/admin/organization-requests', label: 'Solicitudes de alta', icon: InboxArrowDownIcon, group: 'Organizaciones' },
    { href: '/admin/sealing', label: 'Sellado', icon: CheckBadgeIcon, group: 'Operación', primary: true },
    { href: '/admin/secop-health', label: 'SECOP', icon: ArrowPathIcon, group: 'Operación', primary: true },
    { href: '/admin/usage', label: 'Uso', icon: PresentationChartLineIcon, group: 'Operación' },
    { href: '/admin/parameters', label: 'Parámetros', icon: AdjustmentsHorizontalIcon, group: 'Configuración' },
    { href: '/admin/audit', label: 'Auditoría', icon: ClipboardDocumentListIcon, group: 'Configuración' },
];

const page = usePage();
const badges = computed(() => (page.props.organizationRequestsPending ? { '/admin/organization-requests': page.props.organizationRequestsPending } : {}));
</script>

<template>
    <Head :title="title" />
    <AppLayout title="Panel global">
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <slot />
        </div>

        <template #sidebar>
            <PanelSidebar :screens="screens" :badges="badges" />
        </template>
        <template #nav>
            <PanelTabs :screens="screens" :badges="badges" />
        </template>
    </AppLayout>
</template>
