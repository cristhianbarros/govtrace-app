<script setup>
// US-023 / US-025: lo que dice un Recibo de Inmutabilidad — la raíz, la
// transacción, el ledger y su hora, y el camino a Stellar Expert —, o el
// mensaje de que el sellado sigue en curso.
import { formatDateTime } from '@/lib/format.js';

defineProps({
    receipt: { type: Object, required: true },
});
</script>

<template>
    <p v-if="!receipt.sealed">{{ receipt.message }}</p>
    <dl v-else class="flex flex-col gap-2">
        <div><dt class="text-xs text-slate-500">Raíz de Merkle</dt><dd class="break-all font-mono text-xs">{{ receipt.merkle_root }}</dd></div>
        <div><dt class="text-xs text-slate-500">Transacción</dt><dd class="break-all font-mono text-xs">{{ receipt.tx_hash ?? '—' }}</dd></div>
        <div><dt class="text-xs text-slate-500">Ledger</dt><dd>{{ receipt.ledger }}</dd></div>
        <div><dt class="text-xs text-slate-500">Sellado el</dt><dd>{{ formatDateTime(receipt.sealed_at) }}</dd></div>
        <a
            v-if="receipt.explorer"
            :href="receipt.explorer.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 items-center self-start rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 bg-white px-3 font-semibold"
        >{{ receipt.explorer.label }}</a>
    </dl>
</template>
