<script setup>
// El dominio central de GovTrace. No hay un mapa que mezcle organizaciones
// (R-MAP-01): cada una publica el suyo. It. 40d (V5): es la puerta de entrada
// para cualquiera — qué es, cómo funciona y el directorio de veedurías, cada
// una con el enlace a su mapa. El acceso del Super Administrador, al pie.
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import Illustration from '@/Components/Brand/Illustration.vue';
import WorksIllustration from '@/Components/Brand/WorksIllustration.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { requestOrganization } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

defineProps({
    organizations: { type: Array, default: () => [] }, // [{ name, territory, url, suspended }]
});

// It. 43k (V10, US-062-ALT): una veeduría pide su alta; el Super Administrador la decide.
const AUTHORIZATION_REQUIRED = 'Para enviar la solicitud, autorice el tratamiento de sus datos personales.';
// It. 46b: la resolución o el certificado de inscripción, en PDF, para que el Super Administrador compruebe la inscripción.
const NEEDS_THE_PDF = 'Adjunte la resolución o el certificado de inscripción en PDF, de hasta 10 MB.';
const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;
const request = reactive({ name: '', email: '', resolution: '', authority: '', document: null, authorized: false, website: '' });
const documentRefused = ref(false);

function chooseDocument(event) {
    request.document = event.target.files?.[0] ?? null;
    documentRefused.value = false;
}

const acceptableDocument = (file) =>
    file !== null && (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) && file.size > 0 && file.size <= MAX_DOCUMENT_BYTES;
const requestSent = ref(null);
const requestRefused = ref(null);
const requesting = ref(false);

async function sendRequest() {
    requestRefused.value = null;
    documentRefused.value = !acceptableDocument(request.document);
    if (documentRefused.value) {
        return;
    }
    if (!request.authorized) {
        requestRefused.value = AUTHORIZATION_REQUIRED;
        return;
    }
    requesting.value = true;
    try {
        requestSent.value = (
            await requestOrganization({
                name: request.name.trim(),
                contact_email: request.email.trim(),
                registration_number: request.resolution.trim(),
                registration_authority: request.authority.trim(),
                document: request.document,
                data_authorization: true,
                website: request.website,
            })
        ).message;
    } catch (failure) {
        requestRefused.value = errorMessage(failure);
    } finally {
        requesting.value = false;
    }
}

const STEPS = [
    { art: 'evidence', text: 'Los veedores de cada veeduría ciudadana visitan las obras públicas y toman fotos con su celular.' },
    { art: 'validator', text: 'Cada foto recibe un sello digital en el momento: nadie puede borrarla ni cambiarla después.' },
    { art: 'map', text: 'La veeduría las revisa y las publica en su mapa, donde cualquiera las ve y comprueba que son originales.' },
];
</script>

