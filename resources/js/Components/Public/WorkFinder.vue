<script setup>
// It. 59 (US-066): en el Inicio, el ciudadano busca la obra y GovTrace le dice
// qué veedurías pueden atender su informe, con reglas públicas. Sugiere, no
// elige: elegir la veeduría es elegir a quién se le entregan el informe y el
// correo. La ubicación se compara aquí con las cabeceras de los municipios y
// no viaja; la obra elegida no queda en la dirección de la página.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MoreButton from '@/Components/MoreButton.vue';
import { CONTRALORIA } from '@/lib/oversight.js';
import { nearestMunicipality, roughPosition } from '@/lib/nearestMunicipality.js';
import { fetchWhoCanHelp, fetchWorkMunicipalities, searchWorks } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const MIN_WORDS = 3;
const municipalities = ref([]);
const municipality = ref('');
const words = ref('');
const searched = computed(() => (words.value.trim().length >= MIN_WORDS ? words.value.trim() : ''));
const works = ref(null);
const hasMore = ref(false);
const page = ref(1);
const searchError = ref(null);
const loadingMore = ref(false);
const chosen = ref(null); // lo que dice el servidor de la obra elegida
const whoError = ref(null);
const proposal = ref(null); // el municipio que propone la ubicación, por confirmar
const locationError = ref(null);
const municipalitySelect = ref(null);

const params = () => ({ ...(searched.value ? { q: searched.value } : {}), ...(municipality.value ? { municipality: municipality.value } : {}) });

async function search(next = 1) {
    if (!searched.value && !municipality.value) {
        works.value = null;
        return;
    }
    searchError.value = null;
    try {
        const answer = await searchWorks(next > 1 ? { ...params(), page: next } : params());
        works.value = next === 1 ? answer.data : [...works.value, ...answer.data];
        hasMore.value = answer.has_more;
        page.value = next;
    } catch (failure) {
        searchError.value = errorMessage(failure);
    }
}

async function more() {
    loadingMore.value = true;
    await search(page.value + 1);
    loadingMore.value = false;
}

// Se busca cuando la persona deja de escribir un momento; el municipio, de una vez.
let typing = null;
watch(searched, () => {
    clearTimeout(typing);
    typing = setTimeout(() => search(), 400);
});
watch(municipality, () => search());
onBeforeUnmount(() => clearTimeout(typing));

async function choose(work) {
    whoError.value = null;
    try {
        chosen.value = await fetchWhoCanHelp(work.contract_id);
    } catch (failure) {
        whoError.value = errorMessage(failure);
    }
}

async function useMyLocation() {
    locationError.value = null;
    proposal.value = null;
    try {
        proposal.value = nearestMunicipality(municipalities.value, await roughPosition());
        if (!proposal.value) {
            throw new Error('Sin municipios');
        }
    } catch {
        locationError.value = 'No pudimos saber dónde está. Elija su municipio en la lista.';
    }
}

function acceptProposal() {
    municipality.value = proposal.value.code;
    proposal.value = null;
}

function chooseAnother() {
    proposal.value = null;
    municipalitySelect.value?.focus();
}

const suggested = computed(() => (chosen.value?.organizations[0]?.suggested ? chosen.value.organizations[0] : null));
const others = computed(() => (suggested.value ? chosen.value.organizations.slice(1) : chosen.value?.organizations ?? []));

onMounted(async () => {
    try {
        municipalities.value = (await fetchWorkMunicipalities()) ?? [];
    } catch {
        municipalities.value = []; // sin la lista, la búsqueda por nombre sigue sirviendo
    }
});

const FIELD = 'min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base';
const PRIMARY = 'inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-4 text-base font-semibold text-white hover:bg-brand-800';
const SECONDARY = 'inline-flex min-h-12 items-center justify-center rounded-xl border-2 border-brand-700 bg-white px-4 text-base font-semibold text-brand-800 hover:bg-brand-50';
</script>

