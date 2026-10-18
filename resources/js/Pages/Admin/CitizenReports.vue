<script setup>
// US-059-LEG (it. 44f): lo que los ciudadanos le informan a la veeduría —
// su deber es recibirlo (Ley 850 de 2003, art. 18) —, sin el correo de quien
// lo envió. Se responde desde aquí (al ciudadano le llega por correo) o se
// descarta. No es evidencia sellada: si hace falta, la veeduría manda un veedor.
// It. 58: la ubicación que propuso el ciudadano para una obra sin ubicación —
// con las demás propuestas para la misma obra — se confirma desde aquí.
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import LocationMap from '@/Components/LocationMap.vue';
import MoreButton from '@/Components/MoreButton.vue';
import RowAction from '@/Components/RowAction.vue';
import { usePagedList } from '@/composables/usePagedList.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/format.js';
import { answerCitizenReport, assignCitizenReport, confirmCitizenLocation, discardCitizenReport, fetchCitizenReports } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: reports, meta, loading, loadingMore, error, load, more } = usePagedList(fetchCitizenReports);
// It. 52: de 20 en 20. It. 65a (US-068): como la Bandeja, una pestaña por estado, con su número, y el
// filtro por obra; cada pestaña trae los suyos al tocarla. Abre en "Nuevos": los que esperan.
const TABS = [
    { status: 'new', label: 'Nuevos', empty: 'No hay informes nuevos: nada espera respuesta.' },
    { status: 'assigned', label: 'Visita asignada', empty: 'Ningún informe tiene una visita asignada.' },
    { status: 'documented', label: 'Documentados', empty: 'Ningún informe documentado todavía.' },
    { status: 'answered', label: 'Respondidos', empty: 'Ningún informe respondido.' },
    { status: 'discarded', label: 'Descartados', empty: 'Ningún informe descartado.' },
    { status: null, label: 'Todos', empty: 'Aún no hay informes de ciudadanos.' },
];
const tab = ref(TABS[0]);
const NO_FILTER = { worksite: '', observer: '' }; // it. 65b: y el veedor de la visita
const filters = ref({ ...NO_FILTER });
const params = () => ({
    ...(tab.value.status ? { status: tab.value.status } : {}),
    ...(filters.value.worksite ? { worksite: filters.value.worksite } : {}),
    ...(filters.value.observer ? { observer: filters.value.observer } : {}),
});
const open = (chosen) => {
    tab.value = chosen;
    filters.value = { ...NO_FILTER };
    notice.value = null;
    return load(params());
};
const setFilter = (name, value) => {
    filters.value = { ...filters.value, [name]: value };
    return load(params());
};
const countOf = (candidate) => meta.value?.counts?.[candidate.status ?? 'all'];
const emptyText = computed(() => (filters.value.worksite || filters.value.observer ? 'Ningún informe con ese filtro.' : tab.value.empty));
const notice = ref(null);
const answering = ref(null); // el id del informe que se responde
const answer = ref('');
const refused = ref(null);
const sending = ref(false);

const STATE = {
    new: 'bg-amber-100 text-amber-900',
    assigned: 'bg-sky-100 text-sky-900',
    documented: 'bg-emerald-100 text-emerald-800',
    answered: 'bg-emerald-100 text-emerald-800',
    discarded: 'bg-slate-200 text-slate-700',
};

// It. 65a: el número rojo de "Informes ciudadanos" (it. 40c), al día con cada decisión.
const refreshPending = () => router.reload({ only: ['citizenReportsPending'] });

