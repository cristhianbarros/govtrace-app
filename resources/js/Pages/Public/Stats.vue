<script setup>
// US-051-RPT: las estadísticas públicas del territorio, sin sesión — obras
// en riesgo, evidencias publicadas por mes y contratos anulados con
// evidencias. US-052-RPT: los datos abiertos, para auditarlos sin GovTrace.
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import Illustration from '@/Components/Brand/Illustration.vue';
import LoadState from '@/Components/LoadState.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import PageHero from '@/Components/Public/PageHero.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMonth } from '@/lib/format.js';
import { fetchPublicStats } from '@/services/api.js';

const page = usePage();
const { data: stats, loading, error, load } = useLoader(fetchPublicStats);

// It. 40f: cada mes, una barra tan larga como su parte del mes con más evidencias.
const busiest = computed(() => Math.max(1, ...(stats.value?.published_by_month ?? []).map((row) => row.total)));
const share = (row) => `${Math.round((row.total / busiest.value) * 100)}%`;

onMounted(load);
</script>

<template>
    <Head title="Estadísticas del territorio" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo" sections>
        <div class="flex flex-col gap-4">
            <OrganizationNotice />
            <PageHero title="Estadísticas del territorio" illustration="stats">Cómo van las obras del territorio, según los contratos de SECOP II y las evidencias que publica la veeduría.</PageHero>

            <LoadState :loading="loading" :error="error" loading-text="Cargando las estadísticas…" empty-text="" @retry="load">
                <template v-if="stats">
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <p data-test="at-risk" class="flex flex-col rounded-2xl border-t-8 border-red-700 bg-white p-4 shadow-soft">
                            <span class="font-display text-4xl font-semibold text-red-700">{{ stats.worksites_at_risk }}</span>
                            <span class="text-sm text-slate-700">Obras en riesgo</span>
                        </p>
                        <p data-test="cancelled" class="flex flex-col rounded-2xl border-t-8 border-warm-500 bg-white p-4 shadow-soft">
                            <span class="font-display text-4xl font-semibold text-warm-700">{{ stats.cancelled_contracts_with_evidence }}</span>
                            <span class="text-sm text-slate-700">Contratos anulados con evidencias</span>
                        </p>
                    </div>
                    <!-- It. 44a (R-LEG-01). -->
                    <p class="text-sm text-slate-700">Las obras en riesgo son alertas de GovTrace, no obras inconclusas en el sentido de la Ley 2020 de 2020.</p>

                    <section aria-labelledby="by-month" class="rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5">
                        <h2 id="by-month" class="mb-3 text-lg">Evidencias publicadas por mes</h2>
                        <div v-if="stats.published_by_month.length === 0" data-test="empty" class="flex flex-col items-center gap-2 py-2 text-center">
                            <Illustration name="evidence" size="w-28" />
                            <p class="text-base text-slate-700">Aún no hay evidencias publicadas en este territorio.</p>
                        </div>
                        <ul v-else class="flex flex-col gap-3 text-base">
                            <li v-for="row in stats.published_by_month" :key="row.month" data-test="month" class="flex flex-col gap-1">
                                <div class="flex justify-between">
                                    <span>{{ formatMonth(row.month) }}</span>
                                    <span class="font-semibold">{{ row.total }}</span>
                                </div>
                                <div aria-hidden="true" class="h-3 overflow-hidden rounded-full bg-brand-50">
                                    <div data-test="bar" class="h-full rounded-full bg-linear-to-r from-brand-600 to-brand-700" :style="{ width: share(row) }"></div>
                                </div>
                            </li>
                        </ul>
                    </section>
                </template>
            </LoadState>

            <section aria-labelledby="open-data" class="flex flex-col gap-2 rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                <h2 id="open-data" class="text-lg">Datos abiertos</h2>
                <p class="text-slate-600">
                    Las evidencias publicadas y sus sellos en la red Stellar, para auditarlas por su cuenta. Las ubicaciones van aproximadas y cada veedor, con un seudónimo.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="/open-data.csv" class="inline-flex min-h-11 items-center rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 font-semibold">Descargar para Excel (CSV)</a>
                    <a href="/open-data.json" class="inline-flex min-h-11 items-center rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 font-semibold">Datos para programadores (JSON)</a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
