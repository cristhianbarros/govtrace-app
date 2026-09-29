<script setup>
// US-004: lo que costó sellar, por mes y por organización — las comisiones
// que la red le cobró a la cuenta patrocinadora, en XLM, y su costo
// estimado en pesos con el precio de XLM que se usó y de cuándo es.
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import { formatCop, formatDateTime, formatMonth, formatXlm } from '@/lib/format.js';
import { fetchSealingCosts } from '@/services/api.js';

const { data: report, loading, error, load } = useLoader(fetchSealingCosts);

onMounted(load);

function priceNote(price) {
    if (price === null) {
        return 'Sin precio de XLM: el costo en pesos no se puede estimar todavía.';
    }
    const rate = `1 XLM = ${formatCop(price.cop_per_xlm)}`;
    return price.live
        ? `${rate} · precio de ${formatDateTime(price.quoted_at)}`
        : `El API de precios no respondió: se usa el último precio conocido, ${rate}, del ${formatDateTime(price.quoted_at)}.`;
}
</script>

<template>
    <section class="flex flex-col gap-3">
        <h3 class="text-base font-semibold">Comisiones de sellado</h3>
        <LoadState
            :loading="loading"
            :error="error"
            :empty="report?.rows.length === 0"
            loading-text="Calculando las comisiones…"
            empty-text="Aún no hay sellos en la red de Stellar."
            @retry="load"
        >
            <p data-test="price" class="text-sm" :class="report.price?.live ? 'text-slate-600' : 'rounded-lg bg-amber-50 p-3 text-amber-900'">
                {{ priceNote(report.price) }}
            </p>

            <div class="overflow-x-auto rounded-lg bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-slate-500">
                        <tr>
                            <th class="p-2 font-semibold">Mes y organización</th>
                            <th class="p-2 text-right font-semibold">Sellos</th>
                            <th class="p-2 text-right font-semibold">Comisiones</th>
                            <th class="p-2 text-right font-semibold">Costo estimado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.rows" :key="`${row.month}-${row.organization}`" data-test="cost-row" class="border-t align-top">
                            <td class="p-2">
                                <span class="block text-xs text-slate-500">{{ formatMonth(row.month) }}</span>
                                <span class="block font-semibold">{{ row.organization }}</span>
                                <span v-if="row.without_fee" class="block text-xs text-amber-800">{{ row.without_fee }} sin comisión conocida</span>
                            </td>
                            <td data-test="sealed" class="p-2 text-right">{{ row.sealed }}</td>
                            <td data-test="fee" class="whitespace-nowrap p-2 text-right">{{ formatXlm(row.fee_xlm) }} XLM</td>
                            <td data-test="cost" class="whitespace-nowrap p-2 text-right">{{ formatCop(row.cost_cop) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </LoadState>
    </section>
</template>
