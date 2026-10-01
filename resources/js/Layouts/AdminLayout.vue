<script setup>
// El panel del Administrador de Organización (it. 18): el nombre de la
// organización arriba y sus pantallas. It. 40c: en el celular, tres pestañas
// con ícono y "Más"; en el computador, una barra lateral por grupos. La
// Bandeja cuenta cuántas evidencias esperan revisión.
import {
    BuildingOffice2Icon,
    ChartBarIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    InboxIcon,
    MapPinIcon,
    ShieldCheckIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelSidebar from '@/Components/PanelSidebar.vue';
import PanelTabs from '@/Components/PanelTabs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

// El orden es el de la barra lateral; `primary`, las pestañas del celular.
const screens = [
    { href: '/admin/inbox', label: 'Bandeja', icon: InboxIcon, group: 'Revisar', primary: true },
    // It. 44f (US-059-LEG): lo que informan los ciudadanos desde el mapa.
    { href: '/admin/citizen-reports', label: 'Informes ciudadanos', icon: ChatBubbleLeftRightIcon, group: 'Revisar' },
    { href: '/admin/summary', label: 'Resumen', icon: ChartBarIcon, group: 'Revisar' },
    { href: '/admin/territory', label: 'Territorio', icon: MapPinIcon, group: 'Territorio y obras' },
    { href: '/admin/contracts', label: 'Contratos', icon: DocumentTextIcon, group: 'Territorio y obras' },
    { href: '/admin/worksites', label: 'Obras', icon: BuildingOffice2Icon, group: 'Territorio y obras', primary: true },
    { href: '/admin/observers', label: 'Veedores', icon: UsersIcon, group: 'Equipo', primary: true },
    { href: '/admin/organization', label: 'Organización', icon: Cog6ToothIcon, group: 'Organización' },
    { href: '/admin/audit', label: 'Auditoría', icon: ClipboardDocumentListIcon, group: 'Organización' },
    { href: '/admin/authorization', label: 'Autorización', icon: ShieldCheckIcon, group: 'Organización' },
];

const badges = computed(() => (page.props.inboxPending ? { '/admin/inbox': page.props.inboxPending } : {}));

// US-021: evidencias en "Falla de Sellado" (solo el Administrador recibe este número).
const failuresBanner = computed(() => {
    const count = page.props.sealingFailures ?? 0;
    if (count === 0) {
        return null;
    }
    // It. 45e (enmienda de US-021): sin jerga técnica.
    return count === 1
        ? 'Alerta: 1 evidencia no se pudo certificar de forma segura. El soporte técnico de GovTrace tiene que revisarla.'
        : `Alerta: ${count} evidencias no se pudieron certificar de forma segura. El soporte técnico de GovTrace tiene que revisarlas.`;
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

        <template #sidebar>
            <PanelSidebar :screens="screens" :badges="badges" badge-label="por revisar" />
        </template>
        <template #nav>
            <PanelTabs :screens="screens" :badges="badges" />
        </template>
    </AppLayout>
</template>
