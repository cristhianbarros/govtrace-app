<script setup>
// US-027: el mapa público de la organización (R-MAP-01), sin sesión. Carga
// solo los pines (R-MAP-02); tocar uno abre la vista de su obra, que pide
// sus datos en ese momento.
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import PinsMap from '@/Components/Public/PinsMap.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchPins } from '@/services/api.js';

const page = usePage();
const { data: pins, loading, error, load } = useLoader(fetchPins);

const LEGEND = [
    { css: 'bg-green-600', label: 'Normal' },
    { css: 'bg-yellow-400', label: 'Alerta' },
    { css: 'bg-red-600', label: 'En riesgo' },
];

onMounted(load);
</script>

<template>
    <Head title="Mapa de obras" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <OrganizationNotice />
        <LoadState
            :loading="loading"
            :error="error"
            :empty="pins !== null && pins.length === 0"
            loading-text="Cargando obras…"
            empty-text="No se encontraron obras o evidencias que coincidan con estos filtros en este territorio."
            @retry="load"
        >
            <PinsMap :pins="pins ?? []" @select="(id) => router.visit(`/worksite/${id}`)" />
            <ul class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700" aria-label="Qué significa cada color">
                <li v-for="item in LEGEND" :key="item.label" class="flex items-center gap-2">
                    <span class="size-3 rounded-full" :class="item.css" aria-hidden="true"></span>{{ item.label }}
                </li>
            </ul>
        </LoadState>
    </AppLayout>
</template>
