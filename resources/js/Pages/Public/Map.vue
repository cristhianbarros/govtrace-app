<script setup>
// US-027: el mapa público de la organización (R-MAP-01), sin sesión. Carga
// solo los pines (R-MAP-02); tocar uno abre la tarjeta de su obra sobre el
// mapa (it. 60), que pide sus datos en ese momento.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Illustration from '@/Components/Brand/Illustration.vue';
import WorksIllustration from '@/Components/Brand/WorksIllustration.vue';
import LoadState from '@/Components/LoadState.vue';
import MoreButton from '@/Components/MoreButton.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import PinsMap from '@/Components/Public/PinsMap.vue';
import WorksiteCard from '@/Components/Public/WorksiteCard.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchMapFilters, fetchPins, fetchUnlocatedWorksites, fetchWorksiteList } from '@/services/api.js';

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

// It. 60 (US-029): la tarjeta de la obra cuyo pin se tocó; al cerrarla, el foco vuelve a ese pin.
const selected = ref(null);
const pinsMap = ref(null);

function closeCard() {
    const id = selected.value;
    selected.value = null;
    nextTick(() => pinsMap.value?.focusPin(id));
}

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

// It. 58 (US-059-LEG): las obras del territorio que aún no están en el mapa — ningún
// veedor las ha visitado, o su ubicación está por confirmar —, para que el ciudadano
// informe de cualquiera. Las busca el servidor (en una ciudad son cientos), de a 20.
const unlocated = ref(null);
const unlocatedMore = ref(false);
const unlocatedPage = ref(1);
const unlocatedError = ref(null);
const loadingMoreUnlocated = ref(false);
const MIN_WORDS = 3;
const searched = computed(() => (words.value.trim().length >= MIN_WORDS ? words.value.trim() : ''));
const unlocatedParams = () => ({ ...(searched.value ? { q: searched.value } : {}), ...(active.value.municipality ? { municipality: active.value.municipality } : {}) });
let lastAsked = null;

async function loadUnlocated(page = 1) {
    const params = unlocatedParams();
    lastAsked = JSON.stringify(params);
    unlocatedError.value = null;
    try {
        const answer = await fetchUnlocatedWorksites(page > 1 ? { ...params, page } : params);
        unlocated.value = page === 1 ? answer.data : [...unlocated.value, ...answer.data];
        unlocatedMore.value = answer.has_more;
        unlocatedPage.value = page;
    } catch {
        unlocatedError.value = 'No se pudieron cargar las obras sin ubicación. Revise su conexión e intente de nuevo.';
    }
}

async function moreUnlocated() {
    loadingMoreUnlocated.value = true;
    await loadUnlocated(unlocatedPage.value + 1);
    loadingMoreUnlocated.value = false;
}

// Mientras escribe, se busca cuando deja de escribir un momento, y solo si cambió lo que se busca.
let typing = null;
watch(searched, () => {
    clearTimeout(typing);
    typing = setTimeout(() => {
        if (view.value === 'list' && JSON.stringify(unlocatedParams()) !== lastAsked) {
            loadUnlocated();
        }
    }, 400);
});
onBeforeUnmount(() => clearTimeout(typing));

