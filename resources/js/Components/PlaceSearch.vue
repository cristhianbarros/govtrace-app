<script setup>
// It. 68 (US-059-LEG, US-035): dónde queda una obra, por su dirección o un sitio
// conocido. Busca al tocar "Buscar" o con Enter, nunca letra a letra: la
// política de OpenStreetMap (Nominatim) no admite autocompletar. Lo escrito es
// del formulario (v-model); el lugar elegido sale en "found", con su punto.
import { ref } from 'vue';
import { searchPlaces } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    work: { type: Object, required: true }, // { worksite_id } o { contract_id }: alrededor de su municipio
});
const emit = defineEmits(['found']);
const text = defineModel({ type: String, default: '' });

const MIN_LETTERS = 3;
const NOT_FOUND = 'No encontramos ese lugar. Pruebe con el barrio, un sitio conocido cercano o la calle sin el número, o márquelo en el mapa.';

const searching = ref(false);
const places = ref([]);
const refused = ref(null);
const chosen = ref(null);

async function search() {
    if (searching.value) {
        return;
    }
    const query = text.value.trim();
    chosen.value = null;
    places.value = [];
    refused.value = query.length < MIN_LETTERS ? `Escriba al menos ${MIN_LETTERS} letras del lugar.` : null;
    if (refused.value) {
        return;
    }
    searching.value = true;
    try {
        places.value = (await searchPlaces({ q: query, ...props.work })).data;
        refused.value = places.value.length === 0 ? NOT_FOUND : null;
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        searching.value = false;
    }
}

function choose(place) {
    places.value = [];
    chosen.value = place.label.split(',')[0];
    emit('found', { latitude: place.latitude, longitude: place.longitude });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label for="place-search" class="font-semibold">Dirección o un sitio conocido</label>
        <div class="flex gap-2">
            <input
                id="place-search"
                v-model="text"
                type="text"
                maxlength="120"
                autocomplete="off"
                enterkeyhint="search"
                class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-3 text-base"
                @keydown.enter.prevent="search"
            />
            <button
                type="button"
                :disabled="searching"
                class="min-h-12 shrink-0 rounded-xl border-2 border-brand-700 bg-white px-4 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40"
                @click="search"
            >
                {{ searching ? 'Buscando…' : 'Buscar' }}
            </button>
        </div>
        <p v-if="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ refused }}</p>
        <p v-if="chosen" role="status" class="rounded-lg bg-emerald-50 p-3 text-base font-semibold text-emerald-800">El punto quedó en {{ chosen }}. Muévalo en el mapa si hace falta.</p>
        <ul v-if="places.length" aria-label="Lugares encontrados" class="flex flex-col gap-2">
            <li v-for="place in places" :key="`${place.latitude},${place.longitude}`">
                <button type="button" data-test="place" class="min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-base hover:bg-slate-50" @click="choose(place)">
                    {{ place.label }}
                </button>
            </li>
        </ul>
        <!-- Los datos de OpenStreetMap piden su crédito (ODbL). -->
        <p class="text-sm text-slate-700">
            Búsqueda con datos de <a data-test="osm-credit" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="font-semibold underline">© OpenStreetMap</a>
        </p>
    </div>
</template>
