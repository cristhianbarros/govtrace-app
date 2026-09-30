<script setup>
// US-010: "Mis Reportes" — cada reporte del veedor con su estado técnico
// (En Cola, Sellando, Sellado) y su estado editorial (En Revisión,
// Publicado, Rechazada con su motivo, Retirado), por separado. Y su Recibo
// de Inmutabilidad (US-023).
import { Head, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import ReceiptDetails from '@/Components/ReceiptDetails.vue';
import VeedorNav from '@/Components/VeedorNav.vue';
import { useLoader } from '@/composables/useLoader.js';
import { outboxState, startOutboxSync, syncOutbox } from '@/composables/useOutbox.js';
import { registerServiceWorker } from '@/lib/pwa.js';
import { MESSAGES, waitingLabel } from '@/lib/outbox.js';
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

// US-018: al abrir y al volver la señal, la bandeja de salida se sube; lo que
// llegó aparece en la lista.
async function syncAndReload() {
    const result = await syncOutbox();
    if (result?.sent > 0) {
        await load();
    }
}

function onOnline() {
    syncAndReload();
}

onMounted(async () => {
    registerServiceWorker();
    window.addEventListener('online', onOnline);
    await load();
    if ((await startOutboxSync())?.sent > 0) {
        await load();
    }
});

onBeforeUnmount(() => window.removeEventListener('online', onOnline));
</script>

<template>
    <Head title="Mis Reportes" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <h1 class="mb-3 text-xl font-semibold">Mis Reportes</h1>
        <section v-if="outboxState.count > 0 || outboxState.notice || outboxState.rejected.length" aria-label="Bandeja de salida" class="mb-3 flex flex-col gap-2">
            <p v-if="outboxState.count > 0" data-test="outbox" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">{{ waitingLabel(outboxState.count) }}</p>
            <p v-if="outboxState.expiring > 0" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ MESSAGES.expiring }}</p>
            <p v-if="outboxState.notice" role="status" class="rounded-lg bg-slate-100 p-3 text-sm">{{ outboxState.notice }}</p>
            <p v-for="reason in outboxState.rejected" :key="reason" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">El servidor rechazó un reporte pendiente: {{ reason }}</p>
        </section>
        <LoadState
            :loading="loading"
            :error="error"
            :empty="reports !== null && reports.length === 0"
            loading-text="Cargando sus reportes…"
            empty-text="Aún no ha enviado reportes. Los que envíe aparecerán aquí, con su estado."
            @retry="load"
        >
            <ol class="flex flex-col gap-3">
                <li v-for="report in reports" :key="report.id" data-test="report" class="rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-4">
                    <p class="text-sm text-slate-600">{{ formatDateTime(report.captured_at) }} · {{ report.classification }}</p>
                    <p class="font-semibold">{{ report.worksite }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-xs text-slate-600">Sello digital</dt><dd data-test="technical" class="font-semibold">{{ report.technical_status }}</dd></div>
                        <div><dt class="text-xs text-slate-600">Publicación</dt><dd data-test="editorial" class="font-semibold">{{ report.editorial_status }}</dd></div>
                    </dl>
                    <p v-if="report.rejection_reason" class="mt-2 rounded bg-red-50 p-2 text-sm text-red-800">Motivo: {{ report.rejection_reason }}</p>
                    <button type="button" class="mt-3 min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 text-sm font-semibold" :aria-expanded="Boolean(receipts[report.id])" @click="toggleReceipt(report)">Ver recibo</button>
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
