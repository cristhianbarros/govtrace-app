<script setup>
// US-001 y US-002: dar de alta una organización, y en el mismo paso
// asignar su Administrador inicial si ya se conoce (nombre y correo). It. 43k
// (V10): desde una solicitud de alta, precargada; registrarla la aprueba.
// It. 46b: antes de registrarla, lo que dicen del NIT o de la matrícula los
// datos abiertos del RUES.
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import RuesAnswer from '@/Components/SuperAdmin/RuesAnswer.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { lookupRues, registerOrganization, suggestSubdomain } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    request: { type: Object, default: null }, // { id, name, contact_email, registration_number, registration_authority }
});

const empty = { name: '', nit: '', registrationNumber: '', registrationAuthority: '', subdomain: '', administratorName: '', administratorEmail: '' };
const form = reactive({
    ...empty,
    ...(props.request
        ? {
              name: props.request.name,
              registrationNumber: props.request.registration_number,
              registrationAuthority: props.request.registration_authority,
              administratorEmail: props.request.contact_email,
          }
        : {}),
});

// It. 51: el subdominio sale del nombre de la veeduría. Se propone mientras se escribe el nombre
// (tras una pausa) y se puede ajustar: lo que se escribe a mano ya no lo pisa el nombre. El servidor
// calcula el slug —el mismo con el que registra si el campo llega vacío— y el primero libre si ya
// existe (-2, -3…); aquí solo se muestra.
const MIN_NAME = 3;
const suggestion = ref(null); // { subdomain, address } de la última propuesta
const apex = ref(null); // el dominio de las veedurías, de la dirección que dio el servidor
const touched = ref(false); // el Super Administrador lo escribió o lo cambió él
let timer;
let latest = 0;

async function suggest(name) {
    const current = ++latest;
    const answer = await suggestSubdomain(name.trim());
    if (current !== latest || !answer) {
        return;
    }
    suggestion.value = answer.subdomain ? answer : null;
    apex.value = answer.address ? answer.address.slice(answer.subdomain.length + 1) : apex.value;
    if (!touched.value) {
        form.subdomain = answer.subdomain ?? '';
    }
}

watch(
    () => form.name,
    (name) => {
        clearTimeout(timer);
        if (name.trim().length < MIN_NAME) {
            latest += 1;
            suggestion.value = null;
            if (!touched.value) {
                form.subdomain = '';
            }
            return;
        }
        timer = setTimeout(() => suggest(name), 300);
    },
);

// Desde una solicitud de alta, el nombre ya viene: se propone al abrir, sin pausa.
if (props.request && form.name.trim().length >= MIN_NAME) {
    suggest(form.name);
}

function useSuggestion() {
    touched.value = false;
    form.subdomain = suggestion.value?.subdomain ?? '';
}

onBeforeUnmount(() => clearTimeout(timer));

const NOTHING_TO_LOOK_UP = 'Escriba el NIT o la inscripción para consultarla en el RUES.';
const UNAVAILABLE = 'No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde.';
const rues = reactive({ loading: false, answer: null });

async function consultRues() {
    if (form.nit.trim() === '' && form.registrationNumber.trim() === '') {
        Object.assign(rues, { loading: false, answer: { status: 'nothing', message: NOTHING_TO_LOOK_UP, records: [] } });
        return;
    }
    Object.assign(rues, { loading: true, answer: null });
    try {
        rues.answer = await lookupRues({ nit: form.nit.trim(), registration_number: form.registrationNumber.trim(), registration_authority: form.registrationAuthority.trim() });
    } catch (failure) {
        rues.answer = { status: 'unavailable', message: failure?.response?.status === 422 ? errorMessage(failure) : UNAVAILABLE, records: [] };
    } finally {
        rues.loading = false;
    }
}

const sending = ref(false);
const sent = ref(null);
const refused = ref(null);

