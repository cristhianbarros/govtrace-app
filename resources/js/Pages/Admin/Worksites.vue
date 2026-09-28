<script setup>
// US-035: las obras de la organización y su ubicación oficial — de donde se
// calcula la geocerca de los veedores —, para corregirla arrastrando el pin
// o escribiendo la latitud y la longitud. Cada corrección queda en el log.
import { computed, onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import LocationMap from '@/Components/LocationMap.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { correctWorksiteLocation, fetchWorksites } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

// Sin ubicación todavía, el mapa abre sobre Colombia.
const COLOMBIA = { latitude: 4.5709, longitude: -74.2973 };

const { data: worksites, loading, error, load } = useLoader(fetchWorksites);

const editing = ref(null); // id de la obra que se corrige
const latitude = ref('');
const longitude = ref('');
const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

const typed = computed(() => {
    const point = { latitude: Number.parseFloat(latitude.value), longitude: Number.parseFloat(longitude.value) };
    return Number.isFinite(point.latitude) && Number.isFinite(point.longitude) ? point : null;
});

const pin = computed({
    get: () => typed.value ?? COLOMBIA,
    set: (point) => {
        latitude.value = String(point.latitude);
        longitude.value = String(point.longitude);
    },
});

function correct(worksite) {
    editing.value = worksite.id;
    latitude.value = worksite.latitude === null ? '' : String(worksite.latitude);
    longitude.value = worksite.longitude === null ? '' : String(worksite.longitude);
    saved.value = null;
    refused.value = null;
}

async function save() {
    saving.value = true;
    refused.value = null;
    try {
        saved.value = (await correctWorksiteLocation(editing.value, typed.value)).message;
        editing.value = null;
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        saving.value = false;
    }
}

const where = (worksite) =>
    worksite.latitude === null ? 'Sin ubicación oficial' : `${worksite.latitude.toFixed(7)}, ${worksite.longitude.toFixed(7)}`;

onMounted(load);
</script>

<template>
    <AdminLayout title="Obras">
        <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="worksites?.length === 0"
            loading-text="Cargando obras…"
            empty-text="Aún no hay obras. Una obra aparece aquí con el primer reporte de uno de sus contratos."
            @retry="load"
        >
            <div class="flex flex-col gap-3">
                <article v-for="worksite in worksites" :key="worksite.id" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
                    <ul>
                        <li v-for="contract in worksite.contracts" :key="contract.secop_contract_id" class="font-semibold">{{ contract.object }}</li>
                    </ul>
                    <p class="text-slate-600">Ubicación oficial: {{ where(worksite) }}</p>

                    <div v-if="editing === worksite.id" class="flex flex-col gap-3">
                        <LocationMap v-model="pin" />
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex flex-col gap-1">
                                <label for="latitude" class="text-xs font-semibold text-slate-700">Latitud</label>
                                <input id="latitude" v-model="latitude" type="text" inputmode="decimal" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="longitude" class="text-xs font-semibold text-slate-700">Longitud</label>
                                <input id="longitude" v-model="longitude" type="text" inputmode="decimal" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            </div>
                        </div>
                        <p v-if="refused" role="alert" class="rounded bg-red-50 p-2 text-red-800">{{ refused }}</p>
                        <div class="flex gap-2">
                            <button type="button" :disabled="!typed || saving" class="flex-1 rounded-lg bg-slate-900 px-3 py-3 font-semibold text-white disabled:opacity-40" @click="save">
                                Guardar ubicación
                            </button>
                            <button type="button" class="rounded-lg border px-3 py-3 font-semibold" @click="editing = null">Cancelar</button>
                        </div>
                    </div>
                    <button v-else type="button" class="self-start rounded-lg border px-3 py-2 font-semibold" @click="correct(worksite)">Corregir ubicación</button>
                </article>
            </div>
        </LoadState>
    </AdminLayout>
</template>
