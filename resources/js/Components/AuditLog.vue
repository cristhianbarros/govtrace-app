<script setup>
// US-043-MON: el log de auditoría, de 20 en 20 y lo más reciente primero.
// Cada entrada es una frase ("Ana Directora desactivó a un Super
// Administrador"), con el valor anterior y el nuevo desplegables. La misma
// lista para el Super Administrador (todo, con la organización de cada
// entrada) y para el Administrador (solo lo de su organización).
// It. 46d: filtros por fecha, quién, tipo de acción y, en el panel global,
// organización; combinables, y la paginación los conserva.
import { computed, onMounted, reactive, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import { formatDateTime } from '@/lib/format.js';

const props = defineProps({
    fetchPage: { type: Function, required: true }, // (página, filtros) → { data, meta, options }
    showOrganization: { type: Boolean, default: false },
});

const FIELDS = ['from', 'to', 'actor', 'group', 'organization'];

const pageNumber = ref(1);
const draft = reactive({ from: '', to: '', actor: '', group: '', organization: '' }); // lo que está escrito
const applied = ref({}); // lo que se pidió: solo lo lleno
const options = ref({ groups: [], organizations: [] });
const { data: log, loading, error, load } = useLoader(async (...args) => {
    const answer = await props.fetchPage(...args);
    options.value = answer.options ?? options.value; // se conservan mientras carga otra página o falla un filtro
    return answer;
});

const filtering = computed(() => Object.keys(applied.value).length > 0);

function request(next) {
    pageNumber.value = next;
    return load(next, applied.value);
}

function applyFilters() {
    applied.value = Object.fromEntries(FIELDS.map((field) => [field, draft[field].trim()]).filter(([, value]) => value !== ''));
    return request(1);
}

function removeFilters() {
    FIELDS.forEach((field) => (draft[field] = ''));
    applied.value = {};
    return request(1);
}

/** { nit: "900123456-8" } → ["nit: 900123456-8"] */
const lines = (values) => Object.entries(values ?? {}).map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : value}`);

onMounted(() => request(1));
</script>

<template>
    <div class="flex flex-col gap-3">
        <form data-test="audit-filters" class="grid grid-cols-1 gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5 md:grid-cols-3" aria-label="Filtros del registro" @submit.prevent="applyFilters">
            <div class="flex flex-col gap-1">
                <label for="audit-from" class="text-sm font-semibold text-slate-700">Desde</label>
                <input id="audit-from" v-model="draft.from" type="date" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="audit-to" class="text-sm font-semibold text-slate-700">Hasta</label>
                <input id="audit-to" v-model="draft.to" type="date" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="audit-actor" class="text-sm font-semibold text-slate-700">Quién lo hizo</label>
                <input id="audit-actor" v-model="draft.actor" type="text" maxlength="100" placeholder="Parte del nombre" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="audit-group" class="text-sm font-semibold text-slate-700">Tipo de acción</label>
                <select id="audit-group" v-model="draft.group" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base">
                    <option value="">Todos los tipos</option>
                    <option v-for="group in options.groups" :key="group.key" :value="group.key">{{ group.label }}</option>
                </select>
            </div>
            <div v-if="showOrganization" class="flex flex-col gap-1">
                <label for="audit-organization" class="text-sm font-semibold text-slate-700">Organización</label>
                <select id="audit-organization" v-model="draft.organization" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base">
                    <option value="">Todas las organizaciones</option>
                    <option v-for="organization in options.organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
                </select>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="submit" class="min-h-11 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white hover:bg-brand-800">Filtrar</button>
                <button v-if="filtering" type="button" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-800 hover:bg-brand-50" @click="removeFilters">Quitar filtros</button>
            </div>
        </form>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="log?.meta.total === 0"
            loading-text="Cargando el registro…"
            :empty-text="filtering ? 'Ninguna entrada cumple esos filtros.' : 'Aún no hay nada registrado.'"
            illustration="records"
            @retry="request(pageNumber)"
        >
            <ul class="flex flex-col gap-2">
                <li v-for="entry in log.data" :key="entry.id" data-test="audit-entry" class="rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                    <p class="font-semibold">{{ entry.sentence }}</p>
                    <p class="text-xs text-slate-600">
                        {{ entry.actor }} · {{ formatDateTime(entry.created_at) }}<template v-if="showOrganization && entry.organization"> · {{ entry.organization }}</template>
                    </p>
                    <details class="mt-2 text-xs">
                        <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-brand-800">Ver el antes y el después</summary>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <div>
                                <p class="text-slate-500">Antes</p>
                                <p v-for="line in lines(entry.before)" :key="line" class="break-all">{{ line }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Después</p>
                                <p v-for="line in lines(entry.after)" :key="line" class="break-all">{{ line }}</p>
                            </div>
                        </div>
                    </details>
                </li>
            </ul>

            <nav class="flex items-center justify-between gap-2 text-sm" aria-label="Páginas">
                <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="pageNumber <= 1" @click="request(pageNumber - 1)">
                    Anterior
                </button>
                <span class="text-slate-600">Página {{ log.meta.current_page }} de {{ log.meta.last_page }} · {{ log.meta.total }} entradas</span>
                <button
                    type="button"
                    class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40"
                    :disabled="pageNumber >= log.meta.last_page"
                    @click="request(pageNumber + 1)"
                >
                    Siguiente
                </button>
            </nav>
        </LoadState>
    </div>
</template>
