<script setup>
// US-039-USR: el enlace del correo abre esta pantalla, donde se elige la
// nueva contraseña. Si el enlace venció (60 minutos) o ya se usó, no hay
// formulario: solo el motivo y cómo pedir otro.
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_RULES_MESSAGE, isStrongPassword } from '@/lib/credentials.js';

const props = defineProps({
    valid: { type: Boolean, required: true },
    email: { type: String, default: '' },
    token: { type: String, default: '' },
    message: { type: String, default: '' },
});

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const hints = ref({});
const serverError = computed(() => form.errors.token ?? form.errors.password ?? form.errors.email);

function submit() {
    hints.value = {};
    if (!isStrongPassword(form.password)) {
        hints.value.password = PASSWORD_RULES_MESSAGE;
    } else if (form.password !== form.password_confirmation) {
        hints.value.password_confirmation = 'Las contraseñas no coinciden.';
    }
    if (Object.keys(hints.value).length > 0) {
        return;
    }

    form.post('/reset-password', { onError: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Nueva contraseña" />
    <AppLayout title="GovTrace">
        <div class="mx-auto flex w-full max-w-sm flex-col gap-5 pt-4">
            <h1 class="text-xl font-semibold">Nueva contraseña</h1>

            <template v-if="!valid">
                <p role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ message }}</p>
                <Link href="/forgot-password" class="text-center text-sm font-semibold text-slate-700 underline">Pedir otro enlace</Link>
            </template>

            <form v-else class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                <p class="text-sm text-slate-600">
                    Cuenta: <span class="font-semibold text-slate-900">{{ email }}</span>
                </p>

                <p v-if="serverError" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ serverError }}</p>

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-semibold text-slate-700">Nueva contraseña</label>
                    <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base" />
                    <p class="text-xs" :class="hints.password ? 'text-red-700' : 'text-slate-500'">
                        {{ hints.password ?? 'Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.' }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Confirmar contraseña</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base"
                    />
                    <p v-if="hints.password_confirmation" class="text-sm text-red-700">{{ hints.password_confirmation }}</p>
                </div>

                <button type="submit" :disabled="form.processing" class="rounded-xl bg-brand-700 px-3 py-4 text-base font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                    {{ form.processing ? 'Guardando…' : 'Guardar contraseña' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
