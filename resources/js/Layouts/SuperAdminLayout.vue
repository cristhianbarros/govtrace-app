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
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
    // It. 46a (US-063-USR): varios Super Administradores, y nunca ninguno.
    { href: '/admin/super-administrators', label: 'Super Administradores', icon: UserGroupIcon, group: 'Configuración' },
    { href: '/admin/parameters', label: 'Parámetros', icon: AdjustmentsHorizontalIcon, group: 'Configuración' },
    { href: '/admin/audit', label: 'Auditoría', icon: ClipboardDocumentListIcon, group: 'Configuración' },
];

const page = usePage();
const badges = computed(() => (page.props.organizationRequestsPending ? { '/admin/organization-requests': page.props.organizationRequestsPending } : {}));
// It. 46a: con un solo Super Administrador activo, todas las pantallas lo avisan.
const onlyOne = computed(() => page.props.superAdministratorsActive === 1);
</script>

<template>
    <Head :title="title" />
    <AppLayout title="Panel global">
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <div v-if="onlyOne" data-test="one-super-admin" role="status" class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 text-base text-amber-900 ring-1 ring-amber-200">
                <p><span aria-hidden="true">⚠️ </span>Solo hay un Super Administrador activo. Si pierde el acceso, nadie podrá dar de alta veedurías ni atender las alertas. Invite a otro.</p>
                <Link
                    href="/admin/super-administrators"
                    class="inline-flex min-h-11 items-center self-start rounded-xl border border-amber-300 bg-white px-3 font-semibold text-amber-900 hover:bg-amber-100"
                >Invitar a otro Super Administrador</Link>
            </div>
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
