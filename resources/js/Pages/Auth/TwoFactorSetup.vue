<script setup>
// It. 46g — US-065-SEC: la primera vez, el Super Administrador configura su
// app autenticadora: la instala, escanea el código QR (o escribe la clave) y
// escribe el código que muestra. Así el servidor sabe que la app tiene la clave.
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    email: { type: String, required: true },
    qr: { type: String, required: true }, // data:image/svg+xml
    secret: { type: String, required: true }, // en grupos de 4
});

const form = useForm({ code: '' });
const hint = ref(null);

function submit() {
    form.code = form.code.replace(/\s+/g, '');
    hint.value = /^\d{6}$/.test(form.code) ? null : 'Escriba los 6 dígitos que muestra su app.';
    if (hint.value === null) {
        form.post('/two-factor/setup', { onError: () => form.reset('code') });
    }
}
</script>

<template>
    <Head title="Verificación en dos pasos" />
    <AppLayout title="GovTrace">
        <form class="mx-auto mt-2 flex w-full max-w-md flex-col gap-5 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 md:p-7" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-1 text-center">
                <h1 class="text-2xl">Verificación en dos pasos</h1>
                <p class="text-base text-slate-700">Para entrar al panel global, además de su contraseña, un código de su celular. Se configura una sola vez.</p>
            </div>

            <ol class="flex list-decimal flex-col gap-4 pl-6 text-base text-slate-800">
                <li>Instale una app autenticadora en su celular: Google Authenticator, Microsoft Authenticator, Authy o la de su gestor de contraseñas.</li>
                <!-- Un li con flex pierde su número: el contenido va adentro. -->
                <li>
                    <div class="flex flex-col gap-3">
                        <span>Escanee este código QR con la app. Si no puede, escriba la clave a mano.</span>
                        <img :src="qr" :alt="`Código QR para la app autenticadora de ${email}`" class="mx-auto size-56 rounded-lg bg-white p-2 ring-1 ring-slate-200" />
                        <span class="text-sm text-slate-700">Clave: <span data-test="secret" class="break-all font-mono text-base font-semibold tracking-wider text-slate-900">{{ secret }}</span></span>
                        <span class="text-sm text-slate-700">No lleva ceros ni unos: solo letras y los números del 2 al 7.</span>
                    </div>
                </li>
                <li>
                    <div class="flex flex-col gap-2">
                        <label for="code">Escriba el código de 6 dígitos que muestra la app.</label>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="7"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-center font-mono text-2xl tracking-[0.3em]"
                        />
                        <p v-if="hint" class="text-sm text-red-700">{{ hint }}</p>
                    </div>
                </li>
            </ol>

            <p v-if="form.errors.code" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ form.errors.code }}</p>

            <button type="submit" :disabled="form.processing" class="rounded-xl bg-brand-700 px-3 py-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                {{ form.processing ? 'Comprobando…' : 'Confirmar y entrar' }}
            </button>
        </form>
    </AppLayout>
</template>
