<script setup>
// US-015: los contratos de obra del territorio, para planear a qué obras
// mandar a los veedores. De 20 en 20, por fecha de firma (lo más reciente
// primero) o por valor; cada orden se invierte con un segundo toque.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatCop, formatDate } from '@/lib/format.js';
import { fetchContracts } from '@/services/api.js';

const OBJECT_PREVIEW = 50;

const sort = ref('signed_at');
const direction = ref('desc');
const page = ref(1);
const { data: contracts, loading, error, load } = useLoader(fetchContracts);

const reload = () => load({ sort: sort.value, direction: direction.value, page: page.value });

function sortBy(field) {
    direction.value = sort.value === field && direction.value === 'desc' ? 'asc' : 'desc';
    sort.value = field;
    page.value = 1;
    reload();
}

function goTo(next) {
    page.value = next;
    reload();
}

const preview = (object) => (object && object.length > OBJECT_PREVIEW ? `${object.slice(0, OBJECT_PREVIEW)}…` : object);
const arrow = (field) => (sort.value !== field ? '' : direction.value === 'desc' ? ' ↓' : ' ↑');

onMounted(reload);

// It. 40b: "cancelled" es el estado interno de GovTrace para un contrato anulado
// o retirado en SECOP; se nombra como en la tarjeta pública (US-017).
const statusOf = (contract) => (contract.status === 'cancelled' ? 'Anulado/Retirado en SECOP' : contract.status);
</script>

<template>
    <AdminLayout title="Contratos">
        <div class="flex gap-2 text-sm">
            <span class="self-center text-slate-600">Ordenar por:</span>
            <button type="button" class="rounded-lg border bg-white px-3 py-2 font-semibold" :aria-pressed="sort === 'signed_at'" @click="sortBy('signed_at')">
                Fecha de firma{{ arrow('signed_at') }}
            </button>
            <button type="button" class="rounded-lg border bg-white px-3 py-2 font-semibold" :aria-pressed="sort === 'value'" @click="sortBy('value')">
                Valor{{ arrow('value') }}
            </button>
        </div>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="contracts?.meta.total === 0"
            loading-text="Cargando contratos…"
            empty-text="Aún no hay contratos de obra sincronizados para su territorio. La actualización desde SECOP II se ejecuta automáticamente cada madrugada."
            @retry="reload"
        >
            <ul class="flex flex-col gap-2">
                <li v-for="contract in contracts.data" :key="contract.secop_contract_id" data-test="contract-row" class="rounded-lg bg-white p-3 text-sm">
                    <p class="font-semibold" data-test="contract-object" :title="contract.object">{{ preview(contract.object) }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 md:grid-cols-5">
                        <div><dt class="text-xs text-slate-500">Número de proceso</dt><dd>{{ contract.process_number ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Contratista</dt><dd>{{ contract.contractor_name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Valor total</dt><dd>{{ formatCop(contract.value) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Estado</dt><dd>{{ statusOf(contract) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Fecha de firma</dt><dd>{{ formatDate(contract.signed_at) }}</dd></div>
                    </dl>
                </li>
            </ul>

            <nav class="flex items-center justify-between gap-2 text-sm" aria-label="Páginas">
                <button type="button" class="rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">
                    Anterior
                </button>
                <span class="text-slate-600">Página {{ contracts.meta.current_page }} de {{ contracts.meta.last_page }} · {{ contracts.meta.total }} contratos</span>
                <button
                    type="button"
                    class="rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40"
                    :disabled="page >= contracts.meta.last_page"
                    @click="goTo(page + 1)"
                >
                    Siguiente
                </button>
            </nav>
        </LoadState>
    </AdminLayout>
</template>
