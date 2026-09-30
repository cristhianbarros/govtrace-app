<script setup>
// US-001 y US-002: dar de alta una organización, y en el mismo paso
// asignar su Administrador inicial si ya se conoce (nombre y correo).
import { Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { registerOrganization } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const empty = { name: '', nit: '', registrationNumber: '', registrationAuthority: '', subdomain: '', administratorName: '', administratorEmail: '' };
const form = reactive({ ...empty });

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
            })
        ).message;
        Object.assign(form, empty);
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
                    <div class="flex flex-col gap-1">
                        <label for="subdomain" class="text-sm font-semibold text-slate-700">Subdominio</label>
                        <input id="subdomain" v-model="form.subdomain" type="text" placeholder="veeduria-smr" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
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
