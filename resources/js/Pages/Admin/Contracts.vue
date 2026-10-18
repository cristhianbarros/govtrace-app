<script setup>
// US-015: los contratos de obra del territorio, para planear a qué obras
// mandar a los veedores. De 20 en 20, por fecha de firma (lo más reciente
// primero) o por valor; cada orden se invierte con un segundo toque.
// It. 40c: y un buscador, por objeto, contratista o número de proceso.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatCop, formatDate, formatDateTime } from '@/lib/format.js';
import { fetchContracts, fetchContractSync, syncContractsNow } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const OBJECT_PREVIEW = 50;

const sort = ref('signed_at');
const direction = ref('desc');
const page = ref(1);
const words = ref('');
const searched = ref('');
const { data: contracts, loading, error, load } = useLoader(fetchContracts);

const reload = () => load({ sort: sort.value, direction: direction.value, page: page.value, ...(searched.value ? { q: searched.value } : {}) });

function search() {
    searched.value = words.value.trim();
    page.value = 1;
    reload();
}

const emptyText = computed(() =>
    searched.value
        ? `Ningún contrato del territorio coincide con «${searched.value}».`
        : 'Aún no hay contratos de obra sincronizados para su territorio. La actualización desde SECOP II se ejecuta automáticamente cada madrugada.',
);

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

// It. 63 (US-070): la sincronización con el SECOP II de su territorio, sin esperar a la noche. Mientras una
// corre, la pantalla vuelve a mirar sola cada 10 segundos.
const lastSync = ref(null);
const syncNotice = ref(null);
const syncRefused = ref(null);
const syncing = ref(false);
let watching = null;

const lastSyncText = computed(() => {
    const run = lastSync.value;
    if (!run) {
        return 'Aún no se ha sincronizado.';
    }
    if (run.status === 'running') {
        return 'Sincronizando…';
    }
    if (run.status === 'failed') {
        return 'La última sincronización falló. GovTrace la vuelve a intentar sola.';
    }
    const inserted = run.inserted === 1 ? '1 contrato nuevo' : `${run.inserted} contratos nuevos`;
    const updated = run.updated === 1 ? '1 actualizado' : `${run.updated} actualizados`;
    return `Última sincronización: ${formatDateTime(run.finished_at)} · ${inserted} y ${updated}`;
});

async function readSync() {
    try {
        lastSync.value = (await fetchContractSync()) ?? null;
    } catch {
        lastSync.value = null; // sin el estado, la pantalla sirve igual
    }
    if (lastSync.value?.status === 'running' && !watching) {
        watching = setInterval(readSync, 10_000);
    } else if (lastSync.value?.status !== 'running' && watching) {
        clearInterval(watching);
        watching = null;
        reload(); // llegaron contratos: la lista también cambia
    }
}

async function syncNow() {
    syncing.value = true;
    syncNotice.value = null;
    syncRefused.value = null;
    try {
        syncNotice.value = (await syncContractsNow()).message;
        await readSync();
    } catch (failure) {
        syncRefused.value = errorMessage(failure);
    } finally {
        syncing.value = false;
    }
}

onMounted(readSync);
onBeforeUnmount(() => clearInterval(watching));

// It. 40b: "cancelled" es el estado interno de GovTrace para un contrato anulado
// o retirado en SECOP; se nombra como en la tarjeta pública (US-017).
const statusOf = (contract) => (contract.status === 'cancelled' ? 'Anulado/Retirado en SECOP' : contract.status);
</script>

<template>
    <AdminLayout title="Contratos">
        <!-- It. 63 (US-070): sincronizar su territorio con el SECOP II sin esperar a la noche. -->
        <section data-test="sync" aria-labelledby="sync-title" class="flex flex-col gap-2 rounded-2xl bg-white p-4 text-base shadow-soft ring-1 ring-slate-900/5">
            <h2 id="sync-title" class="font-semibold">Sincronización con el SECOP II</h2>
            <p data-test="sync-last" class="text-slate-800">{{ lastSyncText }}</p>
            <p v-if="syncNotice" role="status" class="rounded-lg bg-emerald-50 p-3 font-semibold text-emerald-800">{{ syncNotice }}</p>
            <p v-if="syncRefused" role="alert" class="rounded-lg bg-amber-50 p-3 text-amber-900">{{ syncRefused }}</p>
            <button type="button" :disabled="syncing" class="min-h-11 self-start rounded-xl bg-brand-700 px-4 font-semibold text-white disabled:opacity-40 hover:bg-brand-800" @click="syncNow">Sincronizar ahora</button>
            <p class="text-sm text-slate-700">GovTrace lee los contratos de los datos abiertos del SECOP II en datos.gov.co. Uno que el SECOP aún no publica allí llega cuando lo publique.</p>
        </section>

        <form role="search" class="flex flex-col gap-2 md:flex-row" @submit.prevent="search">
            <label for="contract-words" class="sr-only">Buscar un contrato</label>
            <input
                id="contract-words"
                v-model="words"
                type="search"
                placeholder="Buscar por obra, contratista o número de proceso"
                class="min-h-12 flex-1 rounded-lg border border-slate-300 bg-white px-3 text-base"
            />
            <button type="submit" class="min-h-12 rounded-xl bg-brand-700 px-4 text-base font-semibold text-white hover:bg-brand-800">Buscar</button>
        </form>

        <div class="flex flex-wrap gap-2 text-sm">
            <span class="self-center text-slate-600">Ordenar por:</span>
            <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold" :aria-pressed="sort === 'signed_at'" @click="sortBy('signed_at')">
                Fecha de firma{{ arrow('signed_at') }}
            </button>
            <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold" :aria-pressed="sort === 'value'" @click="sortBy('value')">
                Valor{{ arrow('value') }}
            </button>
        </div>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="contracts?.meta.total === 0"
            loading-text="Cargando contratos…"
            :empty-text="emptyText"
            illustration="records"
            @retry="reload"
        >
            <ul class="flex flex-col gap-2">
                <li v-for="contract in contracts.data" :key="contract.secop_contract_id" data-test="contract-row" class="rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
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
                <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">
                    Anterior
                </button>
                <span class="text-slate-600">Página {{ contracts.meta.current_page }} de {{ contracts.meta.last_page }} · {{ contracts.meta.total }} contratos</span>
                <button
                    type="button"
                    class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40"
                    :disabled="page >= contracts.meta.last_page"
                    @click="goTo(page + 1)"
                >
                    Siguiente
                </button>
            </nav>
        </LoadState>
    </AdminLayout>
</template>
