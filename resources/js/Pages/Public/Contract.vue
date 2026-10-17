<script setup>
// It. 58 (US-059-LEG): un contrato del territorio que ningún veedor ha
// reportado — sin ficha ni ubicación todavía. El ciudadano le informa a la
// veeduría y, si sabe, le dice dónde queda la obra.
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import CitizenReportForm from '@/Components/Public/CitizenReportForm.vue';
import ContractCard from '@/Components/Public/ContractCard.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import OversightChannels from '@/Components/Public/OversightChannels.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchPublicContract } from '@/services/api.js';

const props = defineProps({
    contractId: { type: String, required: true }, // su número de SECOP II
});

const page = usePage();
const { data: work, loading, error, load } = useLoader(() => fetchPublicContract(props.contractId));

onMounted(load);
</script>

<template>
    <Head :title="work?.name ?? 'Obra'" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo" sections :here="work?.name ?? 'Obra'">
        <OrganizationNotice />
        <LoadState :loading="loading" :error="error" loading-text="Cargando la obra…" empty-text="" @retry="load">
            <template v-if="work">
                <h1 class="mb-3" :class="work.name.length > 70 ? 'text-xl md:text-2xl' : 'text-2xl md:text-3xl'">{{ work.name }}</h1>
                <p data-test="unlocated" class="mb-4 rounded-2xl bg-white p-4 text-base shadow-soft ring-1 ring-slate-900/5">Esta obra aún no está en el mapa: ningún veedor la ha visitado. Si sabe algo de ella, cuénteselo a la veeduría.</p>
                <section aria-label="Contratos" class="flex flex-col gap-3">
                    <ContractCard v-for="contract in work.contracts" :key="contract.secop_contract_id" :contract="contract" />
                </section>

                <CitizenReportForm :contract-id="work.contract_id" :propose-from="work.seat" />
                <OversightChannels />
            </template>
        </LoadState>
    </AppLayout>
</template>
