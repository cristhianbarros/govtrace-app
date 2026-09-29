<script setup>
// La pantalla "Sellado" del panel global: la cuenta patrocinadora y la
// vigencia del contrato, leídas en vivo de Stellar (US-022); las fallas de
// sellado, para volver a encolarlas (US-047-MNT); y las comisiones (US-004).
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import SealingCosts from '@/Components/SuperAdmin/SealingCosts.vue';
import SealingFailures from '@/Components/SuperAdmin/SealingFailures.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { formatDate, formatXlm } from '@/lib/format.js';
import { fetchSealing } from '@/services/api.js';

// D12: con menos de esto, la tesorería debe extender la vigencia (el mismo aviso de CheckContractLifetime).
const WARN_UNDER_DAYS = 30;

const { data: sealing, loading, error, load } = useLoader(fetchSealing);

onMounted(load);

// Tras re-encolar, la lista se actualiza sin volver a "Consultando…": el aviso de lo hecho sigue a la vista.
async function refresh() {
    try {
        sealing.value = await fetchSealing();
    } catch {
        // Queda la lista anterior; "Reintentar" no hace falta para algo que ya se hizo.
    }
}

const lifetimes = (contract) => [
    { key: 'instance', label: 'Instancia', ...contract.instance },
    { key: 'code', label: 'Código', ...contract.code },
];
</script>

<template>
    <SuperAdminLayout title="Sellado">
        <LoadState :loading="loading" :error="error" loading-text="Consultando el estado del sellado…" empty-text="" @retry="load">
            <p v-if="sealing.network_error" data-test="network-error" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                {{ sealing.network_error }}
            </p>

            <section v-if="sealing.sponsor" data-test="sponsor" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
                <h3 class="text-base font-semibold">Cuenta patrocinadora</h3>
                <p class="text-lg font-semibold" :class="sealing.sponsor.low ? 'text-red-700' : 'text-slate-900'">Saldo: {{ formatXlm(sealing.sponsor.balance_xlm) }} XLM</p>
                <p class="text-slate-600">Umbral de alerta: {{ formatXlm(sealing.sponsor.threshold_xlm) }} XLM</p>
                <p v-if="sealing.sponsor.low" data-test="low-balance" class="rounded bg-red-50 p-2 font-semibold text-red-800">
                    Saldo bajo el umbral: recargue la cuenta desde la tesorería para no detener el sellado.
                </p>
                <p class="break-all font-mono text-xs text-slate-600">{{ sealing.sponsor.address }}</p>
            </section>

            <section v-if="sealing.contract" data-test="contract" class="flex flex-col gap-1 rounded-lg bg-white p-3 text-sm">
                <h3 class="text-base font-semibold">Vigencia del contrato</h3>
                <p
                    v-for="entry in lifetimes(sealing.contract)"
                    :key="entry.key"
                    :data-test="`lifetime-${entry.key}`"
                    :class="entry.days < WARN_UNDER_DAYS ? 'font-semibold text-red-700' : 'text-slate-700'"
                >
                    {{ entry.label }}: vence en {{ entry.days }} días ({{ formatDate(entry.expires_on) }})
                </p>
                <p class="text-xs text-slate-500">La extiende la cuenta de tesorería antes de que venza.</p>
                <p class="break-all font-mono text-xs text-slate-600">{{ sealing.contract.id }}</p>
            </section>

            <SealingFailures :failures="sealing.failures" @requeued="refresh" />
        </LoadState>

        <SealingCosts />
    </SuperAdminLayout>
</template>
