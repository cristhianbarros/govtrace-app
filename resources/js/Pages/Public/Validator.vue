<script setup>
// US-024: el validador público, sin sesión (R-VER-02). El navegador calcula
// el hash y lee el sello en la red Stellar por su cuenta: el veredicto no
// depende de la base de datos de GovTrace. El modo contextual vive en cada
// tarjeta de la línea de tiempo.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import FileDrop from '@/Components/Public/FileDrop.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import VerdictBanner from '@/Components/Public/VerdictBanner.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { validate } from '@/lib/validator.js';
import { findProof } from '@/services/api.js';

const ACCEPT = '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf';

const page = usePage();
const mode = ref('free'); // 'free' o 'attached'
const file = ref(null);
const proofFile = ref(null);
const verifying = ref(false);
const result = ref(null);

function switchTo(next) {
    mode.value = next;
    file.value = null;
    proofFile.value = null;
    result.value = null;
}

async function verify() {
    verifying.value = true;
    result.value = null;
    try {
        result.value = await validate({ file: file.value, proofFile: proofFile.value, mode: mode.value, stellar: page.props.stellar, findProof });
    } finally {
        verifying.value = false;
    }
}

function chooseFile(chosen) {
    file.value = chosen;
    if (mode.value === 'free') {
        verify();
    }
}
</script>

<template>
    <Head title="Validador" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <Link href="/" class="mb-3 inline-flex min-h-11 items-center text-sm font-semibold text-slate-700">← Volver al mapa</Link>
        <OrganizationNotice />
        <h2 class="text-xl font-semibold">Validador de evidencias</h2>
        <p class="mt-1 text-sm text-slate-600">
            Compruebe que una foto o un documento es exactamente el que se selló en la red Stellar. El archivo no sale de su equipo: su navegador calcula su huella y la compara con la red.
        </p>

        <div class="mt-4 grid grid-cols-2 gap-2" role="group" aria-label="Modo de verificación">
            <button type="button" class="min-h-11 rounded-lg border px-3 text-sm font-semibold" :class="mode === 'free' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white'" :aria-pressed="mode === 'free'" @click="switchTo('free')">Un archivo</button>
            <button type="button" class="min-h-11 rounded-lg border px-3 text-sm font-semibold" :class="mode === 'attached' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white'" :aria-pressed="mode === 'attached'" @click="switchTo('attached')">Archivo y su prueba</button>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <FileDrop label="Arrastre aquí la foto o el PDF, o toque para elegirlo" :accept="ACCEPT" :chosen="file" @choose="chooseFile" />
            <p class="text-xs text-slate-500">JPG, PNG o PDF, hasta 10 MB.</p>
            <template v-if="mode === 'attached'">
                <FileDrop label="Y su prueba de inclusión (.prueba.json)" accept=".json,application/json" test="proof" :chosen="proofFile" @choose="(chosen) => (proofFile = chosen)" />
                <p class="text-xs text-slate-500">Con la prueba descargada, solo se consulta la red Stellar: nada a GovTrace.</p>
                <button type="button" class="min-h-11 rounded-lg bg-slate-900 px-4 font-semibold text-white disabled:opacity-50" :disabled="!file || !proofFile || verifying" @click="verify">Verificar</button>
            </template>
        </div>

        <p v-if="verifying" role="status" class="mt-4 text-sm text-slate-600">Verificando en su navegador… El archivo no sale de su equipo.</p>
        <VerdictBanner v-if="result" :result="result" />
    </AppLayout>
</template>
