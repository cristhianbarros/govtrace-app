<script setup>
// US-007: el nombre de fantasía y el logo de la veeduría — lo que sus
// veedores y su mapa público ven. El NIT y el nombre legal se muestran, sin
// editar: solo el Super Administrador los cambia (US-011, R-TA-03). It. 43h
// (V13): y su contacto público, que se ve en el pie de su sitio.
import { Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fetchProfile, saveProfile } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

// Las mismas reglas que el servidor (OrganizationLogo), para avisar antes de subir.
const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/svg+xml'];
const LOGO_MAX_BYTES = 2 * 1024 * 1024;

const { data: profile, loading, error, load } = useLoader(fetchProfile);

const displayName = ref('');
const contactEmail = ref('');
const contactPhone = ref('');
const logo = ref(null);
const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

watch(profile, (current) => {
    displayName.value = current?.display_name ?? '';
    contactEmail.value = current?.contact_email ?? '';
    contactPhone.value = current?.contact_phone ?? '';
});

function chooseLogo(event) {
    refused.value = null;
    const [file] = event.target.files ?? [];
    logo.value = file ?? null;
}

function logoProblem(file) {
    if (!LOGO_TYPES.includes(file.type)) {
        return 'El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG.';
    }
    return file.size > LOGO_MAX_BYTES ? 'El tamaño de la imagen supera el límite permitido de 2 MB.' : null;
}

async function save() {
    saved.value = null;
    refused.value = logo.value ? logoProblem(logo.value) : null;
    if (refused.value) {
        return;
    }

    const form = new FormData();
    form.append('display_name', displayName.value);
    form.append('contact_email', contactEmail.value.trim());
    form.append('contact_phone', contactPhone.value.trim());
    if (logo.value) {
        form.append('logo', logo.value);
    }

    saving.value = true;
    try {
        saved.value = (await saveProfile(form)).message;
        logo.value = null;
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Organización">
        <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

        <LoadState :loading="loading" :error="error" loading-text="Cargando organización…" empty-text="" @retry="load">
            <form class="flex flex-col gap-4 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5" novalidate @submit.prevent="save">
                <div class="flex flex-col gap-1">
                    <label for="display-name" class="text-sm font-semibold text-slate-700">Nombre de fantasía</label>
                    <input id="display-name" v-model="displayName" type="text" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="text-sm font-semibold text-slate-700">Contacto público</legend>
                    <p class="text-sm text-slate-700">Se ven en el sitio público de la veeduría, para que cualquiera la contacte.</p>
                    <label for="contact-email" class="text-sm font-semibold text-slate-700">Correo de contacto</label>
                    <input id="contact-email" v-model="contactEmail" type="email" autocomplete="email" maxlength="150" placeholder="contacto@veeduria.org" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                    <label for="contact-phone" class="text-sm font-semibold text-slate-700">Teléfono de contacto (opcional)</label>
                    <input id="contact-phone" v-model="contactPhone" type="tel" autocomplete="tel" maxlength="30" placeholder="+57 300 123 4567" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                </fieldset>

                <div class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-slate-700">Logo</span>
                    <img v-if="profile.logo_url" :src="profile.logo_url" alt="Logo actual" class="size-20 rounded border object-contain" />
                    <label for="logo" class="text-xs text-slate-500">PNG, JPG o SVG · hasta 2 MB · al menos 128x128 px</label>
                    <input id="logo" type="file" accept="image/png,image/jpeg,image/svg+xml" class="text-sm" @change="chooseLogo" />
                </div>

                <p v-if="refused" role="alert" class="rounded bg-red-50 p-2 text-sm text-red-800">{{ refused }}</p>

                <button type="submit" :disabled="saving" class="rounded-xl bg-brand-700 px-3 py-3 font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                    {{ saving ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </form>

            <section class="rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                <dl class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Nombre legal</dt><dd>{{ profile.legal_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">NIT</dt><dd>{{ profile.nit ?? 'Sin NIT' }}</dd></div>
                    <!-- It. 44d (R-LEG-06): una veeduría de base se identifica con su inscripción. -->
                    <div v-if="profile.registration_number"><dt class="text-xs text-slate-500">Inscripción</dt><dd>{{ profile.registration_number }}, {{ profile.registration_authority }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Subdominio</dt><dd>{{ profile.subdomain }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-slate-500">Solo el Super Administrador lo cambia, a solicitud formal.</p>
            </section>

            <nav aria-label="Más de la organización" class="flex flex-col gap-2 text-sm font-semibold text-slate-700">
                <Link href="/admin/summary" class="inline-flex min-h-11 items-center underline">Resumen del territorio</Link>
                <Link href="/admin/authorization" class="inline-flex min-h-11 items-center underline">Autorización al Super Administrador</Link>
                <Link href="/admin/audit" class="inline-flex min-h-11 items-center underline">Ver el registro de auditoría</Link>
            </nav>
        </LoadState>
    </AdminLayout>
</template>