<template>
    <section id="buscar-obra" aria-labelledby="work-finder-title" class="flex scroll-mt-20 flex-col gap-3 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 md:p-8">
        <h2 id="work-finder-title" class="text-2xl">¿Qué obra quiere reportar?</h2>
        <p class="text-base text-slate-700">Búsquela por su nombre o por el lugar. GovTrace le dice qué veeduría puede atender su informe, y usted elige.</p>

        <template v-if="!chosen">
            <label for="work-words" class="text-base font-semibold">Nombre de la obra</label>
            <input id="work-words" v-model="words" type="search" placeholder="Por ejemplo: parque, colegio, vía a Minca" :class="FIELD" />

            <label for="work-municipality" class="text-base font-semibold">Municipio</label>
            <select id="work-municipality" ref="municipalitySelect" v-model="municipality" :class="FIELD">
                <option value="">Todos los municipios</option>
                <option v-for="place in municipalities" :key="place.code" :value="place.code">{{ place.name }} ({{ place.department }})</option>
            </select>
            <button type="button" :class="SECONDARY" class="self-start" @click="useMyLocation">Usar mi ubicación</button>
            <p class="text-base text-slate-700">Su ubicación se usa en este equipo para proponer el municipio. No se envía a GovTrace.</p>
            <div v-if="proposal" data-test="proposal" class="flex flex-col gap-2 rounded-xl bg-brand-50 p-3">
                <p class="text-base font-semibold">¿Está en {{ proposal.name }} ({{ proposal.department }})?</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="PRIMARY" @click="acceptProposal">Sí, buscar allí</button>
                    <button type="button" :class="SECONDARY" @click="chooseAnother">Elegir otro</button>
                </div>
            </div>
            <p v-if="locationError" role="alert" class="rounded-lg bg-amber-50 p-3 text-base text-amber-900">{{ locationError }}</p>

            <p v-if="searchError" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ searchError }}</p>
            <p v-else-if="works && works.length === 0" class="text-base text-slate-700">No encontramos obras así. Pruebe con otra palabra o con otro municipio.</p>
            <ul v-else-if="works" class="flex flex-col gap-2">
                <li v-for="work in works" :key="work.contract_id" data-test="work">
                    <button type="button" class="flex min-h-16 w-full flex-col items-start justify-center rounded-2xl bg-slate-50 p-3 text-left ring-1 ring-slate-900/5 hover:bg-brand-50" @click="choose(work)">
                        <span class="text-base font-semibold">{{ work.name }}</span> <span class="text-sm text-slate-700">{{ [work.municipality, work.entity_name].filter(Boolean).join(' · ') }}</span>
                    </button>
                </li>
            </ul>
            <MoreButton v-if="works && hasMore" :loading="loadingMore" @more="more" />
            <p v-if="whoError" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ whoError }}</p>
        </template>

        <div v-else data-test="who" class="flex flex-col gap-3">
            <button type="button" class="inline-flex min-h-11 items-center self-start text-base font-semibold text-slate-700" @click="chosen = null">← Volver a los resultados</button>
            <h3 class="text-xl font-semibold">{{ chosen.contract.name }}</h3>
            <p class="text-base text-slate-700">{{ [chosen.contract.municipality ?? chosen.contract.department, chosen.contract.entity_name].filter(Boolean).join(' · ') }}</p>

            <div v-if="chosen.organizations.length === 0" data-test="none" class="flex flex-col gap-2 rounded-2xl border-l-8 border-warm-500 bg-warm-50 p-4 text-base">
                <p class="font-semibold">Ninguna veeduría de GovTrace cubre esta obra todavía.</p>
                <p>Puede acudir a:</p>
                <ul class="flex list-disc flex-col gap-2 pl-5">
                    <li>La Personería de {{ chosen.contract.municipality ?? 'su municipio' }}: allí se inscriben las veedurías ciudadanas y lo pueden orientar (Ley 850 de 2003, art. 3).</li>
                    <li>
                        La Contraloría General de la República:
                        <a v-for="phone in CONTRALORIA.phones" :key="phone.href" :href="phone.href" class="font-semibold underline">{{ phone.label }}</a>,
                        o <a :href="CONTRALORIA.online.href" target="_blank" rel="noopener" class="font-semibold underline">{{ CONTRALORIA.online.label }}</a>.
                    </li>
                    <li>¿Hace parte de una veeduría? <a href="#pedir-alta" class="font-semibold underline">Pida el alta de su veeduría en GovTrace</a></li>
                </ul>
            </div>

            <template v-else>
                <p data-test="rules" class="text-sm text-slate-700">GovTrace las ordena con reglas públicas: primero la que ya sigue la obra, después la que vigila solo ese municipio y después la que publicó evidencias en los últimos 90 días. Usted elige a cuál informar.</p>
                <article v-if="suggested" data-test="suggested" class="flex flex-col gap-2 rounded-2xl border-l-8 border-brand-600 bg-brand-50 p-4">
                    <p class="text-sm font-semibold uppercase tracking-wide text-brand-800">Le sugerimos</p>
                    <p class="font-display text-xl font-semibold text-brand-900">{{ suggested.name }}</p>
                    <ul class="flex list-disc flex-col gap-1 pl-5 text-base">
                        <li v-for="reason in suggested.reasons" :key="reason">{{ reason }}</li>
                    </ul>
                    <a :href="suggested.url" :class="PRIMARY" class="self-start">Informar a esta veeduría</a>
                </article>
                <div v-if="others.length" :data-test="suggested ? 'also' : 'equals'" class="flex flex-col gap-2">
                    <h4 class="text-lg font-semibold">{{ suggested ? 'También cubren esta obra' : 'Estas veedurías cubren esta obra, en igualdad de condiciones' }}</h4>
                    <article v-for="organization in others" :key="organization.url" data-test="organization" class="flex flex-col gap-2 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-900/5">
                        <p class="text-lg font-semibold">{{ organization.name }}</p>
                        <ul class="flex list-disc flex-col gap-1 pl-5 text-base">
                            <li v-for="reason in organization.reasons" :key="reason">{{ reason }}</li>
                        </ul>
                        <a :href="organization.url" :class="SECONDARY" class="self-start">Informar a esta veeduría</a>
                    </article>
                </div>
            </template>
        </div>
    </section>
</template>