<template>
    <Head title="Inicio" />
    <AppLayout title="GovTrace">
        <div class="flex flex-col gap-8">
            <!-- It. 40e: la bienvenida — una banda en verde pino, con la ilustración y el camino a las veedurías. -->
            <section class="overflow-hidden rounded-3xl bg-linear-to-br from-brand-800 via-brand-700 to-brand-600 p-6 text-white shadow-soft md:p-10">
                <div class="grid items-center gap-6 md:grid-cols-[1fr_16rem]">
                    <div>
                        <p class="text-base font-semibold text-accent-300">Control social, con evidencia que nadie puede cambiar</p>
                        <h1 class="mt-2 text-3xl leading-tight text-white md:text-5xl">Veeduría ciudadana de obras públicas</h1>
                        <p class="mt-3 max-w-prose text-lg text-brand-50">Aquí las veedurías ciudadanas muestran, con fotos, cómo avanzan las obras públicas de su territorio.</p>
                        <a href="#veedurias" class="mt-5 inline-flex min-h-12 items-center rounded-xl bg-accent-400 px-5 text-base font-semibold text-slate-900 shadow-sm hover:bg-accent-300">Ver las veedurías</a>
                    </div>
                    <div class="mx-auto w-48 md:w-full"><WorksIllustration /></div>
                </div>
            </section>

            <section aria-labelledby="how-title">
                <h2 id="how-title" class="text-2xl">¿Cómo funciona?</h2>
                <ol data-test="how" class="mt-4 grid gap-3 md:grid-cols-3">
                    <li v-for="(step, index) in STEPS" :key="index" class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5">
                        <!-- It. 40f: cada paso, con su dibujo y su número. -->
                        <div class="flex items-end justify-between gap-3">
                            <span aria-hidden="true" class="font-display text-4xl font-semibold text-warm-500">{{ index + 1 }}</span>
                            <Illustration :name="step.art" size="w-32" />
                        </div>
                        <p class="text-base"><span class="sr-only">{{ index + 1 }}.</span> {{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section id="veedurias" aria-labelledby="directory-title" class="flex scroll-mt-20 flex-col gap-3">
                <h2 id="directory-title" class="text-2xl">Veedurías en GovTrace</h2>
                <div v-if="organizations.length === 0" data-test="empty" class="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-6 text-center shadow-soft ring-1 ring-slate-900/5">
                    <Illustration name="team" size="w-36" />
                    <p class="text-base text-slate-700">Aún no hay veedurías publicando en GovTrace.</p>
                </div>
                <ul v-else class="grid gap-3 md:grid-cols-2">
                    <li v-for="organization in organizations" :key="organization.url" data-test="organization" class="flex flex-col justify-between gap-4 rounded-2xl border-l-8 border-brand-600 bg-white p-5 shadow-soft">
                        <div class="flex items-start gap-3">
                            <BuildingOffice2Icon aria-hidden="true" class="mt-1 size-7 shrink-0 text-brand-700" />
                            <div>
                                <p class="font-display text-xl font-semibold text-brand-900">{{ organization.name }}</p>
                                <p v-if="organization.territory" class="text-base text-slate-700">Vigila: {{ organization.territory }}</p>
                                <p v-if="organization.suspended" class="mt-1 text-sm font-semibold text-amber-900">Suspendida por ahora: sus evidencias siguen a la vista.</p>
                            </div>
                        </div>
                        <a :href="organization.url" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-4 text-base font-semibold text-white shadow-sm hover:bg-brand-800">Ver su mapa de obras</a>
                    </li>
                </ul>
                <p class="text-base text-slate-700">¿Es veedor? Entre desde el mapa de su veeduría, con el botón "Entrar".</p>
            </section>

            <!-- It. 43k (V10, US-062-ALT): una veeduría pide su alta. No es autorregistro: la decide el Super Administrador. -->
            <section id="pedir-alta" aria-labelledby="request-title" class="flex scroll-mt-20 flex-col gap-3 rounded-2xl border-l-8 border-accent-400 bg-white p-5 shadow-soft">
                <h2 id="request-title" class="text-2xl">¿Su veeduría quiere publicar en GovTrace?</h2>
                <p class="text-base text-slate-700">Pida su alta. El equipo de GovTrace revisa cada solicitud y le escribe con la respuesta.</p>
                <p v-if="requestSent" role="status" data-test="request-sent" class="rounded-lg bg-emerald-50 p-3 text-base font-semibold text-emerald-900">{{ requestSent }}</p>
                <form v-else data-test="organization-request" class="flex flex-col gap-3" novalidate @submit.prevent="sendRequest">
                    <label for="request-name" class="text-base font-semibold">Nombre de la veeduría</label>
                    <input id="request-name" v-model="request.name" type="text" maxlength="150" autocomplete="organization" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                    <label for="request-email" class="text-base font-semibold">Correo de contacto</label>
                    <input id="request-email" v-model="request.email" type="email" inputmode="email" autocomplete="email" maxlength="150" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                    <label for="request-resolution" class="text-base font-semibold">Número de la resolución o de la matrícula</label>
                    <input id="request-resolution" v-model="request.resolution" type="text" maxlength="100" placeholder="Resolución 045 de 2026" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                    <label for="request-authority" class="text-base font-semibold">Personería o cámara de comercio que la registró</label>
                    <input id="request-authority" v-model="request.authority" type="text" maxlength="150" placeholder="Personería de Medellín" class="min-h-12 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                    <!-- El botón propio, y no el del navegador: el del navegador habla en su idioma ("Choose File"). -->
                    <p id="request-document-label" class="text-base font-semibold">Resolución o certificado de inscripción (PDF)</p>
                    <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-2 text-base font-semibold text-brand-800 focus-within:ring-2 focus-within:ring-brand-600">
                        <span id="request-document-choice" class="min-w-0 break-words">{{ request.document ? request.document.name : 'Elegir el PDF' }}</span>
                        <input
                            id="request-document"
                            type="file"
                            accept="application/pdf,.pdf"
                            class="sr-only"
                            aria-labelledby="request-document-label request-document-choice"
                            aria-describedby="request-document-hint"
                            @change="chooseDocument"
                        />
                    </label>
                    <p id="request-document-hint" :role="documentRefused ? 'alert' : null" class="text-base" :class="documentRefused ? 'text-red-800' : 'text-slate-600'">
                        {{ documentRefused ? NEEDS_THE_PDF : 'Solo PDF, hasta 10 MB. Con él, el equipo de GovTrace comprueba la inscripción.' }}
                    </p>
                    <!-- Solo un robot llena este campo: la solicitud no se guarda. -->
                    <div aria-hidden="true" class="absolute -left-[10000px] size-px overflow-hidden">
                        <label for="request-website">No llene este campo</label>
                        <input id="request-website" v-model="request.website" name="website" type="text" tabindex="-1" autocomplete="off" />
                    </div>
                    <a href="/privacidad" class="inline-flex min-h-11 items-center self-start font-semibold underline">Lea la política de tratamiento de datos</a>
                    <label for="request-authorization" class="flex min-h-11 items-start gap-3 font-semibold">
                        <input id="request-authorization" v-model="request.authorized" type="checkbox" class="mt-0.5 size-6 shrink-0" />
                        Autorizo el tratamiento de mis datos personales según la política.
                    </label>
                    <p v-if="requestRefused" role="alert" class="rounded-lg bg-red-50 p-3 text-base text-red-800">{{ requestRefused }}</p>
                    <button type="submit" :disabled="requesting" class="min-h-12 self-start rounded-xl bg-brand-700 px-5 text-base font-semibold text-white shadow-sm hover:bg-brand-800 disabled:opacity-40">
                        {{ requesting ? 'Enviando…' : 'Enviar solicitud' }}
                    </button>
                </form>
            </section>

            <footer class="flex flex-wrap gap-x-6 border-t border-slate-200 pt-4">
                <Link href="/login" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Acceso para administradores de GovTrace</Link>
                <!-- US-058-LEG (it. 44e). -->
                <Link href="/privacidad" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Política de tratamiento de datos</Link>
            </footer>
        </div>
    </AppLayout>
</template>
