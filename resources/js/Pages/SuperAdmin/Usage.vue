<script setup>
// US-053-RPT: el uso de cada organización — veedores activos y evidencias
// recibidas, publicadas, rechazadas y retiradas —, con su última actividad
// (US-054-RPT: 30 días sin recibir ni publicar evidencias es una alerta).
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { formatDateTime } from '@/lib/format.js';
import { fetchUsage } from '@/services/api.js';

const { data: rows, loading, error, load } = useLoader(fetchUsage);

const FIGURES = [
    { key: 'active_observers', label: 'Veedores activos' },
    { key: 'received', label: 'Recibidas' },
    { key: 'published', label: 'Publicadas' },
    { key: 'rejected', label: 'Rechazadas' },
    { key: 'withdrawn', label: 'Retiradas' },
];

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Uso">
        <LoadState
            :loading="loading"
            :error="error"
            :empty="rows?.length === 0"
            loading-text="Cargando el resumen de uso…"
            empty-text="Aún no hay organizaciones registradas."
            @retry="load"
        >
            <ul class="flex flex-col gap-2">
                <li v-for="row in rows" :key="row.organization" data-test="usage-row" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ row.organization }}</p>
                        <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ row.status }}</span>
                    </div>
                    <dl class="grid grid-cols-3 gap-2 text-center sm:grid-cols-5">
                        <div v-for="figure in FIGURES" :key="figure.key" class="rounded bg-slate-50 p-2">
                            <dd :data-test="figure.key" class="text-lg font-semibold">{{ row[figure.key] }}</dd>
                            <dt class="text-xs text-slate-600">{{ figure.label }}</dt>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-600">
                        {{ row.last_activity_at ? `Última actividad: ${formatDateTime(row.last_activity_at)}` : 'Sin actividad todavía' }}
                    </p>
                </li>
            </ul>
        </LoadState>
    </SuperAdminLayout>
</template>
