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

const photos = () => (props.evidence.files ?? []).filter((file) => file.photo_url);
const pdfs = () => (props.evidence.files ?? []).filter((file) => file.kind === 'pdf');
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
            <a
                v-for="pdf in pdfs()"
                :key="pdf.id"
                data-test="download"
                :href="pdf.download_url"
                class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold"
            >Descargar PDF</a>
            <p v-if="evidence.approximate_location" class="mt-2 text-xs text-slate-500">
                Ubicación aproximada (unos 100 m): {{ evidence.approximate_location.lat }}, {{ evidence.approximate_location.lng }}
            </p>
        </template>

        <button
            type="button"
            class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white sm:w-auto"
            :aria-expanded="showingSeal"
            @click="showingSeal = !showingSeal"
        >Verificar Sello Blockchain</button>
        <SealPanel v-if="showingSeal" :receipt-url="evidence.receipt_url" />

        <PhotoViewer v-if="viewing" :src="viewing.photo_url" :alt="`Foto de la evidencia del ${formatDateTime(evidence.captured_at)}`" @close="viewing = null" />
    </article>
</template>
