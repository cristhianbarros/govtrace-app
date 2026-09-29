<script setup>
// US-029 / US-017: la vista pública de una obra — sus contratos y la línea
// de tiempo de sus evidencias publicadas, pedidas al abrirla (R-MAP-02).
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import ContractCard from '@/Components/Public/ContractCard.vue';
import EvidenceCard from '@/Components/Public/EvidenceCard.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchWorksite } from '@/services/api.js';

const props = defineProps({
    worksiteId: { type: Number, required: true },
});

const page = usePage();
const { data: worksite, loading, error, load } = useLoader(() => fetchWorksite(props.worksiteId));

onMounted(load);
</script>

<template>
    <Head :title="worksite?.name ?? 'Obra'" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <Link href="/" class="mb-3 inline-flex min-h-11 items-center text-sm font-semibold text-slate-700">← Volver al mapa</Link>
        <OrganizationNotice />
        <LoadState :loading="loading" :error="error" loading-text="Cargando la obra…" empty-text="" @retry="load">
            <template v-if="worksite">
                <h2 class="mb-3 text-xl font-semibold">{{ worksite.name }}</h2>
                <section aria-label="Contratos" class="flex flex-col gap-3">
                    <ContractCard v-for="contract in worksite.contracts" :key="contract.secop_contract_id" :contract="contract" />
                </section>

                <h3 class="mb-3 mt-6 text-lg font-semibold">Evidencias publicadas</h3>
                <p v-if="worksite.timeline.length === 0" class="rounded-lg bg-white p-4 text-sm text-slate-600">Aún no hay evidencias publicadas de esta obra.</p>
                <ol v-else class="flex flex-col gap-3">
                    <li v-for="evidence in worksite.timeline" :key="evidence.report_id">
                        <EvidenceCard :evidence="evidence" />
                    </li>
                </ol>
            </template>
        </LoadState>
    </AppLayout>
</template>
