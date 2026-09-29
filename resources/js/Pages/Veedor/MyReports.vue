<script setup>
// US-010: "Mis Reportes" — cada reporte del veedor con su estado técnico
// (En Cola, Sellando, Sellado) y su estado editorial (En Revisión,
// Publicado, Rechazada con su motivo, Retirado), por separado. Y su Recibo
// de Inmutabilidad (US-023).
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import ReceiptDetails from '@/Components/ReceiptDetails.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format.js';
import { errorMessage } from '@/services/errors.js';
import { fetchMyReports, fetchReceipt } from '@/services/api.js';

const page = usePage();
const { data: reports, loading, error, load } = useLoader(fetchMyReports);

// El recibo abierto de cada reporte: { loading, receipt, error }.
const receipts = ref({});

async function toggleReceipt(report) {
    if (receipts.value[report.id]) {
        delete receipts.value[report.id];
        return;
    }
    receipts.value[report.id] = { loading: true, receipt: null, error: null };
    try {
        receipts.value[report.id] = { loading: false, receipt: await fetchReceipt(report.receipt_url), error: null };
    } catch (failure) {
        receipts.value[report.id] = { loading: false, receipt: null, error: errorMessage(failure) };
    }
}

onMounted(load);
</script>

<template>
    <Head title="Mis Reportes" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <h2 class="mb-3 text-xl font-semibold">Mis Reportes</h2>
        <LoadState
            :loading="loading"
            :error="error"
            :empty="reports !== null && reports.length === 0"
            loading-text="Cargando sus reportes…"
            empty-text="Aún no ha enviado reportes. Los que envíe aparecerán aquí, con su estado."
            @retry="load"
        >
            <ol class="flex flex-col gap-3">
                <li v-for="report in reports" :key="report.id" data-test="report" class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-600">{{ formatDateTime(report.captured_at) }} · {{ report.classification }}</p>
                    <p class="font-semibold">{{ report.worksite }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-xs text-slate-500">Estado técnico</dt><dd data-test="technical" class="font-semibold">{{ report.technical_status }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Estado editorial</dt><dd data-test="editorial" class="font-semibold">{{ report.editorial_status }}</dd></div>
                    </dl>
                    <p v-if="report.rejection_reason" class="mt-2 rounded bg-red-50 p-2 text-sm text-red-800">Motivo: {{ report.rejection_reason }}</p>
                    <button type="button" class="mt-3 min-h-11 rounded-lg border border-slate-300 px-3 text-sm font-semibold" :aria-expanded="Boolean(receipts[report.id])" @click="toggleReceipt(report)">Ver recibo</button>
                    <div v-if="receipts[report.id]" data-test="receipt" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <p v-if="receipts[report.id].loading">Consultando el recibo…</p>
                        <p v-else-if="receipts[report.id].error" role="alert">{{ receipts[report.id].error }}</p>
                        <ReceiptDetails v-else :receipt="receipts[report.id].receipt" />
                    </div>
                </li>
            </ol>
        </LoadState>
        <template #nav>
            <VeedorNav current="/my-reports" />
        </template>
    </AppLayout>
</template>
