<script setup>
// Iteración 47a — "Encontrar la obra en campo" (US-016, US-019; V18, V19). Al
// abrir "Nuevo reporte", las obras reportables del municipio del veedor (lo
// decide el servidor con su GPS), con filtros de tipo de obra, situación y
// entidad, plazo vencido primero, de 20 en 20, y la búsqueda sin tildes. Con
// una lectura de 50 m o menos, las obras cercanas encima ("Cerca de usted").
// La ubicación va solo en la primera petición, en el cuerpo (it. 45f).
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { browseContracts, fetchNearbyWorksites } from '@/services/api.js';
import { situationText } from '@/lib/situation.js';

const props = defineProps({
    // undefined: el GPS todavía responde · null: sin ubicación · { latitude, longitude, accuracy }
    location: { type: Object, default: undefined },
});
const emit = defineEmits(['select']);

const NO_NEARBY = '📍 No se encontraron obras a menos de 500m. Utilice el buscador para encontrarla por nombre o contrato.';
const NOT_FOUND = 'No se encontraron obras activas. Verifique el número de proceso, nombre del contratista o palabras clave de la obra.';
const SITUATIONS = [
    { key: 'all', label: 'Todas' },
    { key: 'overdue', label: 'Plazo vencido' },
    { key: 'in_progress', label: 'En ejecución' },
    { key: 'finished', label: 'Terminada hace poco' },
];

const filters = ref({ municipality: null, situation: 'all', workType: '', entity: '' });
const keyword = ref('');
const scope = ref('municipality');
const page = ref(1);
const answer = ref(null);
const items = ref([]);
const nearby = ref(null);
const notice = ref(null);
const status = ref('waiting'); // waiting | loading | ready | failed
const loadingMore = ref(false);

const searched = computed(() => keyword.value.trim().length >= 3);
const municipalityName = computed(() => answer.value?.municipality?.name ?? 'su municipio');
const municipalities = computed(() => {
    const choices = answer.value?.municipalities ?? [];
    const current = answer.value?.municipality;
    return current && !choices.some((choice) => choice.code === current.code) ? [{ ...current, count: 0 }, ...choices] : choices;
});

let latest = 0;

/** El cuerpo de la petición: solo lo elegido; la ubicación, solo la primera vez. */
function body(withLocation) {
    const request = {};
    if (withLocation && props.location) {
        Object.assign(request, { latitude: props.location.latitude, longitude: props.location.longitude, accuracy: props.location.accuracy });
    }
    if (filters.value.municipality) request.municipality = filters.value.municipality;
    if (filters.value.situation !== 'all') request.situation = filters.value.situation;
    if (filters.value.workType) request.work_type = filters.value.workType;
    if (filters.value.entity) request.entity = filters.value.entity;
    if (searched.value) request.q = keyword.value.trim();
    if (searched.value && scope.value === 'territory') request.scope = 'territory';
    request.page = page.value;
    return request;
}

async function load({ more = false, withLocation = false } = {}) {
    const current = ++latest;
    if (more) {
        loadingMore.value = true;
    } else {
        status.value = 'loading';
    }
    try {
        const received = await browseContracts(body(withLocation));
        if (current !== latest) return;
        answer.value = received;
        items.value = more ? [...items.value, ...received.data] : received.data;
        filters.value.municipality = received.municipality?.code ?? null;
        if (withLocation) {
            nearby.value = received.nearby;
            notice.value = received.notice;
        }
        status.value = 'ready';
    } catch {
        if (current === latest) status.value = 'failed';
    } finally {
        if (current === latest) loadingMore.value = false;
    }
}

const firstLoad = () => load({ withLocation: true });
const reload = () => {
    page.value = 1;
    return load();
};

watch(
    () => props.location,
    (location) => {
        if (location !== undefined && answer.value === null && status.value === 'waiting') firstLoad();
        lookNearby(location);
    },
    { immediate: true },
);

// It. 47b: las obras cercanas, apenas llega una lectura de 50 m o menos (si la
// primera no lo era). Una sola vez: después, la lista ya las tiene.
let lookingNearby = false;
async function lookNearby(location) {
    if (!answer.value || nearby.value !== null || lookingNearby || !location || location.accuracy > 50) return;
    lookingNearby = true;
    try {
        nearby.value = await fetchNearbyWorksites(location.latitude, location.longitude);
    } catch {
        lookingNearby = false;
    }
}

function chooseMunicipality(code) {
    filters.value.municipality = code;
    notice.value = null;
    filters.value.entity = ''; // las entidades son las del municipio
    reload();
}

function setFilter(name, value) {
    filters.value[name] = value;
    reload();
}

let timer;
watch(keyword, (value, before) => {
    clearTimeout(timer);
    scope.value = 'municipality';
    const now = value.trim();
    // Por debajo de 3 caracteres no se busca; al borrar la búsqueda, vuelve la lista.
    if (now.length < 3 && before.trim().length < 3) return;
    timer = setTimeout(reload, 300);
});
onBeforeUnmount(() => clearTimeout(timer));

function wholeTerritory() {
    scope.value = 'territory';
    reload();
}

function more() {
    page.value += 1;
    load({ more: true });
}