async function submit() {
    sent.value = null;
    refused.value = null;

    if (form.administratorEmail.trim() !== '' && form.administratorName.trim() === '') {
        refused.value = 'Escriba también el nombre del Administrador inicial.';
        return;
    }

    sending.value = true;
    try {
        sent.value = (
            await registerOrganization({
                name: form.name,
                nit: form.nit.trim() || null,
                // It. 44d (R-LEG-06): una veeduría sin NIT se identifica con su inscripción.
                registration_number: form.registrationNumber.trim() || null,
                registration_authority: form.registrationAuthority.trim() || null,
                subdomain: form.subdomain,
                administrator_name: form.administratorName.trim() || null,
                administrator_email: form.administratorEmail.trim() || null,
                ...(props.request ? { request_id: props.request.id } : {}),
            })
        ).message;
        Object.assign(form, empty);
        touched.value = false;
        suggestion.value = null;
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <SuperAdminLayout title="Nueva organización">
        <div class="flex flex-col gap-4">
            <Link href="/admin/organizations" class="text-sm font-semibold text-slate-700 underline">← Organizaciones</Link>

            <p v-if="request" data-test="from-request" class="rounded-lg bg-brand-50 p-3 text-sm text-brand-900">
                Viene de la solicitud de alta de {{ request.name }}. Elija su subdominio y escriba el nombre de quien la administrará; al registrarla, la solicitud queda aprobada.
            </p>
            <p v-if="sent" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ sent }}</p>
            <p v-if="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ refused }}</p>

            <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <fieldset class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
                    <legend class="px-1 text-sm font-semibold text-slate-700">Organización</legend>
                    <div class="flex flex-col gap-1">
                        <label for="name" class="text-sm font-semibold text-slate-700">Nombre</label>
                        <input id="name" v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                    <p class="text-sm text-slate-700">Para identificarla: su NIT. Si no tiene NIT, su inscripción: el número de la resolución o el acta, y la personería o la cámara de comercio que la registró.</p>
                    <div class="flex flex-col gap-1">
                        <label for="nit" class="text-sm font-semibold text-slate-700">NIT (con dígito de verificación, si tiene)</label>
                        <input id="nit" v-model="form.nit" type="text" placeholder="900123456-8" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="registration-number" class="text-sm font-semibold text-slate-700">Resolución o acta de inscripción</label>
                        <input id="registration-number" v-model="form.registrationNumber" type="text" placeholder="Resolución 012 de 2026" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="registration-authority" class="text-sm font-semibold text-slate-700">Entidad que la registró</label>
                        <input id="registration-authority" v-model="form.registrationAuthority" type="text" placeholder="Personería de Santa Marta" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                    <button type="button" :disabled="rues.loading" class="min-h-11 self-start rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40" @click="consultRues">Consultar en el RUES</button>
                    <RuesAnswer :loading="rues.loading" :answer="rues.answer" />
                    <div class="flex flex-col gap-1">
                        <label for="subdomain" class="text-sm font-semibold text-slate-700">Subdominio</label>
                        <input id="subdomain" v-model="form.subdomain" type="text" placeholder="Sale del nombre de la veeduría" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" @input="touched = true" />
                        <p v-if="form.subdomain.trim() !== '' && apex" data-test="subdomain-address" class="text-base text-slate-700">La dirección será {{ form.subdomain.trim() }}.{{ apex }}</p>
                        <p v-else class="text-base text-slate-700">Se propone del nombre; puede cambiarlo antes de registrar. Después no se cambia.</p>
                        <button v-if="touched && suggestion && suggestion.subdomain !== form.subdomain" type="button" data-test="use-suggestion" class="min-h-11 self-start rounded-xl border border-brand-200 bg-white px-3 font-semibold text-brand-800 hover:bg-brand-50" @click="useSuggestion">Usar la propuesta: {{ suggestion.subdomain }}</button>
                    </div>
                </fieldset>

                <fieldset class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
                    <legend class="px-1 text-sm font-semibold text-slate-700">Administrador inicial (opcional)</legend>
                    <div class="flex flex-col gap-1">
                        <label for="administrator-name" class="text-sm font-semibold text-slate-700">Nombre</label>
                        <input id="administrator-name" v-model="form.administratorName" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="administrator-email" class="text-sm font-semibold text-slate-700">Correo electrónico</label>
                        <input id="administrator-email" v-model="form.administratorEmail" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    </div>
                </fieldset>

                <button type="submit" :disabled="sending" class="rounded-xl bg-brand-700 px-3 py-4 font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                    {{ sending ? 'Registrando…' : 'Registrar organización' }}
                </button>
            </form>
        </div>
    </SuperAdminLayout>
</template>
