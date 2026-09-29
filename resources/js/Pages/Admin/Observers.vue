<script setup>
// US-005: invitar veedores por correo (el enlace vence en 48 horas) y ver
// el equipo con el estado de cada invitación. US-006 y US-041-USR:
// desactivar a un veedor (su sesión se cierra de inmediato) y reactivarlo.
// US-040-USR: reenviar o revocar una invitación que no se ha aceptado.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RowAction from '@/Components/RowAction.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { isEmail } from '@/lib/credentials.js';
import { deactivateObserver, fetchObservers, inviteObserver, reactivateObserver, resendInvitation, revokeInvitation } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: team, loading, error, load } = useLoader(fetchObservers);

const email = ref('');
const sending = ref(false);
const notice = ref(null);
const refused = ref(null);

async function invite() {
    notice.value = null;
    refused.value = null;
    if (!isEmail(email.value)) {
        refused.value = 'Escriba un correo electrónico válido.';
        return;
    }

    sending.value = true;
    try {
        notice.value = (await inviteObserver(email.value.trim())).message;
        email.value = '';
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}

async function changed(message) {
    notice.value = message;
    await load();
}

// Una invitación sin aceptar, vigente o vencida: se reenvía o se revoca (US-040-USR).
const INVITATIONS = ['Invitación pendiente', 'Invitación vencida'];

const statusStyle = {
    Activo: 'bg-emerald-100 text-emerald-800',
    'Invitación pendiente': 'bg-amber-100 text-amber-900',
};

onMounted(load);
</script>

<template>
    <AdminLayout title="Veedores">
        <form class="flex flex-col gap-2 rounded-lg bg-white p-3" novalidate @submit.prevent="invite">
            <label for="invite-email" class="text-sm font-semibold text-slate-700">Invitar un veedor de campo</label>
            <input
                id="invite-email"
                v-model="email"
                type="email"
                inputmode="email"
                autocomplete="off"
                placeholder="correo@ejemplo.co"
                class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base"
            />
            <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
            <button type="submit" :disabled="sending" class="rounded-lg bg-slate-900 px-3 py-3 font-semibold text-white disabled:opacity-40">
                {{ sending ? 'Enviando…' : 'Enviar invitación' }}
            </button>
        </form>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <LoadState :loading="loading" :error="error" :empty="team?.length === 0" loading-text="Cargando veedores…" empty-text="Aún no ha invitado veedores." @retry="load">
            <ul class="flex flex-col divide-y divide-slate-100 rounded-lg bg-white">
                <li v-for="member in team" :key="member.id" class="flex flex-col gap-2 px-3 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate">{{ member.email }}</span>
                        <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold" :class="statusStyle[member.status] ?? 'bg-slate-100 text-slate-700'">{{ member.status }}</span>
                    </div>
                    <RowAction
                        v-if="member.status === 'Inactivo'"
                        label="Reactivar"
                        :run="() => reactivateObserver(member.id)"
                        @done="changed"
                    />
                    <div v-else-if="INVITATIONS.includes(member.status)" class="flex flex-wrap gap-2">
                        <RowAction label="Reenviar invitación" :run="() => resendInvitation(member.id)" @done="changed" />
                        <RowAction
                            label="Revocar invitación"
                            confirm-label="Confirmar revocación"
                            warning="El enlace enviado dejará de funcionar. Podrá invitar ese correo de nuevo."
                            :run="() => revokeInvitation(member.id)"
                            @done="changed"
                        />
                    </div>
                    <RowAction
                        v-else
                        label="Desactivar"
                        confirm-label="Confirmar desactivación"
                        warning="Su sesión se cerrará de inmediato y no podrá enviar reportes. Sus reportes anteriores se conservan."
                        :run="() => deactivateObserver(member.id)"
                        @done="changed"
                    />
                </li>
            </ul>
        </LoadState>
    </AdminLayout>
</template>
