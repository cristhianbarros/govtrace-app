<script setup>
// It. 43g (V7): el Super Administrador reporta en nombre de una organización
// que lo autorizó (US-042-SEC, R-SA-02). Es el mismo reporte del veedor, con
// las mismas reglas (la geocerca, el GPS, los adjuntos y sus hashes); el
// servidor lo firma a nombre de la organización y lo deja en el registro de
// auditoría. Sin autorización vigente, la pantalla lo dice y no ofrece nada.
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContractSearch from '@/Components/ContractSearch.vue';
import ReportForm from '@/Components/ReportForm.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { formatDay } from '@/lib/format.js';
import { reportFormData } from '@/lib/report.js';
import { searchContractsOf, sendReportOnBehalf } from '@/services/api.js';
import { errorMessages } from '@/services/errors.js';

const props = defineProps({
    organization: { type: Object, required: true }, // { id, name }
    authorizedUntil: { type: String, default: null },
    refusal: { type: String, default: null },
});

const search = searchContractsOf(props.organization.id);
const contract = ref(null);
const sending = ref(false);
const serverErrors = ref([]);
const sent = ref(false);
const locationPending = ref(null); // it. 46f: por qué la ubicación de la obra quedó por confirmar

function choose(selected) {
    contract.value = selected;
    sent.value = false;
    locationPending.value = null;
}

async function submit(report) {
    sending.value = true;
    serverErrors.value = [];
    try {
        const answer = await sendReportOnBehalf(props.organization.id, reportFormData(report));
        contract.value = null;
        sent.value = true;
        locationPending.value = answer?.location_pending ?? null;
    } catch (error) {
        serverErrors.value = errorMessages(error);
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <SuperAdminLayout :title="`Reportar en nombre de ${organization.name}`">
        <template v-if="authorizedUntil">
            <p class="rounded-lg bg-brand-50 p-3 text-base text-brand-900">
                {{ organization.name }} lo autorizó a reportar en su nombre hasta el {{ formatDay(authorizedUntil) }}.
                El reporte queda a nombre de la organización y en el registro de auditoría. La veeduría lo revisa antes de publicarlo, como los demás.
            </p>

            <p v-if="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-base font-semibold text-emerald-800">
                Reporte recibido en nombre de {{ organization.name }}. Se sella como los demás, y la veeduría lo revisa antes de publicarlo.
            </p>
            <p v-if="locationPending" data-test="location-pending" class="rounded-lg bg-amber-50 p-3 text-base font-semibold text-amber-900 ring-1 ring-amber-200">{{ locationPending }}</p>

            <ContractSearch v-if="!contract" :search="search" @select="choose" />
            <ReportForm v-else :contract="contract" :sending="sending" :server-errors="serverErrors" @submit="submit" @change-worksite="contract = null" />
        </template>

        <template v-else>
            <p role="alert" class="rounded-lg bg-amber-50 p-3 text-base text-amber-900">{{ refusal }}</p>
            <Link href="/admin/organizations" class="inline-flex min-h-11 items-center self-start text-base font-semibold text-slate-700 underline">Volver a las organizaciones</Link>
        </template>
    </SuperAdminLayout>
</template>
