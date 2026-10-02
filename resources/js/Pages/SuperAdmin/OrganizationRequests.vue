<script setup>
// It. 43k (V10, US-062-ALT): las veedurías que pidieron su alta desde el
// Inicio. Aprobar lleva a la Nueva organización precargada (US-001); al
// registrarla, la solicitud queda aprobada. Rechazar pide un motivo, que le
// llega por correo a quien la pidió. It. 46b: junto a cada una, lo que dicen
// los datos abiertos del RUES y el PDF que adjuntó; si el RUES no responde,
// se decide con el PDF.
import { Link } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RuesAnswer from '@/Components/SuperAdmin/RuesAnswer.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { formatDay } from '@/lib/format.js';
import { fetchOrganizationRequestRues, fetchOrganizationRequests, rejectOrganizationRequest } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const REASON_REQUIRED = 'Escriba el motivo: le llega a quien pidió el alta.';

const { data: requests, loading, error, load } = useLoader(fetchOrganizationRequests);

const UNAVAILABLE = { status: 'unavailable', message: 'No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde.', records: [] };
const rues = reactive({}); // id de la solicitud → { loading, answer }

async function askRues(request) {
    if (rues[request.id]) {
        return;
    }
    rues[request.id] = { loading: true, answer: null };
    try {
        rues[request.id] = { loading: false, answer: (await fetchOrganizationRequestRues(request.id)) ?? null };
    } catch {
        rues[request.id] = { loading: false, answer: UNAVAILABLE };
    }
}

async function loadAll() {
    await load();
    (requests.value ?? []).forEach(askRues);
}

const rejecting = ref(null); // el id de la solicitud
const reason = ref('');
const refused = ref(null);
const notice = ref(null);
const busy = ref(false);

function startRejecting(request) {
    rejecting.value = request.id;
    reason.value = '';
    refused.value = null;
}

async function reject(request) {
    refused.value = null;
    if (reason.value.trim() === '') {
        refused.value = REASON_REQUIRED;
        return;
    }
    busy.value = true;
    try {
        notice.value = (await rejectOrganizationRequest(request.id, reason.value.trim())).message;
        rejecting.value = null;
        await loadAll();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

onMounted(loadAll);
</script>

<template>
    <SuperAdminLayout title="Solicitudes de alta">
        <p class="text-base text-slate-700">Las veedurías que pidieron publicar en GovTrace desde el Inicio. No es autorregistro: cada una se aprueba, dándola de alta, o se rechaza con un motivo.</p>
        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <LoadState :loading="loading" :error="error" :empty="requests?.length === 0" loading-text="Cargando solicitudes…" empty-text="No hay solicitudes de alta pendientes." illustration="inbox-done" @retry="loadAll">
            <ul class="flex flex-col gap-3">
                <li v-for="request in requests" :key="request.id" data-test="organization-request" class="flex flex-col gap-2 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5">
                    <p class="font-display text-lg font-semibold text-brand-900">{{ request.name }}</p>
                    <dl class="grid grid-cols-1 gap-1 text-sm md:grid-cols-[auto_1fr] md:gap-x-4">
                        <dt class="font-semibold text-slate-700">Correo de contacto</dt><dd>{{ request.contact_email }}</dd>
                        <dt class="font-semibold text-slate-700">Inscripción</dt><dd>{{ request.registration_number }}, {{ request.registration_authority }}</dd>
                        <dt class="font-semibold text-slate-700">Recibida</dt><dd>{{ formatDay(request.received_at) }}</dd>
                    </dl>
                    <a v-if="request.has_document" :href="`/admin/organization-requests/${request.id}/document`" class="inline-flex min-h-11 items-center self-start text-sm font-semibold text-brand-800 underline">Descargar el PDF que adjuntó</a>
                    <RuesAnswer :loading="rues[request.id]?.loading ?? false" :answer="rues[request.id]?.answer ?? null" />
                    <div v-if="rejecting === request.id" class="flex flex-col gap-2">
                        <label :for="`reject-reason-${request.id}`" class="text-sm font-semibold text-slate-700">Motivo del rechazo (le llega por correo)</label>
                        <textarea :id="`reject-reason-${request.id}`" v-model="reason" rows="3" maxlength="1000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                        <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" :disabled="busy" class="min-h-11 rounded-xl border border-red-700 bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-40" @click="reject(request)">Confirmar rechazo</button>
                            <button type="button" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-800 hover:bg-brand-50" @click="rejecting = null">Cancelar</button>
                        </div>
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <Link :href="`/admin/organizations/new?request=${request.id}`" class="inline-flex min-h-11 items-center rounded-xl bg-brand-700 px-3 text-sm font-semibold text-white hover:bg-brand-800">Aprobar y dar de alta</Link>
                        <button type="button" class="min-h-11 rounded-xl border border-red-300 bg-white px-3 text-sm font-semibold text-red-800 hover:bg-red-50" @click="startRejecting(request)">Rechazar</button>
                    </div>
                </li>
            </ul>
        </LoadState>
    </SuperAdminLayout>
</template>
