<script setup>
// US-012: el territorio que vigila la organización — departamentos (con la
// Gobernación y todos sus municipios) y municipios sueltos. Quitar uno no
// borra nada de lo registrado: solo deja de sincronizarse y de verse en el
// mapa de los veedores.
import { onMounted, ref, watch } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch.js';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fetchTerritory, saveTerritory, searchTerritories } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const EMPTY_TERRITORY = 'Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia.';

const { data: current, loading, error, load } = useLoader(fetchTerritory);
const chosen = ref([]);
watch(current, (territory) => (chosen.value = [...(territory ?? [])]));

const { keyword, results, searching, failed } = useDebouncedSearch(searchTerritories);

const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

function add(place) {
    if (!chosen.value.some((candidate) => candidate.code === place.code)) {
        chosen.value = [...chosen.value, place];
    }
    keyword.value = '';
    saved.value = null;
}

function remove(code) {
    chosen.value = chosen.value.filter((place) => place.code !== code);
    saved.value = null;
}

async function save() {
    saved.value = null;
    refused.value = null;
    if (chosen.value.length === 0) {
        refused.value = EMPTY_TERRITORY;
        return;
    }

    saving.value = true;
    try {
        saved.value = (await saveTerritory(chosen.value.map((place) => place.code))).message;
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Territorio">
        <LoadState :loading="loading" :error="error" loading-text="Cargando territorio…" empty-text="" @retry="load">
            <section class="flex flex-col gap-2">
                <h3 class="text-sm font-semibold text-slate-700">Vigilamos</h3>
                <p v-if="chosen.length === 0" class="text-sm text-slate-600">Aún no ha elegido territorio.</p>
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="place in chosen" :key="place.code" data-test="territory-chosen" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm">
                        <span>{{ place.name }} · {{ place.kind }}</span>
                        <button type="button" class="px-2 py-1 font-semibold text-red-700" @click="remove(place.code)">Quitar</button>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-2">
                <label for="territory-search" class="text-sm font-semibold text-slate-700">Buscar departamento o municipio</label>
                <input
                    id="territory-search"
                    v-model="keyword"
                    type="search"
                    autocomplete="off"
                    placeholder="Escriba al menos 3 caracteres"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base"
                />
                <p v-if="searching" class="text-sm text-slate-500">Buscando…</p>
                <p v-else-if="failed" class="text-sm text-red-700">No se pudo buscar. Revise su conexión y vuelva a intentarlo.</p>
                <p v-else-if="results && results.length === 0" class="text-sm text-slate-700">No se encontraron departamentos ni municipios.</p>
                <ul v-if="results && results.length" class="flex flex-col gap-1">
                    <li v-for="place in results" :key="place.code">
                        <button type="button" data-test="territory-result" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm" @click="add(place)">
                            {{ place.name }} <span class="text-slate-500">· {{ place.kind }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <p v-if="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ refused }}</p>
            <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

            <button type="button" :disabled="saving" class="rounded-lg bg-slate-900 px-3 py-4 font-semibold text-white disabled:opacity-40" @click="save">
                {{ saving ? 'Guardando…' : 'Guardar territorio' }}
            </button>
        </LoadState>
    </AdminLayout>
</template>
