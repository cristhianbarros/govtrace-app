<script setup>
// US-025: el sello de una evidencia en la red Stellar — su Recibo de
// Inmutabilidad público —, con el camino a un explorador independiente.
// La it. 27 le suma el validador: comprobar el archivo en el navegador.
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import { formatDateTime } from '@/lib/format.js';
import { fetchReceipt } from '@/services/api.js';

const props = defineProps({
    receiptUrl: { type: String, required: true },
});

const { data: receipt, loading, error, load } = useLoader(() => fetchReceipt(props.receiptUrl));

onMounted(load);
</script>

<template>
    <div data-test="seal" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
        <LoadState :loading="loading" :error="error" loading-text="Consultando el sello…" empty-text="" @retry="load">
            <p v-if="receipt && !receipt.sealed">{{ receipt.message }}</p>
            <dl v-else-if="receipt" class="flex flex-col gap-2">
                <div><dt class="text-xs text-slate-500">Raíz de Merkle</dt><dd class="break-all font-mono text-xs">{{ receipt.merkle_root }}</dd></div>
                <div><dt class="text-xs text-slate-500">Transacción</dt><dd class="break-all font-mono text-xs">{{ receipt.tx_hash ?? '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Ledger</dt><dd>{{ receipt.ledger }}</dd></div>
                <div><dt class="text-xs text-slate-500">Sellado el</dt><dd>{{ formatDateTime(receipt.sealed_at) }}</dd></div>
                <a
                    v-if="receipt.explorer"
                    :href="receipt.explorer.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center self-start rounded-lg border border-slate-300 bg-white px-3 font-semibold"
                >{{ receipt.explorer.label }}</a>
            </dl>
        </LoadState>
    </div>
</template>
