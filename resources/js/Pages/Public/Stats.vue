<script setup>
// US-051-RPT: las estadísticas públicas del territorio, sin sesión — obras
// en riesgo, evidencias publicadas por mes y contratos anulados con
// evidencias. US-052-RPT: los datos abiertos, para auditarlos sin GovTrace.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMonth } from '@/lib/format.js';
import { fetchPublicStats } from '@/services/api.js';

const page = usePage();
const { data: stats, loading, error, load } = useLoader(fetchPublicStats);

onMounted(load);
</script>

<template>
    <Head title="Estadísticas del territorio" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <div class="flex flex-col gap-4">
            <OrganizationNotice />
            <Link href="/" class="self-start text-sm font-semibold text-slate-700 underline">Volver al mapa</Link>
            <h1 class="text-xl font-semibold">Estadísticas del territorio</h1>

            <LoadState :loading="loading" :error="error" loading-text="Cargando las estadísticas…" empty-text="" @retry="load">
                <template v-if="stats">
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <p data-test="at-risk" class="flex flex-col rounded-lg bg-white p-3">
                            <span class="text-3xl font-semibold text-red-700">{{ stats.worksites_at_risk }}</span>
                            <span class="text-xs text-slate-600">Obras en riesgo</span>
                        </p>
                        <p data-test="cancelled" class="flex flex-col rounded-lg bg-white p-3">
                            <span class="text-3xl font-semibold">{{ stats.cancelled_contracts_with_evidence }}</span>
                            <span class="text-xs text-slate-600">Contratos anulados con evidencias</span>
                        </p>
                    </div>

                    <section aria-labelledby="by-month" class="rounded-lg bg-white p-3">
                        <h3 id="by-month" class="mb-2 text-sm font-semibold text-slate-700">Evidencias publicadas por mes</h3>
                        <p v-if="stats.published_by_month.length === 0" class="text-sm text-slate-600">Aún no hay evidencias publicadas en este territorio.</p>
                        <ul v-else class="flex flex-col divide-y divide-slate-100 text-sm">
                            <li v-for="row in stats.published_by_month" :key="row.month" data-test="month" class="flex justify-between py-2">
                                <span>{{ formatMonth(row.month) }}</span>
                                <span class="font-semibold">{{ row.total }}</span>
                            </li>
                        </ul>
                    </section>
                </template>
            </LoadState>

            <section aria-labelledby="open-data" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
                <h3 id="open-data" class="font-semibold text-slate-700">Datos abiertos</h3>
                <p class="text-slate-600">
                    Las evidencias publicadas y sus sellos en la red Stellar, para auditarlas por su cuenta. Las ubicaciones van aproximadas y cada veedor, con un seudónimo.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="/open-data.csv" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 font-semibold">Descargar para Excel (CSV)</a>
                    <a href="/open-data.json" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 font-semibold">Datos para programadores (JSON)</a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
