<script setup>
// US-005: invitar veedores por correo (el enlace vence en 48 horas) y ver
// el equipo con el estado de cada invitación.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { isEmail } from '@/lib/credentials.js';
import { fetchObservers, inviteObserver } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: team, loading, error, load } = useLoader(fetchObservers);

const email = ref('');
const sending = ref(false);
const sent = ref(null);
const refused = ref(null);

async function invite() {
    sent.value = null;
    refused.value = null;
    if (!isEmail(email.value)) {
        refused.value = 'Escriba un correo electrónico válido.';
        return;
    }

    sending.value = true;
    try {
        sent.value = (await inviteObserver(email.value.trim())).message;
        email.value = '';
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}

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

        <p v-if="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ sent }}</p>

        <LoadState :loading="loading" :error="error" :empty="team?.length === 0" loading-text="Cargando veedores…" empty-text="Aún no ha invitado veedores." @retry="load">
            <ul class="flex flex-col divide-y divide-slate-100 rounded-lg bg-white">
                <li v-for="member in team" :key="member.email" class="flex items-center justify-between gap-2 px-3 py-3 text-sm">
                    <span class="truncate">{{ member.email }}</span>
                    <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold" :class="statusStyle[member.status] ?? 'bg-slate-100 text-slate-700'">{{ member.status }}</span>
                </li>
            </ul>
        </LoadState>
    </AdminLayout>
</template>
