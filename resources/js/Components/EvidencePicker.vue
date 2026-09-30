<script setup>
// US-009: de 1 a 5 fotos o un PDF, sin mezclar. Cada archivo se prepara en
// el teléfono — optimizado o limpio, y con su SHA-256 — antes de quedar
// adjunto; lo que se adjunta es lo que se sube.
import { computed, ref } from 'vue';
import { acceptedTypes, cannotAdd } from '@/lib/evidence/attachments.js';
import { kindOf, prepareEvidence } from '@/lib/evidence/prepare.js';

const evidences = defineModel({ type: Array, default: () => [] });
const processing = defineModel('processing', { type: Boolean, default: false });

const errors = ref([]);
const accept = computed(() => acceptedTypes(evidences.value));

async function onChoose(event) {
    const files = Array.from(event.target.files ?? []);
    errors.value = [];
    processing.value = true;

    let attached = [...evidences.value];
    for (const file of files) {
        const kind = kindOf(file);
        const refused = cannotAdd(attached, kind);
        if (refused) {
            addError(refused);
            continue;
        }
        try {
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
    processing.value = false;
    event.target.value = '';
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

        <label
            class="flex items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-4 text-base font-semibold text-slate-700"
            :class="{ 'opacity-50': !accept || processing }"
        >
            {{ processing ? 'Preparando archivos…' : 'Adjuntar fotos o PDF' }}
            <input type="file" multiple class="sr-only" :accept="accept" :disabled="!accept || processing" @change="onChoose" />
        </label>

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
