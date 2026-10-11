<script setup>
// It. 54 (V3, US-061-USR): los administradores de la organización, en su propia
// pantalla — antes eran una sección de "Veedores". Ver quién gestiona la
// veeduría, con el estado de cada invitación, e invitar a otro, por si uno
// pierde el acceso o se va. Desactivarlos lo hace el Super Administrador.
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { isEmail } from '@/lib/credentials.js';
import { fetchAdministrators, inviteAdministrator } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: administrators, loading, error, load } = useLoader(fetchAdministrators);

const newAdministrator = ref({ name: '', email: '' });
const inviting = ref(false);
const notice = ref(null);
const refused = ref(null);

async function invite() {
    notice.value = null;
    refused.value = null;
    const { name, email } = newAdministrator.value;
    if (name.trim() === '' || !isEmail(email)) {
        refused.value = 'Escriba el nombre y un correo electrónico válido.';
        return;
    }

    inviting.value = true;
    try {
        notice.value = (await inviteAdministrator({ name: name.trim(), email: email.trim() })).message;
        newAdministrator.value = { name: '', email: '' };
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        inviting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Administradores">
        <section data-test="administrators" aria-labelledby="administrators-title" class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
            <h2 id="administrators-title" class="text-lg">Administradores</h2>
            <p class="text-sm text-slate-700">Con más de uno, la veeduría no queda sin quién la gestione si alguien pierde el acceso o se va. Desactivar a un administrador lo hace el Super Administrador.</p>

            <LoadState :loading="loading" :error="error" :empty="administrators?.length === 0" loading-text="Cargando administradores…" empty-text="Aún no hay administradores." illustration="team" @retry="load">
                <ul class="flex flex-col divide-y divide-slate-100">
                    <li v-for="administrator in administrators" :key="administrator.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <span><span class="font-semibold">{{ administrator.name }}</span> · {{ administrator.email }}</span>
                        <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="administrator.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'">{{ administrator.label }}</span>
                    </li>
                </ul>
            </LoadState>

            <form class="flex flex-col gap-2" novalidate @submit.prevent="invite">
                <p class="text-sm font-semibold text-slate-700">Invitar a otro administrador</p>
                <label for="administrator-name" class="text-sm text-slate-700">Nombre</label>
                <input id="administrator-name" v-model="newAdministrator.name" type="text" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                <label for="administrator-email" class="text-sm text-slate-700">Correo electrónico</label>
                <input id="administrator-email" v-model="newAdministrator.email" type="email" inputmode="email" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                <button type="submit" :disabled="inviting" class="rounded-xl border border-brand-200 bg-white px-3 py-3 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40">
                    {{ inviting ? 'Enviando…' : 'Invitar administrador' }}
                </button>
            </form>
        </section>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>
    </AdminLayout>
</template>
