<script setup>
// US-042-SEC: el consentimiento explícito y trazable (R-SA-02) para que el
// Super Administrador cree reportes en nombre de la organización — 30 días,
// uno por vez, revocable.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { errorMessage } from '@/services/errors.js';
import { authorizeSuperAdmin, fetchSuperAdminAuthorization, revokeSuperAdmin } from '@/services/api.js';

const { data: authorization, loading, error, load } = useLoader(fetchSuperAdminAuthorization);
const done = ref(null);
const refused = ref(null);
const busy = ref(false);

const untilDate = (iso) => new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'America/Bogota' }).format(new Date(iso));

async function change(action) {
    busy.value = true;
    done.value = null;
    refused.value = null;
    try {
        done.value = (await action()).message;
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Autorización al Super Administrador">
        <p class="text-sm text-slate-600">Mientras esté vigente, el Super Administrador puede crear reportes en nombre de la organización. Cada reporte queda en el registro de auditoría.</p>
        <p v-if="done" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ done }}</p>
        <p v-if="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ refused }}</p>

        <LoadState :loading="loading" :error="error" loading-text="Cargando la autorización…" empty-text="" @retry="load">
            <div v-if="authorization" class="flex flex-col gap-3 rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                <template v-if="authorization.active">
                    <p class="font-semibold">Vigente hasta el {{ untilDate(authorization.expires_at) }}, otorgada por {{ authorization.granted_by }}.</p>
                    <button type="button" :disabled="busy" class="min-h-11 self-start rounded-lg border border-red-300 px-3 font-semibold text-red-800 disabled:opacity-40" @click="change(revokeSuperAdmin)">Revocar autorización</button>
                </template>
                <template v-else>
                    <p>No hay una autorización vigente.</p>
                    <button type="button" :disabled="busy" class="min-h-11 self-start rounded-xl bg-brand-700 px-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800" @click="change(authorizeSuperAdmin)">Autorizar por 30 días</button>
                </template>
            </div>
        </LoadState>
    </AdminLayout>
</template>