function show(next) {
    view.value = next;
    if (next === 'list') {
        loadList();
        loadUnlocated();
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
        loadUnlocated();
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
    if (view.value === 'list') {
        loadUnlocated();
    }
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
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo" sections>
        <OrganizationNotice />
        <!-- It. 40e: la bienvenida — qué es esta página, en dos líneas, con una ilustración. -->
        <section class="mb-3 flex items-center gap-3 rounded-3xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl md:text-3xl">Obras vigiladas</h1>
                <p class="mt-1 text-base text-slate-700">Toque un punto para ver la obra y sus fotos. Cada foto lleva un sello digital: nadie puede cambiarla.</p>
            </div>
            <div class="w-24 shrink-0 md:w-36"><WorksIllustration /></div>
        </section>
        <!-- It. 40d: para quien llega por primera vez, cómo llega cada evidencia al mapa. -->
        <details data-test="how" class="mb-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
            <summary class="min-h-11 cursor-pointer py-2 text-base font-semibold">¿Cómo funciona?</summary>
            <ol class="mt-1 flex list-decimal flex-col gap-2 pl-6 text-base text-slate-800">
                <li>Los veedores de la veeduría visitan las obras y toman fotos con su celular.</li>
                <li>Cada foto recibe un sello digital en el momento: nadie puede borrarla ni cambiarla.</li>
                <li>La veeduría los revisa y publica aquí. En cada foto, "Comprobar que es original" lo demuestra.</li>
            </ol>
            <p class="mt-2 text-base text-slate-800">Los colores (Normal, Alerta y En riesgo) son alertas de GovTrace, no decisiones de una autoridad. Cada obra explica por qué tiene el suyo.</p>
        </details>

        <!-- It. 40e: los estados, como fichas grandes que también filtran: el número se ve de lejos. -->
        <div role="group" aria-label="Estado de las obras" class="mb-3 grid grid-cols-3 gap-2">
            <button
                v-for="state in STATES"
                :key="state.value"
                type="button"
                data-test="state"
                :aria-pressed="filters.status === state.value && active.status === state.value ? 'true' : 'false'"
                class="flex min-h-11 flex-col items-center gap-0.5 rounded-2xl border-2 px-2 py-2 text-center font-semibold shadow-soft transition"
                :class="active.status === state.value ? `${state.css} border-transparent` : `bg-white ${state.idle} hover:-translate-y-0.5`"
                @click="toggleState(state.value)"
            ><span aria-hidden="true" class="grid size-7 place-items-center rounded-full font-bold" :class="state.css">{{ state.icon }}</span> <span class="order-3 text-sm">{{ state.label }}</span> <span class="order-2 font-display text-2xl leading-tight">{{ counts[state.value] }}</span></button>
        </div>


        <details class="mb-3 rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-3 text-sm">
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
                <button type="button" :disabled="Object.keys(chosen).length === 0" class="min-h-11 flex-1 rounded-xl bg-brand-700 px-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800" @click="apply">Aplicar</button>
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 font-semibold" @click="clear">Limpiar</button>
            </div>
        </details>
        <div role="group" aria-label="Cómo ver las obras" class="mb-3 grid grid-cols-2 gap-2 md:inline-grid md:w-72">
            <button v-for="[value, name] in [['map', 'Mapa'], ['list', 'Lista']]" :key="value" type="button" :aria-pressed="view === value ? 'true' : 'false'" class="min-h-11 rounded-lg border-2 px-3 text-base font-semibold" :class="view === value ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 bg-white'" @click="show(value)">{{ name }}</button>
        </div>

        <section v-if="view === 'list'" aria-label="Lista de obras" class="flex flex-col gap-3">
            <label for="worksite-words" class="text-base font-semibold">Buscar una obra por su nombre</label>
            <input id="worksite-words" v-model="words" type="search" placeholder="Por ejemplo: parque, colegio, Calle 30" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            <p v-if="listError" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ listError }}</p>
            <p v-else-if="listed === null" class="text-base text-slate-700">Cargando obras…</p>
            <div v-else-if="shown.length === 0" data-test="empty" class="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-6 text-center shadow-soft ring-1 ring-slate-900/5">
                <Illustration name="search" size="w-32" />
                <p class="text-base text-slate-700">{{ words.trim() ? 'Ninguna obra se llama así. Pruebe con otra palabra.' : 'No hay obras que coincidan con estos filtros.' }}</p>
            </div>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="worksite in shown" :key="worksite.id" data-test="listed">
                    <Link :href="`/worksite/${worksite.id}`" class="flex min-h-16 items-center gap-3 rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-3 hover:bg-slate-50"><span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-full text-lg font-bold" :class="LOOK[worksite.color_pin].css">{{ LOOK[worksite.color_pin].icon }}</span> <span class="flex flex-col"><span class="text-sm font-semibold">{{ LOOK[worksite.color_pin].label }}</span> <span class="text-base font-semibold">{{ worksite.name }}</span> <span class="text-sm text-slate-700">{{ worksite.municipality }}</span></span></Link>
                </li>
            </ul>

            <!-- It. 58: las que aún no están en el mapa, para informar de cualquiera. -->
            <section data-test="unlocated-section" aria-labelledby="unlocated-title" class="mt-4 flex flex-col gap-2">
                <h2 id="unlocated-title" class="text-lg font-semibold">Obras sin ubicación todavía</h2>
                <p class="text-base text-slate-700">No están en el mapa: ningún veedor las ha visitado, o su ubicación está por confirmar. Puede informarle a la veeduría de cualquiera, y decirle dónde queda.</p>
                <p v-if="unlocatedError" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ unlocatedError }}</p>
                <p v-else-if="unlocated === null" class="text-base text-slate-700">Cargando las obras sin ubicación…</p>
                <p v-else-if="unlocated.length === 0" data-test="unlocated-empty" class="text-base text-slate-700">{{ searched ? 'Ninguna obra sin ubicación se llama así.' : 'Todas las obras del territorio ya están en el mapa.' }}</p>
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="work in unlocated" :key="work.contract_id" data-test="unlocated">
                        <Link :href="work.worksite_id ? `/worksite/${work.worksite_id}` : `/contract/${encodeURIComponent(work.contract_id)}`" class="flex min-h-16 flex-col justify-center rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5 hover:bg-slate-50"><span class="text-base font-semibold">{{ work.name }}</span> <span class="text-sm text-slate-700">{{ [work.municipality, work.entity_name].filter(Boolean).join(' · ') }}</span></Link>
                    </li>
                </ul>
                <MoreButton v-if="unlocatedMore" :loading="loadingMoreUnlocated" @more="moreUnlocated" />
            </section>
        </section>

        <LoadState
            v-else
            :loading="loading"
            :error="error"
            :empty="pins !== null && pins.length === 0"
            loading-text="Cargando obras…"
            empty-text="No se encontraron obras o evidencias que coincidan con estos filtros en este territorio."
            illustration="search"
            @retry="load(active)"
        >
            <PinsMap ref="pinsMap" :pins="pins ?? []" @select="(id) => (selected = id)" />
            <WorksiteCard v-if="selected !== null" :key="selected" :worksite-id="selected" @close="closeCard" />
            <!-- It. 53 (R-PRIV-02): el punto es aproximado, y se dice con palabras, no solo con el círculo. -->
            <p data-test="approximate-note" class="mt-2 text-base text-slate-700">Cada punto es aproximado: la obra está a unos 100 m de él. Es para proteger a quien tomó la foto.</p>
        </LoadState>
    </AppLayout>
</template>
