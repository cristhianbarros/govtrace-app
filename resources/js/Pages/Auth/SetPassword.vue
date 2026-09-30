<script setup>
// US-030: el enlace de la invitación (US-002, US-005) abre esta pantalla,
// donde el veedor o el Administrador inicial crea su contraseña y entra. Si
// el enlace venció o no es válido, no hay formulario: solo el motivo.
// US-057-LEG (it. 44c): el veedor declara además que no tiene impedimentos para serlo.
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImpedimentsDeclaration from '@/Components/ImpedimentsDeclaration.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_RULES_MESSAGE, isStrongPassword } from '@/lib/credentials.js';
import { DECLARATION_REQUIRED } from '@/lib/impediments.js';
import { AUTHORIZATION_REQUIRED } from '@/lib/privacy.js';

const props = defineProps({
    valid: { type: Boolean, required: true },
    email: { type: String, default: '' },
    token: { type: String, default: '' },
    action: { type: String, default: '' },
    message: { type: String, default: '' },
    declaration: { type: Boolean, default: false }, // un veedor la hace; el Administrador, no
    dataPolicyUrl: { type: String, default: '/privacidad' }, // US-058-LEG: la política que autoriza
});

const form = useForm({ token: props.token, password: '', password_confirmation: '', data_authorization: false, ...(props.declaration ? { declaration: false } : {}) });
const hints = ref({});
const serverError = computed(() => form.errors.token ?? form.errors.data_authorization ?? form.errors.declaration ?? form.errors.password ?? form.errors.password_confirmation);

function submit() {
    hints.value = {};
    if (!isStrongPassword(form.password)) {
        hints.value.password = PASSWORD_RULES_MESSAGE;
    } else if (form.password !== form.password_confirmation) {
        hints.value.password_confirmation = 'Las contraseñas no coinciden.';
    }
    if (props.declaration && !form.declaration) {
        hints.value.declaration = DECLARATION_REQUIRED;
    }
    if (!form.data_authorization) {
        hints.value.data_authorization = AUTHORIZATION_REQUIRED;
    }
    if (Object.keys(hints.value).length > 0) {
        return;
    }

    form.post(props.action, { onError: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Crear contraseña" />
    <AppLayout title="GovTrace">
        <div class="mx-auto flex w-full max-w-sm flex-col gap-5 pt-4">
            <h1 class="text-xl font-semibold">Crear contraseña</h1>

            <p v-if="!valid" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ message }}</p>

            <form v-else class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                <p class="text-sm text-slate-600">
                    Cuenta: <span class="font-semibold text-slate-900">{{ email }}</span>
                </p>

                <p v-if="serverError" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ serverError }}</p>

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-semibold text-slate-700">Contraseña</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base"
                    />
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

                <ImpedimentsDeclaration v-if="declaration" v-model="form.declaration" :error="hints.declaration ?? null" />

                <!-- US-058-LEG (it. 44e): la autorización del tratamiento de datos, previa, expresa e informada (Ley 1581, art. 9). -->
                <section aria-labelledby="data-title" class="flex flex-col gap-2 rounded-lg border border-slate-300 bg-white p-3 text-base">
                    <h2 id="data-title" class="font-semibold">Sus datos personales</h2>
                    <p>GovTrace guarda su nombre, su correo y, si reporta, sus fotos con su ubicación, para el control social de las obras públicas. Puede conocerlos, corregirlos o pedir que se borren.</p>
                    <a data-test="data-policy" :href="dataPolicyUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center self-start font-semibold underline">Lea la política de tratamiento de datos</a>
                    <label for="data-authorization" class="flex min-h-11 items-start gap-3 font-semibold">
                        <input id="data-authorization" v-model="form.data_authorization" type="checkbox" class="mt-0.5 size-6 shrink-0" />
                        Autorizo el tratamiento de mis datos personales según esta política.
                    </label>
                    <p v-if="hints.data_authorization" role="alert" class="text-sm text-red-700">{{ hints.data_authorization }}</p>
                </section>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-slate-900 px-3 py-4 text-base font-semibold text-white disabled:opacity-40"
                >
                    {{ form.processing ? 'Activando…' : 'Activar mi cuenta' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
