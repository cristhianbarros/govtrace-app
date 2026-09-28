<script setup>
// US-007: el nombre de fantasía y el logo de la veeduría — lo que sus
// veedores y su mapa público ven. El NIT y el nombre legal se muestran, sin
// editar: solo el Super Administrador los cambia (US-011, R-TA-03).
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
const logo = ref(null);
const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

watch(profile, (current) => (displayName.value = current?.display_name ?? ''));

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
            <form class="flex flex-col gap-4 rounded-lg bg-white p-3" novalidate @submit.prevent="save">
                <div class="flex flex-col gap-1">
                    <label for="display-name" class="text-sm font-semibold text-slate-700">Nombre de fantasía</label>
                    <input id="display-name" v-model="displayName" type="text" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-base" />
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-slate-700">Logo</span>
                    <img v-if="profile.logo_url" :src="profile.logo_url" alt="Logo actual" class="size-20 rounded border object-contain" />
                    <label for="logo" class="text-xs text-slate-500">PNG, JPG o SVG · hasta 2 MB · al menos 128x128 px</label>
                    <input id="logo" type="file" accept="image/png,image/jpeg,image/svg+xml" class="text-sm" @change="chooseLogo" />
                </div>

                <p v-if="refused" role="alert" class="rounded bg-red-50 p-2 text-sm text-red-800">{{ refused }}</p>

                <button type="submit" :disabled="saving" class="rounded-lg bg-slate-900 px-3 py-3 font-semibold text-white disabled:opacity-40">
                    {{ saving ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </form>

            <dl class="grid grid-cols-1 gap-2 rounded-lg bg-white p-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Nombre legal</dt><dd>{{ profile.legal_name }}</dd></div>
                <div><dt class="text-xs text-slate-500">NIT</dt><dd>{{ profile.nit }}</dd></div>
                <div><dt class="text-xs text-slate-500">Subdominio</dt><dd>{{ profile.subdomain }}</dd></div>
                <p class="text-xs text-slate-500 sm:col-span-2">Solo el Super Administrador lo cambia, a solicitud formal.</p>
            </dl>

            <Link href="/admin/audit" class="text-sm font-semibold text-slate-700 underline">Ver el registro de auditoría</Link>
        </LoadState>
    </AdminLayout>
</template>