// It. 65b (US-068): pedirle la visita a un veedor (el último que reportó la obra viene elegido), o pasársela a otro.
const choosing = ref(null); // el id del informe
const chosen = ref('');
const visitRefused = ref(null);
function chooseVeedor(report) {
    choosing.value = report.id;
    chosen.value = report.visit?.observer?.id ?? report.visit?.suggested ?? '';
    visitRefused.value = null;
}
async function askForVisit(report) {
    if (!chosen.value) {
        visitRefused.value = 'Elija al veedor que va a visitar la obra.';
        return;
    }
    sending.value = true;
    try {
        const { message } = await assignCitizenReport(report.id, chosen.value);
        choosing.value = null;
        await done(message);
    } catch (failure) {
        visitRefused.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}

function startAnswer(report) {
    answering.value = report.id;
    answer.value = '';
    refused.value = null;
}

async function sendAnswer() {
    sending.value = true;
    refused.value = null;
    try {
        notice.value = (await answerCitizenReport(answering.value, answer.value.trim())).message;
        answering.value = null;
        refreshPending();
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}

const pointOf = ({ latitude, longitude }) => ({ latitude, longitude });
const othersLabel = (count) => (count === 1 ? 'Gris: otra ubicación que propuso un ciudadano para esta obra.' : `Gris: otras ${count} ubicaciones que propusieron ciudadanos para esta obra.`);

async function done(message) {
    notice.value = message;
    refreshPending();
    await load();
}

onMounted(() => open(TABS[0]));
</script>

<template>
    <AdminLayout title="Informes ciudadanos">
        <p class="text-base text-slate-700">Lo que los ciudadanos le informan a la veeduría desde el mapa. La ley la obliga a recibirlo (Ley 850 de 2003, art. 18). No se publica ni se sella: si hace falta, pídale a un veedor que lo documente, y su evidencia sí. El correo del ciudadano no se muestra: la respuesta le llega desde GovTrace.</p>
        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <!-- It. 65a: como la Bandeja, una pestaña por estado, con su número. -->
        <div class="grid grid-cols-2 gap-1 rounded-lg bg-slate-200 p-1 md:grid-cols-3 lg:flex" role="tablist" aria-label="Estado de los informes">
            <button
                v-for="candidate in TABS"
                :key="candidate.label"
                type="button"
                role="tab"
                :aria-selected="tab.label === candidate.label"
                class="min-h-11 flex-1 rounded-md px-2 py-2 text-sm font-semibold"
                :class="tab.label === candidate.label ? 'bg-white shadow-sm' : 'text-slate-700'"
                @click="open(candidate)"
            >{{ candidate.label }} <span v-if="countOf(candidate) !== undefined" class="rounded-full bg-slate-100 px-2 text-xs text-slate-700">{{ countOf(candidate) }}</span></button>
        </div>
        <div v-if="meta?.filters?.worksites?.length || meta?.filters?.observers?.length" class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <label v-if="meta.filters.worksites?.length" for="filter-worksite" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Obra
                <select id="filter-worksite" :value="filters.worksite" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('worksite', $event.target.value)">
                    <option value="">Todas</option>
                    <option v-for="candidate in meta.filters.worksites" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option>
                </select>
            </label>
            <!-- It. 65b: el veedor de la visita. -->
            <label v-if="meta.filters.observers?.length" for="filter-observer" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Veedor
                <select id="filter-observer" :value="filters.observer" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal" @change="setFilter('observer', $event.target.value)">
                    <option value="">Todos</option>
                    <option v-for="candidate in meta.filters.observers" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option>
                </select>
            </label>
        </div>
        <p v-if="meta && meta.total > 0" data-test="total" class="text-base text-slate-700">{{ meta.total }} {{ meta.total === 1 ? 'informe' : 'informes' }}</p>

        <LoadState :loading="loading" :error="error" :empty="reports?.length === 0" loading-text="Cargando informes…" :empty-text="emptyText" illustration="messages" @retry="load">
            <div class="flex flex-col gap-3">
                <article v-for="report in reports" :key="report.id" data-test="citizen-report" class="flex flex-col gap-2 rounded-2xl bg-white p-3 text-base shadow-soft ring-1 ring-slate-900/5">
                    <header class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold">{{ report.worksite }}</span>
                        <span class="rounded px-2 py-0.5 text-sm font-semibold" :class="STATE[report.status]">{{ report.status_label }}</span>
                    </header>
                    <p class="text-sm text-slate-600">Informe n.º {{ report.reference }} · {{ formatDateTime(report.received_at) }}</p>
                    <p class="whitespace-pre-line">{{ report.message }}</p>
                    <!-- It. 46h: de 1 a 3 fotos. -->
                    <div v-if="report.photo_urls.length" class="flex flex-wrap gap-2">
                        <a v-for="(url, index) in report.photo_urls" :key="url" :href="url" target="_blank" rel="noopener">
                            <img :src="url" :alt="`Foto ${index + 1} de ${report.photo_urls.length} que envió el ciudadano`" class="size-32 rounded-lg object-cover" />
                        </a>
                    </div>
                    <p v-if="report.answer" class="rounded-lg bg-slate-50 p-2"><strong>Respuesta:</strong> {{ report.answer }}</p>
                    <!-- It. 58: dónde dice el ciudadano que queda la obra. -->
                    <div v-if="report.proposed_location" data-test="proposed-location" class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 ring-1 ring-amber-200">
                        <p class="font-semibold text-amber-900">📍 El ciudadano dice que la obra queda aquí.</p>
                        <LocationMap :model-value="pointOf(report.proposed_location)" :others="report.proposed_location.others" label="Donde el ciudadano dice que queda la obra" readonly />
                        <p v-if="report.proposed_location.others.length" data-test="other-proposals" class="text-base text-slate-700">{{ othersLabel(report.proposed_location.others.length) }}</p>
                        <p v-if="report.proposed_location.status === 'confirmed'" class="font-semibold text-emerald-800">Ubicación confirmada aquí.</p>
                        <p v-else-if="report.proposed_location.status === 'elsewhere'" class="font-semibold text-slate-800">La obra ya tiene ubicación oficial, en otro lugar.</p>
                        <template v-else-if="report.status !== 'discarded'">
                            <p class="text-base text-slate-800">La obra aún no tiene ubicación oficial. Si no está seguro de este punto, pídale a un veedor que la visite: su primer reporte la fija.</p>
                            <RowAction label="Confirmar esta ubicación" confirm-label="Sí, confirmar" warning="La obra aparecerá en el mapa en este punto, y la geocerca de los veedores se medirá desde aquí." :run="() => confirmCitizenLocation(report.id)" @done="done" />
                        </template>
                    </div>

                    <!-- It. 65b (US-068): la visita de un veedor. -->
                    <p v-if="report.visit?.note" data-test="visit-note" class="rounded-lg bg-amber-50 p-2 font-semibold text-amber-900">{{ report.visit.note }}</p>
                    <div v-if="report.status === 'assigned'" data-test="visit-assigned" class="flex flex-col gap-2 rounded-lg bg-sky-50 p-3 ring-1 ring-sky-200">
                        <p class="font-semibold text-sky-900">Visita asignada a {{ report.visit?.observer?.name }}</p>
                        <p v-if="report.visit?.reported" class="text-base text-slate-800">{{ report.visit.observer.name }} ya envió su reporte: revíselo en la Bandeja.</p>
                        <p v-else class="text-base text-slate-800">Cuando reporte la obra, su evidencia llega a la Bandeja. Al publicarla, el informe queda documentado y al ciudadano le llega el enlace.</p>
                        <button v-if="choosing !== report.id" type="button" class="min-h-11 self-start rounded-xl border-2 border-brand-700 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50" @click="chooseVeedor(report)">Cambiar de veedor</button>
                    </div>
                    <div v-if="choosing === report.id" class="flex flex-col gap-2">
                        <label :for="`visit-${report.id}`" class="font-semibold">¿A qué veedor le pide la visita?</label>
                        <select :id="`visit-${report.id}`" v-model="chosen" data-test="visit-observer" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base md:max-w-md">
                            <option value="">Elija al veedor</option>
                            <option v-for="candidate in meta?.observers ?? []" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option>
                        </select>
                        <p class="text-sm text-slate-700">El veedor la ve en su "Nuevo reporte", con el mensaje y las fotos del ciudadano. Al ciudadano le avisamos que un veedor va a visitar la obra, sin su nombre.</p>
                        <p v-if="visitRefused" role="alert" class="text-sm text-red-700">{{ visitRefused }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" :disabled="sending" class="min-h-11 rounded-xl bg-brand-700 px-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800" @click="askForVisit(report)">Pedir la visita</button>
                            <button type="button" class="min-h-11 rounded-xl border border-brand-200 px-3 font-semibold text-brand-800 hover:bg-brand-50" @click="choosing = null">Cancelar</button>
                        </div>
                    </div>

                    <template v-if="report.status === 'new' && choosing !== report.id">
                        <div v-if="answering === report.id" class="flex flex-col gap-2">
                            <label :for="`answer-${report.id}`" class="font-semibold">Respuesta para el ciudadano</label>
                            <textarea :id="`answer-${report.id}`" v-model="answer" rows="3" maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                            <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" :disabled="sending || answer.trim().length < 5" class="min-h-11 rounded-xl bg-brand-700 px-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800" @click="sendAnswer">Enviar respuesta</button>
                                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 font-semibold" @click="answering = null">Cancelar</button>
                            </div>
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button type="button" class="min-h-11 rounded-xl bg-brand-700 px-3 font-semibold text-white hover:bg-brand-800" @click="chooseVeedor(report)">Pedir a un veedor que lo documente</button>
                            <button type="button" class="min-h-11 rounded-xl border-2 border-brand-700 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50" @click="startAnswer(report)">Responder</button>
                            <RowAction label="Descartar" confirm-label="Confirmar descarte" warning="Al ciudadano no le llega ningún correo." :run="() => discardCitizenReport(report.id)" @done="done" />
                        </div>
                    </template>
                </article>
                <MoreButton v-if="meta?.has_more" :loading="loadingMore" @more="more" />
            </div>
        </LoadState>
    </AdminLayout>
</template>
