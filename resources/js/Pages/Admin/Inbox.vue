<script setup>
// US-036 / US-037: la bandeja de entrada. "Por revisar": las evidencias
// selladas y ocultas, para publicarlas o rechazarlas de a una (no hay
// publicación masiva). "Publicadas": para retirarlas dejando una lápida.
// It. 52: de 20 en 20 ("Ver 20 más"), con filtros por obra, veedor, clasificación
// y hora de captura sospechosa que se aplican en el servidor, y con lo que hay
// en cada pestaña. Nunca se cargan todas de una vez.
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import EvidenceCard from '@/Components/Admin/EvidenceCard.vue';
import LoadState from '@/Components/LoadState.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fetchInbox } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const tabs = [
    { status: 'hidden', label: 'Por revisar', empty: 'No hay evidencias por revisar.', illustration: 'inbox-done' },
    { status: 'published', label: 'Publicadas', empty: 'No hay evidencias publicadas.', illustration: 'evidence' },
];
const NO_FILTER = { worksite: '', observer: '', classification: '', suspicious: '' };

const tab = ref(tabs[0]);
const notice = ref(null);
const filters = ref({ ...NO_FILTER });
const page = ref(1);

const evidences = ref(null);
const meta = ref(null); // total, has_more, counts y las opciones de los filtros
const loading = ref(true);
const loadingMore = ref(false);
const error = ref(null);

const filtersActive = computed(() => Object.values(filters.value).some((value) => value !== ''));
const activeCount = computed(() => Object.values(filters.value).filter((value) => value !== '').length);
const emptyText = computed(() => (filtersActive.value ? 'Ninguna evidencia con esos filtros.' : tab.value.empty));
const totalText = computed(() => (meta.value ? `${meta.value.total} ${meta.value.total === 1 ? 'evidencia' : 'evidencias'}` : ''));

function request() {
    const params = { status: tab.value.status, page: page.value };
    if (filters.value.worksite) params.worksite = filters.value.worksite;
    if (filters.value.observer) params.observer = filters.value.observer;
    if (filters.value.classification) params.classification = filters.value.classification;
    if (filters.value.suspicious === 'suspicious') params.suspicious = 1;
    return params;
}

async function load({ more = false } = {}) {
    (more ? loadingMore : loading).value = true;
    error.value = null;
    try {
        const body = await fetchInbox(request());
        evidences.value = more ? [...evidences.value, ...body.data] : body.data;
        meta.value = body;
    } catch (failure) {
        error.value = errorMessage(failure);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function reload() {
    page.value = 1;
    return load();
}

function open(chosen) {
    tab.value = chosen;
    notice.value = null;
    filters.value = { ...NO_FILTER };
    return reload();
}

function setFilter(name, value) {
    filters.value = { ...filters.value, [name]: value };
    return reload();
}

function clearFilters() {
    filters.value = { ...NO_FILTER };
    return reload();
}

function more() {
    page.value += 1;
    return load({ more: true });
}

function decided(id, message) {
    evidences.value = evidences.value.filter((evidence) => evidence.id !== id);
    meta.value = { ...meta.value, total: meta.value.total - 1, counts: { ...meta.value.counts, [tab.value.status]: meta.value.counts[tab.value.status] - 1 } };
    notice.value = message;
    // La cuenta de "Bandeja" en la navegación (it. 40c) baja con cada decisión.
    if (tab.value.status === 'hidden') {
        router.reload({ only: ['inboxPending'] });
    }
}

onMounted(() => open(tabs[0]));
</script>

<template>
    <AdminLayout title="Bandeja de entrada">
        <div class="flex rounded-lg bg-slate-200 p-1" role="tablist">
            <button
                v-for="candidate in tabs"
                :key="candidate.status"
                type="button"
                role="tab"
                :aria-selected="tab.status === candidate.status"
                class="min-h-11 flex-1 rounded-md py-2 text-sm font-semibold"
                :class="tab.status === candidate.status ? 'bg-white shadow-sm' : 'text-slate-600'"
                @click="open(candidate)"
            >{{ candidate.label }}<span v-if="meta" class="ml-1 rounded-full bg-slate-100 px-2 text-xs text-slate-700">{{ meta.counts[candidate.status] }}</span></button>
        </div>

        <details v-if="meta" data-test="filters" :open="filtersActive" class="rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
            <summary class="min-h-11 cursor-pointer py-2 text-base font-semibold text-slate-800">Filtrar{{ activeCount ? ` (${activeCount})` : '' }}</summary>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
                <label for="filter-worksite" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Obra
                    <select id="filter-worksite" :value="filters.worksite" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('worksite', $event.target.value)">
                        <option value="">Todas</option>
                        <option v-for="worksite in meta.filters.worksites" :key="worksite.id" :value="worksite.id">{{ worksite.name }}</option>
                    </select>
                </label>
                <label for="filter-observer" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Veedor
                    <select id="filter-observer" :value="filters.observer" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('observer', $event.target.value)">
                        <option value="">Todos</option>
                        <option v-for="observer in meta.filters.observers" :key="observer.id" :value="observer.id">{{ observer.name }}</option>
                    </select>
                </label>
                <label for="filter-classification" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Clasificación
                    <select id="filter-classification" :value="filters.classification" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('classification', $event.target.value)">
                        <option value="">Todas</option>
                        <option v-for="classification in meta.filters.classifications" :key="classification" :value="classification">{{ classification }}</option>
                    </select>
                </label>
                <label for="filter-suspicious" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Hora de captura
                    <select id="filter-suspicious" :value="filters.suspicious" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('suspicious', $event.target.value)">
                        <option value="">Todas</option>
                        <option value="suspicious">Solo sospechosas</option>
                    </select>
                </label>
            </div>
            <button v-if="filtersActive" type="button" data-test="clear-filters" class="mt-3 min-h-11 rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50" @click="clearFilters">Quitar filtros</button>
        </details>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>
        <p v-if="meta && meta.total > 0" data-test="total" class="text-base text-slate-700">{{ totalText }}</p>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="evidences?.length === 0"
            loading-text="Cargando evidencias…"
            :empty-text="emptyText"
            :illustration="tab.illustration"
            @retry="load()"
        >
            <div class="flex flex-col gap-4">
                <EvidenceCard v-for="evidence in evidences" :key="evidence.id" :evidence="evidence" @decided="decided" />
                <button v-if="meta?.has_more" type="button" data-test="more" :disabled="loadingMore" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40" @click="more">
                    {{ loadingMore ? 'Cargando…' : 'Ver 20 más' }}
                </button>
            </div>
        </LoadState>
    </AdminLayout>
</template>
