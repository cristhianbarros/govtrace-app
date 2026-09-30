<script setup>
// US-035: las obras de la organización y su ubicación oficial — de donde se
// calcula la geocerca de los veedores —, para corregirla arrastrando el pin
// o escribiendo la latitud y la longitud. Cada corrección queda en el log.
import { computed, onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import LocationMap from '@/Components/LocationMap.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { correctWorksiteLocation, fetchWorksites, groupContracts } from '@/services/api.js';
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

// US-045-INT: agrupar contratos en una ficha — un nombre y dos o más contratos, uno por línea.
const groupName = ref('');
const groupIds = ref('');
const grouping = ref(false);
const groupRefused = ref(null);
const idsToGroup = computed(() => [...new Set(groupIds.value.split('\n').map((id) => id.trim()).filter(Boolean))]);

function addToGroup(secopContractId) {
    groupIds.value = [...idsToGroup.value, secopContractId].filter((id, index, all) => all.indexOf(id) === index).join('\n');
}

async function group() {
    grouping.value = true;
    groupRefused.value = null;
    saved.value = null;
    try {
        const { data } = await groupContracts(groupName.value.trim(), idsToGroup.value);
        saved.value = `Contratos agrupados en la ficha «${data.name}».`;
        groupName.value = '';
        groupIds.value = '';
        await load();
    } catch (failure) {
        groupRefused.value = errorMessage(failure);
    } finally {
        grouping.value = false;
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
            <p class="text-sm text-slate-700"><strong>Descargar expediente:</strong> Un ZIP con las evidencias publicadas, sus pruebas y las plantillas del derecho de petición y de la denuncia ante la Contraloría.</p>
            <div class="flex flex-col gap-3">
                <article v-for="worksite in worksites" :key="worksite.id" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
                    <p v-if="worksite.name" class="text-base font-semibold">{{ worksite.name }}</p>
                    <ul class="flex flex-col gap-1">
                        <li v-for="contract in worksite.contracts" :key="contract.secop_contract_id" class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold">{{ contract.object }}</span>
                            <button type="button" class="min-h-11 rounded-lg border px-3 text-sm font-semibold" @click="addToGroup(contract.secop_contract_id)">Unir con otra obra</button>
                        </li>
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
                    <button v-else type="button" class="min-h-11 self-start rounded-lg border px-3 py-2 font-semibold" @click="correct(worksite)">Corregir ubicación</button>
                    <!-- US-056-LEG (it. 44b): lo que la veeduría lleva a la entidad y a la Contraloría. -->
                    <a data-test="dossier" :href="`/worksites/${worksite.id}/dossier.zip`" class="inline-flex min-h-11 items-center self-start rounded-lg border-2 border-slate-900 bg-white px-3 font-semibold text-slate-900">Descargar expediente</a>
                </article>
            </div>
        </LoadState>

        <section aria-labelledby="group-title" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-sm">
            <h2 id="group-title" class="font-semibold">Unir contratos de una misma obra</h2>
            <p class="text-slate-600">Para que una obra con varias fases o reinicios se vea y se reporte como una sola. Un reporte nunca cambia de ficha: dos fichas que ya tienen reportes no se unen.</p>
            <label for="group-name" class="text-xs font-semibold text-slate-700">Nombre de la ficha</label>
            <input id="group-name" v-model="groupName" type="text" maxlength="150" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
            <label for="group-contracts" class="text-xs font-semibold text-slate-700">Contratos de SECOP II (uno por línea)</label>
            <textarea id="group-contracts" v-model="groupIds" rows="3" class="rounded-lg border border-slate-300 px-3 py-2 font-mono text-base"></textarea>
            <p v-if="groupRefused" role="alert" class="rounded bg-red-50 p-2 text-red-800">{{ groupRefused }}</p>
            <button type="button" :disabled="grouping || !groupName.trim() || idsToGroup.length < 2" class="min-h-11 self-start rounded-lg bg-slate-900 px-4 font-semibold text-white disabled:opacity-40" @click="group">Agrupar</button>
        </section>
    </AdminLayout>
</template>