function retry() {
    return answer.value === null ? firstLoad() : reload();
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="status === 'waiting'" class="text-base text-slate-700">Buscando su ubicación…</p>

        <!-- US-019: las obras cercanas, encima de la lista -->
        <section v-if="answer && (nearby !== null || (location && location.accuracy > 50))" data-test="nearby-section" class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">Cerca de usted</h2>
            <p v-if="nearby === null" class="rounded-2xl bg-white p-3 text-base text-slate-700 shadow-soft ring-1 ring-slate-900/5">
                Buscando señal GPS: {{ Math.round(location.accuracy) }} m. Se necesitan 50 m o menos.
            </p>
            <p v-else-if="nearby.length === 0" class="rounded-2xl bg-white p-3 text-base text-slate-700 shadow-soft ring-1 ring-slate-900/5">{{ NO_NEARBY }}</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="item in nearby" :key="item.worksite_id">
                    <button type="button" data-test="nearby" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-2xl bg-white p-3 text-left shadow-soft ring-1 ring-slate-900/5 active:bg-slate-100" @click="emit('select', item.contract)">
                        <span>
                            <span class="block text-base font-semibold">{{ item.name }}</span>
                            <span class="block text-base text-slate-600">{{ item.contract.entity_name }}</span>
                        </span>
                        <span data-test="distance" class="shrink-0 text-base font-semibold text-slate-700">a {{ item.distance_meters }} m</span>
                    </button>
                </li>
            </ul>
        </section>

        <p v-if="notice" data-test="notice" role="status" class="rounded-lg bg-amber-50 p-3 text-base text-amber-900 ring-1 ring-amber-200">{{ notice }}</p>

        <!-- US-016: las obras del municipio -->
        <section v-if="status !== 'waiting'" class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Obras de {{ municipalityName }}</h2>

            <div class="flex flex-col gap-1">
                <label for="work-search" class="text-base font-semibold text-slate-700">Buscar Obra</label>
                <input id="work-search" v-model="keyword" type="search" autocomplete="off" placeholder="Obra, entidad, contratista o número de proceso" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base focus:border-brand-700 focus:outline-none" />
            </div>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <label class="flex flex-col gap-1 text-base font-semibold text-slate-700">
                    Municipio
                    <select id="municipality" :value="filters.municipality ?? ''" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="chooseMunicipality($event.target.value)">
                        <option v-for="choice in municipalities" :key="choice.code" :value="choice.code">{{ choice.name }} ({{ choice.count }})</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-base font-semibold text-slate-700">
                    Situación
                    <select id="situation" :value="filters.situation" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('situation', $event.target.value)">
                        <option v-for="situation in SITUATIONS" :key="situation.key" :value="situation.key">{{ situation.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-base font-semibold text-slate-700">
                    Tipo de obra
                    <select id="work-type" :value="filters.workType" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('workType', $event.target.value)">
                        <option value="">Todos los tipos</option>
                        <option v-for="type in answer?.work_types ?? []" :key="type.key" :value="type.key">{{ type.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-base font-semibold text-slate-700">
                    Entidad
                    <select id="entity" :value="filters.entity" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('entity', $event.target.value)">
                        <option value="">Todas las entidades</option>
                        <option v-for="entity in answer?.entities ?? []" :key="entity.name" :value="entity.name">{{ entity.name }} ({{ entity.count }})</option>
                    </select>
                </label>
            </div>

            <p v-if="status === 'loading'" class="text-base text-slate-600">Cargando obras…</p>
            <div v-else-if="status === 'failed'" class="flex flex-col gap-2">
                <p role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">No se pudieron cargar las obras. Revise su conexión y vuelva a intentarlo.</p>
                <button type="button" data-test="retry" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800" @click="retry">Intentar de nuevo</button>
            </div>
            <template v-else-if="status === 'ready'">
                <div v-if="items.length === 0" class="flex flex-col gap-2">
                    <template v-if="searched && scope === 'municipality'">
                        <p class="text-base text-slate-700">No se encontraron obras en {{ municipalityName }} con esa búsqueda.</p>
                        <button type="button" data-test="whole-territory" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800" @click="wholeTerritory">Buscar en todo el territorio</button>
                    </template>
                    <p v-else-if="searched" class="text-base text-slate-700">{{ NOT_FOUND }}</p>
                    <p v-else class="text-base text-slate-700">No hay obras para reportar en {{ municipalityName }} con estos filtros.</p>
                </div>

                <ul v-else class="flex flex-col gap-2">
                    <li v-for="item in items" :key="item.secop_contract_id" data-test="work">
                        <button type="button" data-test="contract-result" class="flex min-h-11 w-full flex-col gap-1 rounded-2xl bg-white p-3 text-left shadow-soft ring-1 ring-slate-900/5 active:bg-slate-100" @click="emit('select', item)">
                            <span data-test="work-name" class="line-clamp-2 text-base font-semibold">{{ item.name ?? item.object }}</span>
                            <span class="text-base text-slate-700">{{ item.entity_name }}<template v-if="scope === 'territory' && item.municipality"> · {{ item.municipality }}</template></span>
                            <span class="text-base text-slate-600">{{ item.work_type_label }} · {{ item.process_number }}</span>
                            <span class="flex flex-wrap gap-2">
                                <span :class="['rounded-full px-2 py-0.5 text-base', item.situation === 'overdue' ? 'bg-red-50 text-red-800' : 'bg-slate-100 text-slate-700']">{{ situationText(item) }}</span>
                                <span v-if="!item.located" class="rounded-full bg-amber-50 px-2 py-0.5 text-base text-amber-900">Sin ubicación todavía</span>
                            </span>
                        </button>
                    </li>
                </ul>

                <button v-if="answer.has_more" type="button" data-test="more" :disabled="loadingMore" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800" @click="more">
                    {{ loadingMore ? 'Cargando…' : 'Ver 20 más' }}
                </button>
            </template>
        </section>
    </div>
</template>
