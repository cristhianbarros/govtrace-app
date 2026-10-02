<script setup>
// US-009: de 1 a 5 fotos o un PDF, sin mezclar. Cada archivo se prepara en
// el teléfono — optimizado o limpio, y con su SHA-256 — antes de quedar
// adjunto; lo que se adjunta es lo que se sube.
// It. 46e (R-PRIV-05): cada foto se revisa antes, de a una, con sus rostros
// ya difuminados (PhotoReview); solo al aceptarla se calcula su huella.
import { computed, ref, shallowRef } from 'vue';
import PhotoReview from '@/Components/PhotoReview.vue';
import { acceptedTypes, cannotAdd } from '@/lib/evidence/attachments.js';
import { draftPhoto, finishPhoto, kindOf, prepareEvidence } from '@/lib/evidence/prepare.js';

const evidences = defineModel({ type: Array, default: () => [] });
const processing = defineModel('processing', { type: Boolean, default: false });

const errors = ref([]);
const accept = computed(() => acceptedTypes(evidences.value));

// Las fotos elegidas, esperando su revisión; se revisan de a una. Cada borrador
// lleva su canvas: no hace falta que sea reactivo por dentro.
const toReview = shallowRef([]);
const finishing = ref(false);
const reviewed = ref(0);
const reviewing = computed(() => toReview.value[0] ?? null);

async function onChoose(event) {
    const files = Array.from(event.target.files ?? []);
    errors.value = [];
    processing.value = true;

    let attached = [...evidences.value];
    for (const file of files) {
        const kind = kindOf(file);
        // Las fotos que esperan revisión ya cuentan para el máximo.
        const refused = cannotAdd([...attached, ...toReview.value.map(() => ({ kind: 'photo' }))], kind);
        if (refused) {
            addError(refused);
            continue;
        }
        try {
            if (kind === 'photo') {
                toReview.value = [...toReview.value, await draftPhoto(file)];
                continue;
            }
            const evidence = await prepareEvidence(file);
            const tooLarge = cannotAdd(attached, kind, evidence.file.size);
            if (tooLarge) {
                addError(tooLarge);
                continue;
            }
            attached = [...attached, evidence];
        } catch (error) {
            addError(error.message);
        }
    }

    evidences.value = attached;
    processing.value = toReview.value.length > 0;
    event.target.value = '';
}

function nextPhoto() {
    toReview.value = toReview.value.slice(1);
    reviewed.value = toReview.value.length === 0 ? 0 : reviewed.value + 1;
    processing.value = toReview.value.length > 0;
}

async function usePhoto(review) {
    // Un doble toque en "Usar esta foto" no la adjunta dos veces ni se salta la siguiente.
    if (finishing.value) {
        return;
    }
    finishing.value = true;
    try {
        const evidence = await finishPhoto(reviewing.value, review);
        const tooLarge = cannotAdd(evidences.value, 'photo', evidence.file.size);
        if (tooLarge) {
            addError(tooLarge);
        } else {
            evidences.value = [...evidences.value, evidence];
        }
    } catch (error) {
        addError(error.message);
    }
    finishing.value = false;
    nextPhoto();
}

function addError(message) {
    if (!errors.value.includes(message)) {
        errors.value.push(message);
    }
}

function remove(index) {
    evidences.value = evidences.value.filter((_, position) => position !== index);
    errors.value = [];
}

const kilobytes = (bytes) => `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-sm font-semibold text-slate-700">Evidencia: de 1 a 5 fotos o un PDF</span>
        <p data-test="faces-warning" class="text-sm text-slate-700">Si en la foto aparecen personas, sobre todo niños, sus rostros se difuminan antes de enviarla.</p>

        <!-- It. 40d: elegir es claro, y la cámara, a un toque (capture: abre la trasera). -->
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <label
                class="flex min-h-14 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-3 text-base font-semibold text-slate-800"
                :class="{ 'opacity-50': !accept || processing }"
            >
                {{ reviewing ? 'Revise la foto de abajo' : processing ? 'Revisando la foto…' : 'Elegir de la galería o un PDF' }}
                <input type="file" multiple class="sr-only" :accept="accept" :disabled="!accept || processing" @change="onChoose" />
            </label>
            <label
                class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-brand-700 px-3 py-3 text-base font-semibold text-white hover:bg-brand-800"
                :class="{ 'opacity-50': !accept.startsWith('image') || processing }"
            >
                <span aria-hidden="true">📷</span> Tomar foto
                <input type="file" data-test="camera" class="sr-only" accept="image/*" capture="environment" :disabled="!accept.startsWith('image') || processing" @change="onChoose" />
            </label>
        </div>

        <PhotoReview
            v-if="reviewing"
            :key="reviewed"
            :draft="reviewing"
            :position="reviewed + 1"
            :total="reviewed + toReview.length"
            @use="usePhoto"
            @discard="nextPhoto"
        />

        <ul v-if="errors.length" role="alert" class="flex flex-col gap-1 text-sm text-red-700">
            <li v-for="error in errors" :key="error">{{ error }}</li>
        </ul>

        <ul v-if="evidences.length" class="flex flex-col gap-2">
            <li
                v-for="(evidence, index) in evidences"
                :key="evidence.sha256 + index"
                class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-sm"
            >
                <span class="truncate">{{ evidence.kind === 'pdf' ? '📄' : '📷' }} {{ evidence.file.name }} · {{ kilobytes(evidence.file.size) }}</span>
                <button type="button" data-test="remove-evidence" class="min-h-11 shrink-0 px-3 font-semibold text-red-700" @click="remove(index)">
                    Quitar
                </button>
            </li>
        </ul>
    </div>
</template>
