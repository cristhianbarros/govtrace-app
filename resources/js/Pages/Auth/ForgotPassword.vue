<script setup>
// US-039-USR: pedir el enlace para restablecer la contraseña. La respuesta
// es la misma exista o no el correo, para no revelar quién está registrado.
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Illustration from '@/Components/Brand/Illustration.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { isEmail } from '@/lib/credentials.js';

defineProps({
    // Dónde: el nombre de la organización o "Panel global".
    context: { type: String, required: true },
});

const NEUTRAL = 'Si el correo existe, recibirás un enlace';

const form = useForm({ email: '' });
const hint = ref(null);
const answered = ref(false);

function submit() {
    hint.value = isEmail(form.email) ? null : 'Escriba un correo electrónico válido.';
    if (hint.value) {
        return;
    }

    form.post('/forgot-password', { onSuccess: () => (answered.value = true) });
}
</script>

<template>
    <Head title="Restablecer contraseña" />
    <AppLayout title="GovTrace">
        <form class="mx-auto mt-2 flex w-full max-w-sm flex-col gap-5 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 md:p-7" novalidate @submit.prevent="submit">
            <div class="flex flex-col items-center gap-2 text-center">
                <Illustration name="messages" size="w-28" />
                <h1 class="text-2xl">¿Olvidó su contraseña?</h1>
                <p class="text-base text-slate-700">{{ context }}</p>
            </div>

            <p v-if="answered" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ NEUTRAL }}</p>

            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico</label>
                <input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="username" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base" />
                <p v-if="hint" class="text-sm text-red-700">{{ hint }}</p>
            </div>

            <button type="submit" :disabled="form.processing" class="rounded-xl bg-brand-700 px-3 py-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                {{ form.processing ? 'Enviando…' : 'Enviarme el enlace' }}
            </button>

            <Link href="/login" class="text-center text-sm font-semibold text-slate-700 underline">Volver a iniciar sesión</Link>
        </form>
    </AppLayout>
</template>
