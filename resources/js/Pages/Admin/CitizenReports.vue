<script setup>
// US-059-LEG (it. 44f): lo que los ciudadanos le informan a la veeduría —
// su deber es recibirlo (Ley 850 de 2003, art. 18) —, sin el correo de quien
// lo envió. Se responde desde aquí (al ciudadano le llega por correo) o se
// descarta. No es evidencia sellada: si hace falta, la veeduría manda un veedor.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RowAction from '@/Components/RowAction.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/format.js';
import { answerCitizenReport, discardCitizenReport, fetchCitizenReports } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: reports, loading, error, load } = useLoader(fetchCitizenReports);
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

        <LoadState :loading="loading" :error="error" :empty="reports?.length === 0" loading-text="Cargando informes…" empty-text="Aún no hay informes de ciudadanos." @retry="load">
            <div class="flex flex-col gap-3">
                <article v-for="report in reports" :key="report.id" data-test="citizen-report" class="flex flex-col gap-2 rounded-lg bg-white p-3 text-base">
                    <header class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold">{{ report.worksite }}</span>
                        <span class="rounded px-2 py-0.5 text-sm font-semibold" :class="STATE[report.status]">{{ report.status_label }}</span>
                    </header>
                    <p class="text-sm text-slate-600">Informe n.º {{ report.id }} · {{ formatDateTime(report.received_at) }}</p>
                    <p class="whitespace-pre-line">{{ report.message }}</p>
                    <a v-if="report.photo_url" :href="report.photo_url" target="_blank" rel="noopener" class="self-start">
                        <img :src="report.photo_url" alt="Foto que envió el ciudadano" class="size-32 rounded-lg object-cover" />
                    </a>
                    <p v-if="report.answer" class="rounded-lg bg-slate-50 p-2"><strong>Respuesta:</strong> {{ report.answer }}</p>

                    <template v-if="report.status === 'new'">
                        <div v-if="answering === report.id" class="flex flex-col gap-2">
                            <label :for="`answer-${report.id}`" class="font-semibold">Respuesta para el ciudadano</label>
                            <textarea :id="`answer-${report.id}`" v-model="answer" rows="3" maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                            <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" :disabled="sending || answer.trim().length < 5" class="min-h-11 rounded-lg bg-slate-900 px-3 font-semibold text-white disabled:opacity-40" @click="sendAnswer">Enviar respuesta</button>
                                <button type="button" class="min-h-11 rounded-lg border px-3 font-semibold" @click="answering = null">Cancelar</button>
                            </div>
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button type="button" class="min-h-11 rounded-lg border-2 border-slate-900 bg-white px-3 font-semibold" @click="startAnswer(report)">Responder</button>
                            <RowAction label="Descartar" confirm-label="Confirmar descarte" warning="Al ciudadano no le llega ningún correo." :run="() => discardCitizenReport(report.id)" @done="done" />
                        </div>
                    </template>
                </article>
            </div>
        </LoadState>
    </AdminLayout>
</template>
