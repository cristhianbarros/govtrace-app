<script setup>
// US-005: invitar veedores por correo (el enlace vence en 48 horas) y ver
// el equipo con el estado de cada invitación. US-006 y US-041-USR:
// desactivar a un veedor (su sesión se cierra de inmediato) y reactivarlo.
// US-040-USR: reenviar o revocar una invitación que no se ha aceptado.
// It. 43j (V3, US-061-USR): los administradores de la organización, e invitar a otro.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RowAction from '@/Components/RowAction.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { isEmail } from '@/lib/credentials.js';
import { formatDay } from '@/lib/format.js';
import { deactivateObserver, fetchAdministrators, fetchObservers, inviteAdministrator, inviteObserver, reactivateObserver, resendInvitation, revokeInvitation } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: team, loading, error, load } = useLoader(fetchObservers);
const { data: administrators, load: loadAdministrators } = useLoader(fetchAdministrators);

// It. 43j (V3): otro administrador, por si uno pierde el acceso o se va.
const newAdministrator = ref({ name: '', email: '' });
const invitingAdministrator = ref(false);
const administratorRefused = ref(null);

async function inviteAnotherAdministrator() {
    notice.value = null;
    administratorRefused.value = null;
    const { name, email: address } = newAdministrator.value;
    if (name.trim() === '' || !isEmail(address)) {
        administratorRefused.value = 'Escriba el nombre y un correo electrónico válido.';
        return;
    }

    invitingAdministrator.value = true;
    try {
        notice.value = (await inviteAdministrator({ name: name.trim(), email: address.trim() })).message;
        newAdministrator.value = { name: '', email: '' };
        await loadAdministrators();
    } catch (failure) {
        administratorRefused.value = errorMessage(failure);
    } finally {
        invitingAdministrator.value = false;
    }
}

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

onMounted(() => {
    load();
    loadAdministrators();
});
</script>

<template>
    <AdminLayout title="Veedores">
        <form class="flex flex-col gap-2 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5" novalidate @submit.prevent="invite">
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
            <button type="submit" :disabled="sending" class="rounded-xl bg-brand-700 px-3 py-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                {{ sending ? 'Enviando…' : 'Enviar invitación' }}
            </button>
        </form>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <LoadState :loading="loading" :error="error" :empty="team?.length === 0" loading-text="Cargando veedores…" empty-text="Aún no ha invitado veedores." illustration="team" @retry="load">
            <ul class="flex flex-col divide-y divide-slate-100 rounded-lg bg-white">
                <li v-for="member in team" :key="member.id" class="flex flex-col gap-2 px-3 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate">{{ member.email }}</span>
                        <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold" :class="statusStyle[member.status] ?? 'bg-slate-100 text-slate-700'">{{ member.status }}</span>
                    </div>
                    <!-- US-057-LEG (it. 44c): quien aún no activa su cuenta la declara al activarla. -->
                    <p v-if="!INVITATIONS.includes(member.status)" class="text-sm" :class="member.impediments_declared_at ? 'text-slate-600' : 'font-semibold text-amber-800'">
                        {{ member.impediments_declared_at ? `Declaró no tener impedimentos el ${formatDay(member.impediments_declared_at)}` : 'Aún no declara sus impedimentos' }}
                    </p>
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

        <section data-test="administrators" aria-labelledby="administrators-title" class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
            <h2 id="administrators-title" class="text-lg">Administradores</h2>
            <p class="text-sm text-slate-700">Con más de uno, la veeduría no queda sin quién la gestione si alguien pierde el acceso o se va. Desactivar a un administrador lo hace el Super Administrador.</p>
            <ul v-if="administrators?.length" class="flex flex-col divide-y divide-slate-100">
                <li v-for="administrator in administrators" :key="administrator.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span><span class="font-semibold">{{ administrator.name }}</span> · {{ administrator.email }}</span>
                    <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="administrator.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'">{{ administrator.label }}</span>
                </li>
            </ul>
            <form class="flex flex-col gap-2" novalidate @submit.prevent="inviteAnotherAdministrator">
                <p class="text-sm font-semibold text-slate-700">Invitar a otro administrador</p>
                <label for="administrator-name" class="text-sm text-slate-700">Nombre</label>
                <input id="administrator-name" v-model="newAdministrator.name" type="text" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                <label for="administrator-email" class="text-sm text-slate-700">Correo electrónico</label>
                <input id="administrator-email" v-model="newAdministrator.email" type="email" inputmode="email" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                <p v-if="administratorRefused" role="alert" class="text-sm text-red-700">{{ administratorRefused }}</p>
                <button type="submit" :disabled="invitingAdministrator" class="rounded-xl border border-brand-200 bg-white px-3 py-3 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40">
                    {{ invitingAdministrator ? 'Enviando…' : 'Invitar administrador' }}
                </button>
            </form>
        </section>
    </AdminLayout>
</template>
