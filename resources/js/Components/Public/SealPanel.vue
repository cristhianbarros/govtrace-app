<script setup>
// US-025: el sello de una evidencia en la red Stellar — su Recibo de
// Inmutabilidad público —, con el camino a un explorador independiente. Y el
// modo contextual del validador (US-024): comparar una copia del archivo
// con ESTA evidencia, en el navegador.
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import ReceiptDetails from '@/Components/ReceiptDetails.vue';
import FileDrop from '@/Components/Public/FileDrop.vue';
import VerdictBanner from '@/Components/Public/VerdictBanner.vue';
import { useLoader } from '@/composables/useLoader.js';
import { validate } from '@/lib/validator.js';
import { fetchReceipt, findProof } from '@/services/api.js';

const props = defineProps({
    receiptUrl: { type: String, required: true },
    reportId: { type: Number, required: true },
});

const page = usePage();
const { data: receipt, loading, error, load } = useLoader(() => fetchReceipt(props.receiptUrl));
const copy = ref(null);
const verifying = ref(false);
const result = ref(null);

async function compare(file) {
    copy.value = file;
    verifying.value = true;
    result.value = null;
    try {
        result.value = await validate({ file, mode: 'contextual', reportId: props.reportId, stellar: page.props.stellar, findProof });
    } finally {
        verifying.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div data-test="seal" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
        <LoadState :loading="loading" :error="error" loading-text="Consultando el sello…" empty-text="" @retry="load">
            <ReceiptDetails v-if="receipt" :receipt="receipt" />
        </LoadState>

        <div class="mt-3">
            <FileDrop label="¿Tiene una copia? Arrástrela aquí para compararla con esta evidencia" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" :chosen="copy" @choose="compare" />
            <p v-if="verifying" role="status" class="mt-2 text-sm text-slate-600">Verificando en su navegador…</p>
            <VerdictBanner v-if="result" :result="result" />
        </div>
    </div>
</template>
