<script setup>
// US-027: el mapa público de la organización (R-MAP-01), sin sesión. Carga
// solo los pines (R-MAP-02); tocar uno abre la vista de su obra, que pide
// sus datos en ese momento.
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import PinsMap from '@/Components/Public/PinsMap.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchMapFilters, fetchPins } from '@/services/api.js';

const page = usePage();
const { data: pins, loading, error, load } = useLoader(fetchPins);

const LEGEND = [
    { css: 'bg-green-600', label: 'Normal' },
    { css: 'bg-yellow-400', label: 'Alerta' },
    { css: 'bg-red-600', label: 'En riesgo' },
];

// US-028: estado, fechas de las evidencias, presupuesto y municipio.
const STATUSES = [
    { value: 'green', label: 'Normal' },
    { value: 'yellow', label: 'Alerta' },
    { value: 'red', label: 'En riesgo' },
];
const EMPTY_FILTERS = { status: '', from: '', to: '', min_value: '', municipality: '' };
const filters = reactive({ ...EMPTY_FILTERS });
const active = ref({});
const municipalities = ref([]);

const chosen = computed(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value).trim() !== '').map(([key, value]) => [key, String(value).trim()])));

function apply() {
    active.value = chosen.value;
    load(active.value);
}

function clear() {
    Object.assign(filters, EMPTY_FILTERS);
    active.value = {};
    load({});
}

onMounted(async () => {
    load({});
    try {
        municipalities.value = (await fetchMapFilters()).municipalities;
    } catch {
        municipalities.value = []; // sin municipios, el resto de filtros sigue sirviendo
    }
});
</script>

<template>
    <Head title="Mapa de obras" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <OrganizationNotice />
        <Link href="/verify" class="mb-3 inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold">Validar un archivo</Link>

        <details class="mb-3 rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <summary class="min-h-11 cursor-pointer py-2 font-semibold">Filtrar obras</summary>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label for="filter-status" class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-slate-700">Estado</span>
                    <select id="filter-status" v-model="filters.status" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base">
                        <option value="">Todos</option>
                        <option v-for="status in STATUSES" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>
                <label for="filter-municipality" class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-slate-700">Municipio</span>
                    <select id="filter-municipality" v-model="filters.municipality" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base">
                        <option value="">Todos</option>
                        <option v-for="municipality in municipalities" :key="municipality.code" :value="municipality.code">{{ municipality.name }}</option>
                    </select>
                </label>
                <label for="filter-from" class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-slate-700">Evidencias desde</span>
                    <input id="filter-from" v-model="filters.from" type="date" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base" />
                </label>
                <label for="filter-to" class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-slate-700">Evidencias hasta</span>
                    <input id="filter-to" v-model="filters.to" type="date" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base" />
                </label>
                <label for="filter-min-value" class="flex flex-col gap-1 sm:col-span-2">
                    <span class="text-xs font-semibold text-slate-700">Presupuesto mayor a (pesos)</span>
                    <input id="filter-min-value" v-model="filters.min_value" type="number" min="0" inputmode="numeric" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base" />
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <button type="button" :disabled="Object.keys(chosen).length === 0" class="min-h-11 flex-1 rounded-lg bg-slate-900 px-3 font-semibold text-white disabled:opacity-40" @click="apply">Aplicar</button>
                <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-3 font-semibold" @click="clear">Limpiar</button>
            </div>
        </details>
        <LoadState
            :loading="loading"
            :error="error"
            :empty="pins !== null && pins.length === 0"
            loading-text="Cargando obras…"
            empty-text="No se encontraron obras o evidencias que coincidan con estos filtros en este territorio."
            @retry="load(active)"
        >
            <PinsMap :pins="pins ?? []" @select="(id) => router.visit(`/worksite/${id}`)" />
            <ul class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700" aria-label="Qué significa cada color">
                <li v-for="item in LEGEND" :key="item.label" class="flex items-center gap-2">
                    <span class="size-3 rounded-full" :class="item.css" aria-hidden="true"></span>{{ item.label }}
                </li>
            </ul>
        </LoadState>
    </AppLayout>
</template>
