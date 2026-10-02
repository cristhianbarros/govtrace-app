<script setup>
// It. 46a (US-063-USR): varios Super Administradores, y nunca ninguno. Quiénes
// son y en qué va cada uno; invitar a otro; desactivar al que se fue (nunca a
// uno mismo) y reactivarlo; reenviar o revocar una invitación. Las reglas son
// del servidor: si dice que no, el motivo queda en la fila.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RowAction from '@/Components/RowAction.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import {
    deactivateSuperAdministrator,
    fetchSuperAdministrators,
    inviteSuperAdministrator,
    reactivateSuperAdministrator,
    resendSuperAdministratorInvitation,
    revokeSuperAdministratorInvitation,
} from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const STYLE = {
    active: 'bg-emerald-100 text-emerald-900',
    pending: 'bg-amber-100 text-amber-900',
    expired: 'bg-amber-100 text-amber-900',
    inactive: 'bg-slate-200 text-slate-800',
};

const { data: superAdministrators, loading, error, load } = useLoader(fetchSuperAdministrators);

const inviting = ref(false);
const invited = ref({ name: '', email: '' });
const inviteError = ref(null);
const sending = ref(false);
const notice = ref(null);

function startInviting() {
    inviting.value = true;
    invited.value = { name: '', email: '' };
    inviteError.value = null;
}

async function invite() {
    sending.value = true;
    inviteError.value = null;
    try {
        notice.value = (await inviteSuperAdministrator(invited.value.name.trim(), invited.value.email.trim())).message;
        inviting.value = false;
        await load();
    } catch (failure) {
        inviteError.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}

async function changed(message) {
    notice.value = message ?? null;
    await load();
}

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Super Administradores">
        <p class="text-base text-slate-700">
            Para no depender de una sola persona, conviene tener al menos dos. Nadie se desactiva a sí mismo, y la plataforma nunca se queda sin uno activo.
        </p>
        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-base font-semibold text-emerald-800">{{ notice }}</p>

        <LoadState :loading="loading" :error="error" :empty="superAdministrators?.length === 0" loading-text="Cargando Super Administradores…" empty-text="Aún no hay Super Administradores." illustration="records" @retry="load">
            <ul class="flex flex-col gap-3">
                <li
                    v-for="superAdmin in superAdministrators"
                    :key="superAdmin.id"
                    data-test="super-admin"
                    class="flex flex-col gap-2 rounded-2xl bg-white p-3 text-base shadow-soft ring-1 ring-slate-900/5"
                >
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ superAdmin.name }}</span>
                        <span v-if="superAdmin.is_me" class="rounded bg-brand-100 px-2 py-0.5 text-sm font-semibold text-brand-900">Usted</span>
                        <span class="rounded px-2 py-0.5 text-sm font-semibold" :class="STYLE[superAdmin.status]">{{ superAdmin.label }}</span>
                    </p>
                    <p class="break-all text-slate-700">{{ superAdmin.email }}</p>

                    <div v-if="superAdmin.status === 'pending' || superAdmin.status === 'expired'" class="flex flex-wrap gap-2">
                        <RowAction label="Reenviar invitación" :run="() => resendSuperAdministratorInvitation(superAdmin.id)" @done="changed" />
                        <RowAction
                            label="Revocar invitación"
                            confirm-label="Confirmar revocación"
                            warning="El enlace enviado dejará de servir."
                            :run="() => revokeSuperAdministratorInvitation(superAdmin.id)"
                            @done="changed"
                        />
                    </div>
                    <RowAction
                        v-else-if="superAdmin.status === 'active' && !superAdmin.is_me"
                        label="Desactivar"
                        confirm-label="Confirmar desactivación"
                        warning="Ya no podrá entrar al panel global. Lo que hizo queda en el registro de auditoría."
                        :run="() => deactivateSuperAdministrator(superAdmin.id)"
                        @done="changed"
                    />
                    <RowAction v-else-if="superAdmin.status === 'inactive'" label="Reactivar" :run="() => reactivateSuperAdministrator(superAdmin.id)" @done="changed" />
                </li>
            </ul>
        </LoadState>

        <form v-if="inviting" data-test="invite" class="flex flex-col gap-2 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5" novalidate @submit.prevent="invite">
            <h2 class="text-lg font-semibold">Invitar a otro Super Administrador</h2>
            <label for="super-admin-name" class="text-base font-semibold text-slate-700">Nombre</label>
            <input id="super-admin-name" v-model="invited.name" type="text" autocomplete="off" maxlength="120" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            <label for="super-admin-email" class="text-base font-semibold text-slate-700">Correo electrónico</label>
            <input id="super-admin-email" v-model="invited.email" type="email" autocomplete="off" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
            <p class="text-sm text-slate-600">Le llega un correo con un enlace para crear su contraseña.</p>
            <p v-if="inviteError" role="alert" class="text-base text-red-700">{{ inviteError }}</p>
            <div class="flex gap-2">
                <button type="submit" :disabled="sending" class="min-h-11 rounded-xl bg-brand-700 px-3 text-base font-semibold text-white hover:bg-brand-800 disabled:opacity-40">Enviar invitación</button>
                <button type="button" class="min-h-11 rounded-xl border border-brand-200 bg-white px-3 text-base font-semibold text-brand-800 hover:bg-brand-50" @click="inviting = false">Cancelar</button>
            </div>
        </form>
        <button v-else type="button" class="min-h-11 self-start rounded-xl bg-brand-700 px-3 text-base font-semibold text-white hover:bg-brand-800" @click="startInviting">
            Invitar a otro Super Administrador
        </button>
    </SuperAdminLayout>
</template>
