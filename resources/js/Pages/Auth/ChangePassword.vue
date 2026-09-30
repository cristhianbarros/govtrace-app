<script setup>
// It. 40c (V11 de docs/mapa-funcional.md): cambiar la contraseña con la sesión
// abierta, desde "Mi cuenta". La actual, y la nueva dos veces, con las mismas
// reglas que al crearla (US-030); el servidor lo vuelve a comprobar.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasswordField from '@/Components/PasswordField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_RULES_MESSAGE, isStrongPassword } from '@/lib/credentials.js';
import { changePassword } from '@/services/api.js';

defineProps({
    home: { type: String, required: true }, // el panel de la persona, para volver
});

const page = usePage();
const form = ref({ current_password: '', password: '', password_confirmation: '' });
const hints = ref({});
const done = ref(null);
const sending = ref(false);

async function submit() {
    hints.value = {};
    done.value = null;
    if (form.value.current_password === '') {
        hints.value.current_password = 'Escriba su contraseña actual.';
    }
    if (!isStrongPassword(form.value.password)) {
        hints.value.password = PASSWORD_RULES_MESSAGE;
    } else if (form.value.password !== form.value.password_confirmation) {
        hints.value.password_confirmation = 'Las contraseñas no coinciden.';
    }
    if (Object.keys(hints.value).length > 0) {
        return;
    }

    sending.value = true;
    try {
        done.value = (await changePassword({ ...form.value })).message;
        form.value = { current_password: '', password: '', password_confirmation: '' };
    } catch (failure) {
        const errors = failure?.response?.data?.errors;
        hints.value = errors
            ? Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
            : { current_password: 'No se pudo conectar con el servidor. Revise su conexión y vuelva a intentarlo.' };
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Head title="Cambiar contraseña" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo ?? null">
        <div class="mx-auto flex w-full max-w-md flex-col gap-5 pt-2">
            <Link :href="home" class="inline-flex min-h-11 items-center self-start text-base font-semibold text-slate-700">Volver a mi panel</Link>
            <h1 class="text-xl font-semibold">Cambiar contraseña</h1>
            <p v-if="done" role="status" class="rounded-lg bg-emerald-50 p-3 text-base font-semibold text-emerald-900">{{ done }}</p>

            <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <PasswordField id="current_password" v-model="form.current_password" label="Contraseña actual" :hint="hints.current_password" />
                <PasswordField id="password" v-model="form.password" label="Nueva contraseña" autocomplete="new-password" :hint="hints.password" />
                <p class="-mt-2 text-sm text-slate-700">{{ PASSWORD_RULES_MESSAGE }}</p>
                <PasswordField id="password_confirmation" v-model="form.password_confirmation" label="Escriba otra vez la nueva contraseña" autocomplete="new-password" :hint="hints.password_confirmation" />
                <button type="submit" :disabled="sending" class="min-h-12 rounded-xl bg-brand-700 px-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                    {{ sending ? 'Cambiando…' : 'Cambiar contraseña' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
