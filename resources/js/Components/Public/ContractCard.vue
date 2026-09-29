<script setup>
// US-017: los datos clave de un contrato, tal como vienen de SECOP II
// (R-SEC-01): entidad, contratista, valor y plazo, con el original a un toque.
import { formatCop } from '@/lib/format.js';

defineProps({
    contract: { type: Object, required: true },
});

const term = (months) => (months === 1 ? '1 mes' : `${months} meses`);
</script>

<template>
    <article data-test="contract" class="rounded-lg border border-slate-200 bg-white p-4">
        <p v-if="contract.cancelled" class="mb-3 rounded bg-amber-100 px-2 py-1 text-sm font-semibold text-amber-900">
            {{ contract.cancelled_notice }}
        </p>
        <p class="text-xs text-slate-500">{{ contract.secop_contract_id }}</p>
        <h3 class="font-semibold">{{ contract.object }}</h3>
        <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-slate-500">Entidad</dt><dd>{{ contract.entity_name }}</dd></div>
            <div><dt class="text-xs text-slate-500">Contratista</dt><dd>{{ contract.contractor_name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-500">Valor</dt><dd>{{ formatCop(contract.value) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Plazo</dt><dd>{{ contract.term_months === null ? '—' : term(contract.term_months) }}</dd></div>
        </dl>
        <a
            v-if="contract.secop_url"
            :href="contract.secop_url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold"
        >Ver original en SECOP</a>
    </article>
</template>
