<script setup>
// US-049-RPT: el estado de la veeduría de un vistazo — obras por color (las
// del mapa público), evidencias por clasificación y por mes, y veedores
// activos. Solo datos de la organización.
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatMonth } from '@/lib/format.js';
import { fetchSummary } from '@/services/api.js';

const { data: summary, loading, error, load } = useLoader(fetchSummary);

const COLORS = [
    { key: 'green', label: 'Normal', css: 'bg-green-600' },
    { key: 'yellow', label: 'Alerta', css: 'bg-yellow-400' },
    { key: 'red', label: 'En riesgo', css: 'bg-red-600' },
];
const CLASSIFICATIONS = ['Avance', 'Retraso', 'Abandono'];

onMounted(load);
</script>

<template>
    <AdminLayout title="Resumen del territorio">
        <!-- US-050-RPT: una descarga del navegador, con su sesión: no pasa por el API en JSON. -->
        <a href="/export.csv" class="inline-flex min-h-11 items-center self-start rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 bg-white px-3 text-sm font-semibold">Exportar obras y evidencias (CSV)</a>
        <LoadState :loading="loading" :error="error" loading-text="Cargando el resumen…" empty-text="" @retry="load">
            <template v-if="summary">
                <section aria-labelledby="worksites" class="rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
                    <h3 id="worksites" class="mb-2 text-sm font-semibold text-slate-700">Obras en el mapa</h3>
                    <ul class="grid grid-cols-3 gap-2 text-center">
                        <li v-for="color in COLORS" :key="color.key" :data-test="color.key" class="rounded-lg bg-slate-50 p-2">
                            <span class="mx-auto mb-1 block size-3 rounded-full" :class="color.css" aria-hidden="true"></span>
                            <span class="block text-2xl font-semibold">{{ summary.worksites_by_color[color.key] }}</span>
                            <span class="text-xs text-slate-600">{{ color.label }}</span>
                        </li>
                    </ul>
                </section>

                <section aria-labelledby="evidences" class="rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
                    <h3 id="evidences" class="mb-2 text-sm font-semibold text-slate-700">Evidencias recibidas</h3>
                    <ul class="grid grid-cols-3 gap-2 text-center">
                        <li v-for="classification in CLASSIFICATIONS" :key="classification" :data-test="classification" class="rounded-lg bg-slate-50 p-2">
                            <span class="block text-2xl font-semibold">{{ summary.evidences_by_classification[classification] }}</span>
                            <span class="text-xs text-slate-600">{{ classification }}</span>
                        </li>
                    </ul>
                    <p v-if="summary.evidences_by_month.length === 0" class="mt-3 text-sm text-slate-600">Aún no hay evidencias en el territorio.</p>
                    <div v-else class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">Evidencias por mes y clasificación</caption>
                            <thead>
                                <tr class="text-left text-xs text-slate-500">
                                    <th scope="col" class="py-1">Mes</th>
                                    <th v-for="classification in CLASSIFICATIONS" :key="classification" scope="col" class="py-1 text-right">{{ classification }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="month in summary.evidences_by_month" :key="month.month" class="border-t border-slate-100">
                                    <td class="py-1">{{ formatMonth(month.month) }}</td>
                                    <td v-for="classification in CLASSIFICATIONS" :key="classification" class="py-1 text-right">{{ month[classification] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <p data-test="observers" class="rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5"><span class="text-2xl font-semibold">{{ summary.active_observers }}</span> veedores activos</p>
            </template>
        </LoadState>
    </AdminLayout>
</template>
