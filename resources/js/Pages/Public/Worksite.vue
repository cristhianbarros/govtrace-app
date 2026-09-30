<script setup>
// US-029 / US-017: la vista pública de una obra — sus contratos y la línea
// de tiempo de sus evidencias publicadas, pedidas al abrirla (R-MAP-02).
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import CitizenReportForm from '@/Components/Public/CitizenReportForm.vue';
import ContractCard from '@/Components/Public/ContractCard.vue';
import EvidenceCard from '@/Components/Public/EvidenceCard.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import OversightChannels from '@/Components/Public/OversightChannels.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchWorksite } from '@/services/api.js';

const props = defineProps({
    worksiteId: { type: Number, required: true },
});

const page = usePage();
const CONDITION = {
    green: { sign: '✓', css: 'bg-green-700 text-white', border: 'border-green-700' },
    yellow: { sign: '!', css: 'bg-yellow-400 text-slate-900', border: 'border-yellow-500' },
    red: { sign: '✕', css: 'bg-red-700 text-white', border: 'border-red-700' },
};
const { data: worksite, loading, error, load } = useLoader(() => fetchWorksite(props.worksiteId));

onMounted(load);
</script>

<template>
    <Head :title="worksite?.name ?? 'Obra'" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <Link href="/" class="mb-3 inline-flex min-h-11 items-center text-base font-semibold text-slate-700">← Volver al mapa</Link>
        <OrganizationNotice />
        <LoadState :loading="loading" :error="error" loading-text="Cargando la obra…" empty-text="" @retry="load">
            <template v-if="worksite">
                <h1 class="mb-3 text-xl font-semibold">{{ worksite.name }}</h1>
                <!-- It. 40b: el estado de la obra (el color de su pin) y por qué, en palabras. -->
                <section
                    v-if="worksite.condition"
                    data-test="condition"
                    :data-color="worksite.condition.color"
                    aria-label="Estado de la obra"
                    class="mb-4 flex items-start gap-3 rounded-lg border-2 bg-white p-3"
                    :class="CONDITION[worksite.condition.color].border"
                >
                    <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-full text-lg font-bold" :class="CONDITION[worksite.condition.color].css">{{ CONDITION[worksite.condition.color].sign }}</span>
                    <div class="text-base">
                        <p>
                            <span class="block text-lg font-semibold">{{ worksite.condition.label }}</span>
                            {{ worksite.condition.reason }}
                        </p>
                        <!-- It. 44a (R-LEG-01): una alerta, no la decisión de una autoridad ni una "obra inconclusa". -->
                        <p class="mt-2 text-sm text-slate-700">Es una alerta de GovTrace, calculada con los datos de SECOP II y las evidencias publicadas. No es la decisión de una autoridad.</p>
                        <template v-if="worksite.condition.color === 'red'">
                            <p class="mt-2 text-sm text-slate-700">«En riesgo» no es lo mismo que «obra inconclusa». Para la ley, una obra es inconclusa cuando, un año después de vencido el plazo para liquidar su contrato, no se terminó o no presta el servicio (Ley 2020 de 2020).</p>
                            <a href="#contraloria" class="mt-1 inline-flex min-h-11 items-center text-base font-semibold underline">¿Qué puede hacer?</a>
                        </template>
                    </div>
                </section>
                <section aria-label="Contratos" class="flex flex-col gap-3">
                    <ContractCard v-for="contract in worksite.contracts" :key="contract.secop_contract_id" :contract="contract" />
                </section>

                <h2 class="mb-3 mt-6 text-lg font-semibold">Evidencias publicadas</h2>
                <p v-if="worksite.timeline.length === 0" class="rounded-lg bg-white p-4 text-sm text-slate-600">Aún no hay evidencias publicadas de esta obra.</p>
                <ol v-else class="flex flex-col gap-3">
                    <li v-for="evidence in worksite.timeline" :key="evidence.report_id">
                        <EvidenceCard :evidence="evidence" />
                    </li>
                </ol>

                <!-- It. 44f (US-059-LEG): primero, a la veeduría que la vigila; después, a la Contraloría. -->
                <CitizenReportForm :worksite-id="worksite.id" />
                <OversightChannels />
            </template>
        </LoadState>
    </AppLayout>
</template>
