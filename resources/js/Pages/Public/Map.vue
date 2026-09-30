<script setup>
// US-027: el mapa público de la organización (R-MAP-01), sin sesión. Carga
// solo los pines (R-MAP-02); tocar uno abre la vista de su obra, que pide
// sus datos en ese momento.
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import PinsMap from '@/Components/Public/PinsMap.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchMapFilters, fetchPins, fetchWorksiteList } from '@/services/api.js';

const page = usePage();
const { data: pins, loading, error, load } = useLoader(fetchPins);

// It. 40b: los estados, junto al mapa y con su signo (no solo el color):
// son a la vez la leyenda y el filtro más usado. Cuántas obras hay de cada
// uno se cuenta sin el filtro de estado, para que no se pierdan de vista.
const STATES = [
    { value: 'green', icon: '✓', label: 'Normal', css: 'bg-green-700 text-white', idle: 'border-green-700 text-green-900' },
    { value: 'yellow', icon: '!', label: 'Alerta', css: 'bg-yellow-400 text-slate-900', idle: 'border-yellow-500 text-slate-900' },
    { value: 'red', icon: '✕', label: 'En riesgo', css: 'bg-red-700 text-white', idle: 'border-red-700 text-red-900' },
];
const counts = ref({ green: 0, yellow: 0, red: 0 });
const LOOK = Object.fromEntries(STATES.map((state) => [state.value, state]));

// It. 40c (V12): el mapa también como lista, para quien no maneja mapas y para
// los lectores de pantalla, con una búsqueda por nombre. Se pide al abrirla.
const view = ref('map');
const listed = ref(null);
const listError = ref(null);
const words = ref('');
const plain = (text) => (text ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const shown = computed(() => (listed.value ?? []).filter((worksite) => plain(worksite.name).includes(plain(words.value.trim()))));

async function loadList() {
    listError.value = null;
    try {
        listed.value = await fetchWorksiteList(active.value);
    } catch {
        listError.value = 'No se pudo cargar la lista de obras. Revise su conexión e intente de nuevo.';
    }
}

function show(next) {
    view.value = next;
    if (next === 'list') {
        loadList();
    }
}

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
    if (view.value === 'list') {
        loadList();
    }
}

function toggleState(value) {
    filters.status = filters.status === value ? '' : value;
    apply();
}

watch(pins, (loaded) => {
    if (loaded && !active.value.status) {
        counts.value = Object.fromEntries(STATES.map((state) => [state.value, loaded.filter((pin) => pin.color_pin === state.value).length]));
    }
});

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
        <h1 class="text-xl font-semibold">Obras vigiladas</h1>
        <p class="mb-3 text-base text-slate-700">Toque un punto para ver la obra y sus fotos.</p>

        <div role="group" aria-label="Estado de las obras" class="mb-3 flex flex-wrap gap-2">
            <button
                v-for="state in STATES"
                :key="state.value"
                type="button"
                data-test="state"
                :aria-pressed="filters.status === state.value && active.status === state.value ? 'true' : 'false'"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border-2 px-3 text-base font-semibold"
                :class="active.status === state.value ? `${state.css} border-transparent` : `bg-white ${state.idle}`"
                @click="toggleState(state.value)"
            ><span aria-hidden="true" class="grid size-6 place-items-center rounded-full font-bold" :class="state.css">{{ state.icon }}</span> {{ state.label }} <span class="rounded-full bg-slate-100 px-2 text-sm text-slate-900">{{ counts[state.value] }}</span></button>
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            <Link href="/verify" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold">Validar un archivo</Link>
            <Link href="/stats" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold">Estadísticas del territorio</Link>
        </div>

        <details class="mb-3 rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <summary class="min-h-11 cursor-pointer py-2 text-base font-semibold">Más filtros</summary>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
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
                <label for="filter-min-value" class="flex flex-col gap-1 md:col-span-2">
                    <span class="text-xs font-semibold text-slate-700">Presupuesto mayor a (pesos)</span>
                    <input id="filter-min-value" v-model="filters.min_value" type="number" min="0" inputmode="numeric" class="min-h-11 rounded-lg border border-slate-300 px-2 text-base" />
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <button type="button" :disabled="Object.keys(chosen).length === 0" class="min-h-11 flex-1 rounded-lg bg-slate-900 px-3 font-semibold text-white disabled:opacity-40" @click="apply">Aplicar</button>
                <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-3 font-semibold" @click="clear">Limpiar</button>
            </div>
        </details>
        <div role="group" aria-label="Cómo ver las obras" class="mb-3 grid grid-cols-2 gap-2 md:inline-grid md:w-72">
            <button v-for="[value, name] in [['map', 'Mapa'], ['list', 'Lista']]" :key="value" type="button" :aria-pressed="view === value ? 'true' : 'false'" class="min-h-11 rounded-lg border-2 px-3 text-base font-semibold" :class="view === value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white'" @click="show(value)">{{ name }}</button>
        </div>

        <section v-if="view === 'list'" aria-label="Lista de obras" class="flex flex-col gap-3">
            <label for="worksite-words" class="text-base font-semibold">Buscar una obra por su nombre</label>
            <input id="worksite-words" v-model="words" type="search" placeholder="Por ejemplo: parque, colegio, Calle 30" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            <p v-if="listError" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ listError }}</p>
            <p v-else-if="listed === null" class="text-base text-slate-700">Cargando obras…</p>
            <p v-else-if="shown.length === 0" class="rounded-lg bg-white p-3 text-base text-slate-700">{{ words.trim() ? 'Ninguna obra se llama así. Pruebe con otra palabra.' : 'No hay obras que coincidan con estos filtros.' }}</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="worksite in shown" :key="worksite.id" data-test="listed">
                    <Link :href="`/worksite/${worksite.id}`" class="flex min-h-16 items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 hover:bg-slate-50"><span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-full text-lg font-bold" :class="LOOK[worksite.color_pin].css">{{ LOOK[worksite.color_pin].icon }}</span> <span class="flex flex-col"><span class="text-sm font-semibold">{{ LOOK[worksite.color_pin].label }}</span> <span class="text-base font-semibold">{{ worksite.name }}</span> <span class="text-sm text-slate-700">{{ worksite.municipality }}</span></span></Link>
                </li>
            </ul>
        </section>

        <LoadState
            v-else
            :loading="loading"
            :error="error"
            :empty="pins !== null && pins.length === 0"
            loading-text="Cargando obras…"
            empty-text="No se encontraron obras o evidencias que coincidan con estos filtros en este territorio."
            @retry="load(active)"
        >
            <PinsMap :pins="pins ?? []" @select="(id) => router.visit(`/worksite/${id}`)" />
        </LoadState>
    </AppLayout>
</template>
