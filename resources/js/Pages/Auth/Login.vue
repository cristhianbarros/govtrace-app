<script setup>
// US-031: iniciar sesión — en el subdominio de la organización (Administrador
// y Veedor) o en el panel global (Super Administrador). El servidor decide
// y, si entra, lleva a cada rol a su panel.
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasswordField from '@/Components/PasswordField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { isEmail } from '@/lib/credentials.js';

defineProps({
    // Dónde se inicia sesión: el nombre de la organización o "Panel global".
    context: { type: String, required: true },
});

const page = usePage();
const form = useForm({ email: '', password: '' });
const hints = ref({});

function submit() {
    hints.value = {};
    if (!isEmail(form.email)) {
        hints.value.email = 'Escriba un correo electrónico válido.';
    }
    if (form.password === '') {
        hints.value.password = 'Escriba su contraseña.';
    }
    if (Object.keys(hints.value).length > 0) {
        return;
    }

    form.post('/login', { onError: () => form.reset('password') });
}
</script>

<template>
    <Head title="Iniciar sesión" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo ?? null">
        <Link v-if="page.props.organization" href="/" class="mb-2 inline-flex min-h-11 items-center text-base font-semibold text-slate-700">← Volver al mapa de obras</Link>
        <form class="mx-auto flex w-full max-w-sm flex-col gap-5 pt-4" novalidate @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-semibold">Iniciar sesión</h1>
                <p class="text-sm text-slate-600">{{ context }}</p>
            </div>

            <p v-if="page.props.flash?.status" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.status }}
            </p>

            <p v-if="form.errors.email || form.errors.password" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                {{ form.errors.email ?? form.errors.password }}
            </p>

            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    inputmode="email"
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base"
                />
                <p v-if="hints.email" class="text-sm text-red-700">{{ hints.email }}</p>
            </div>

            <!-- It. 40c: con "Mostrar", para no escribir a ciegas. -->
            <PasswordField id="password" v-model="form.password" label="Contraseña" :hint="hints.password" />

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-slate-900 px-3 py-4 text-base font-semibold text-white disabled:opacity-40"
            >
                {{ form.processing ? 'Entrando…' : 'Entrar' }}
            </button>

            <Link href="/forgot-password" class="inline-flex min-h-11 items-center justify-center text-center text-base font-semibold text-slate-700 underline">¿Olvidó su contraseña?</Link>
        </form>
    </AppLayout>
</template>
