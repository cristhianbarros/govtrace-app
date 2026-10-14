<script setup>
// US-059-LEG (it. 44f): lo que los ciudadanos le informan a la veeduría —
// su deber es recibirlo (Ley 850 de 2003, art. 18) —, sin el correo de quien
// lo envió. Se responde desde aquí (al ciudadano le llega por correo) o se
// descarta. No es evidencia sellada: si hace falta, la veeduría manda un veedor.
// It. 58: la ubicación que propuso el ciudadano para una obra sin ubicación —
// con las demás propuestas para la misma obra — se confirma desde aquí.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import LocationMap from '@/Components/LocationMap.vue';
import MoreButton from '@/Components/MoreButton.vue';
import RowAction from '@/Components/RowAction.vue';
import { usePagedList } from '@/composables/usePagedList.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/format.js';
import { answerCitizenReport, confirmCitizenLocation, discardCitizenReport, fetchCitizenReports } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: reports, meta, loading, loadingMore, error, load, more } = usePagedList(fetchCitizenReports);
// It. 52: de 20 en 20, y por estado (los nuevos son los que esperan respuesta).
const STATUSES = [
    { value: '', label: 'Todos' },
    { value: 'new', label: 'Por responder' },
    { value: 'answered', label: 'Atendidos' },
    { value: 'discarded', label: 'Descartados' },
];
const status = ref('');
const byStatus = (value) => {
    status.value = value;
    return load(value === '' ? {} : { status: value });
};
const notice = ref(null);
const answering = ref(null); // el id del informe que se responde
const answer = ref('');
const refused = ref(null);
const sending = ref(false);

const STATE = {
    new: 'bg-amber-100 text-amber-900',
    answered: 'bg-emerald-100 text-emerald-800',
    discarded: 'bg-slate-200 text-slate-700',
};

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
    await load();
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Informes ciudadanos">
        <p class="text-base text-slate-700">Lo que los ciudadanos le informan a la veeduría desde el mapa. La ley la obliga a recibirlo (Ley 850 de 2003, art. 18). No se publica ni se sella; su correo no se muestra: la respuesta le llega desde GovTrace.</p>
        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <label for="status" class="flex flex-col gap-1 text-sm font-semibold text-slate-700">Estado
            <select id="status" :value="status" class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base font-normal md:max-w-xs" @change="byStatus($event.target.value)">
                <option v-for="option in STATUSES" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </label>
        <p v-if="meta && meta.total > 0" data-test="total" class="text-base text-slate-700">{{ meta.total }} {{ meta.total === 1 ? 'informe' : 'informes' }}</p>

        <LoadState :loading="loading" :error="error" :empty="reports?.length === 0" loading-text="Cargando informes…" :empty-text="status === '' ? 'Aún no hay informes de ciudadanos.' : 'Ningún informe con ese estado.'" illustration="messages" @retry="load">
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

                    <template v-if="report.status === 'new'">
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
