<script setup>
// US-029: una evidencia publicada en la línea de tiempo — fecha y hora,
// clasificación, comentario, lugar aproximado (R-PRIV-02) y sus archivos —,
// o la lápida de una retirada (US-037), que conserva su sello.
import { ref } from 'vue';
import PhotoViewer from '@/Components/Public/PhotoViewer.vue';
import SealPanel from '@/Components/Public/SealPanel.vue';
import { formatDateTime } from '@/lib/format.js';

const props = defineProps({
    evidence: { type: Object, required: true },
});

const CLASSIFICATION = {
    Avance: 'bg-green-100 text-green-800',
    Retraso: 'bg-yellow-100 text-yellow-900',
    Abandono: 'bg-red-100 text-red-800',
};

const viewing = ref(null); // la foto abierta en el visor
const showingSeal = ref(false);

const files = () => props.evidence.files ?? [];
const photos = () => files().filter((file) => file.photo_url);

// US-026: el binario exacto que se selló, para comprobarlo en el validador o en un peritaje.
const original = (index) => (files().length > 1 ? `Descargar archivo original (${index + 1} de ${files().length})` : 'Descargar archivo original');
</script>

<template>
    <article data-test="evidence" class="rounded-lg border border-slate-200 bg-white p-4">
        <header class="flex flex-wrap items-center gap-2">
            <time :datetime="evidence.captured_at" class="text-sm text-slate-600">{{ formatDateTime(evidence.captured_at) }}</time>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="CLASSIFICATION[evidence.classification]">{{ evidence.classification }}</span>
        </header>

        <p v-if="evidence.notice" class="mt-3 rounded bg-slate-100 p-3 text-sm text-slate-700">{{ evidence.notice }}</p>
        <template v-else>
            <p v-if="evidence.comment" class="mt-2 whitespace-pre-line">{{ evidence.comment }}</p>
            <div v-if="photos().length" class="mt-3 grid grid-cols-3 gap-2">
                <button
                    v-for="photo in photos()"
                    :key="photo.id"
                    type="button"
                    data-test="thumbnail"
                    class="aspect-square overflow-hidden rounded-lg bg-slate-100"
                    :aria-label="`Ver la foto de la evidencia del ${formatDateTime(evidence.captured_at)}`"
                    @click="viewing = photo"
                >
                    <img :src="photo.photo_url" loading="lazy" alt="" class="size-full object-cover" />
                </button>
            </div>
            <ul v-if="files().length" class="mt-3 flex flex-col gap-2">
                <li v-for="(file, index) in files()" :key="file.id" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <a
                        data-test="download"
                        :href="file.download_url"
                        class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold"
                    >{{ original(index) }}</a>
                    <a
                        data-test="proof"
                        :href="file.proof_url"
                        class="inline-flex min-h-11 items-center text-sm text-slate-700 underline"
                    >Descargar su prueba</a>
                </li>
            </ul>
            <p v-if="evidence.approximate_location" class="mt-2 text-xs text-slate-500">
                Ubicación aproximada (unos 100 m): {{ evidence.approximate_location.lat }}, {{ evidence.approximate_location.lng }}
            </p>
        </template>

        <button
            type="button"
            class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border-2 border-slate-900 bg-white px-3 text-sm font-semibold text-slate-900 md:w-auto"
            :aria-expanded="showingSeal"
            @click="showingSeal = !showingSeal"
        >Comprobar que es original</button>
        <SealPanel v-if="showingSeal" :receipt-url="evidence.receipt_url" :report-id="evidence.report_id" />

        <PhotoViewer v-if="viewing" :src="viewing.photo_url" :alt="`Foto de la evidencia del ${formatDateTime(evidence.captured_at)}`" @close="viewing = null" />
    </article>
</template>
