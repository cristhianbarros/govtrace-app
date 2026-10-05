<script setup>
// It. 46g — US-065-SEC: después de la contraseña, el código que muestra la app
// del Super Administrador; sin su teléfono, uno de sus códigos de recuperación.
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    email: { type: String, required: true },
});

const form = useForm({ code: '', recovery_code: '' });
const recovering = ref(false);
const hint = ref(null);

function switchTo(recovery) {
    recovering.value = recovery;
    form.code = '';
    form.recovery_code = '';
    hint.value = null;
    form.clearErrors();
}

function submit() {
    form.code = form.code.replace(/\s+/g, '');
    form.recovery_code = form.recovery_code.trim().toUpperCase();
    hint.value = recovering.value
        ? (form.recovery_code === '' ? 'Escriba uno de sus códigos de recuperación.' : null)
        : (/^\d{6}$/.test(form.code) ? null : 'Escriba los 6 dígitos que muestra su app.');
    if (hint.value === null) {
        form.post('/two-factor', { onError: () => form.reset('code', 'recovery_code') });
    }
}
</script>

<template>
    <Head title="Verificación en dos pasos" />
    <AppLayout title="GovTrace">
        <form class="mx-auto mt-2 flex w-full max-w-sm flex-col gap-5 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 md:p-7" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-1 text-center">
                <h1 class="text-2xl">Verificación en dos pasos</h1>
                <p class="text-base text-slate-700">{{ email }}</p>
            </div>

            <p v-if="form.errors.code || form.errors.recovery_code" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                {{ form.errors.code ?? form.errors.recovery_code }}
            </p>

            <div v-if="!recovering" class="flex flex-col gap-2">
                <label for="code" class="text-base font-semibold text-slate-800">El código de 6 dígitos que muestra su app autenticadora</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="7"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-center font-mono text-2xl tracking-[0.3em]"
                />
            </div>
            <div v-else class="flex flex-col gap-2">
                <label for="recovery-code" class="text-base font-semibold text-slate-800">Uno de sus códigos de recuperación</label>
                <input
                    id="recovery-code"
                    v-model="form.recovery_code"
                    type="text"
                    autocomplete="off"
                    autocapitalize="characters"
                    maxlength="20"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-center font-mono text-xl tracking-wider"
                />
                <p class="text-sm text-slate-700">Cada código sirve una sola vez.</p>
            </div>
            <p v-if="hint" class="text-sm text-red-700">{{ hint }}</p>

            <button type="submit" :disabled="form.processing" class="rounded-xl bg-brand-700 px-3 py-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                {{ form.processing ? 'Comprobando…' : 'Entrar' }}
            </button>

            <button type="button" class="inline-flex min-h-11 items-center justify-center text-base font-semibold text-slate-700 underline" @click="switchTo(!recovering)">
                {{ recovering ? 'Usar el código de la app' : 'Usar un código de recuperación' }}
            </button>
        </form>
    </AppLayout>
</template>
