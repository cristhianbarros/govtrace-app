<script setup>
// US-009: de 1 a 5 fotos o un PDF, sin mezclar. Cada archivo se prepara en
// el teléfono — optimizado o limpio, y con su SHA-256 — antes de quedar
// adjunto; lo que se adjunta es lo que se sube.
// It. 46e (R-PRIV-05): cada foto se revisa antes, de a una, con sus rostros
// ya difuminados (PhotoReview); solo al aceptarla se calcula su huella.
import { computed, nextTick, ref, shallowRef } from 'vue';
import CameraCapture from '@/Components/CameraCapture.vue';
import FramePreview from '@/Components/FramePreview.vue';
import PhotoReview from '@/Components/PhotoReview.vue';
import { acceptedTypes, cannotAdd } from '@/lib/evidence/attachments.js';
import { cameraInPage } from '@/lib/evidence/camera.js';
import { loadDetector } from '@/lib/evidence/faces.js';
import { draftFrame, draftPhoto, finishPhoto, kindOf, prepareEvidence } from '@/lib/evidence/prepare.js';
import { releaseCanvas } from '@/lib/evidence/photos.js';
import { reveal, vReveal } from '@/lib/reveal.js';

const evidences = defineModel({ type: Array, default: () => [] });
const processing = defineModel('processing', { type: Boolean, default: false });

// It. 46h (US-059-LEG): el mismo selector sirve al ciudadano, con de 1 a 3 fotos y sin PDF.
const props = defineProps({
    max: { type: Number, default: 5 },
    photosOnly: { type: Boolean, default: false },
    label: { type: String, default: 'Evidencia: de 1 a 5 fotos o un PDF' },
});
const limits = computed(() => ({ max: props.max, photosOnly: props.photosOnly }));

const errors = ref([]);
const accept = computed(() => acceptedTypes(evidences.value, limits.value));

// Las fotos elegidas, esperando su revisión; se revisan de a una. Cada borrador
// lleva su canvas: no hace falta que sea reactivo por dentro.
const toReview = shallowRef([]);
const finishing = ref(false);
const reviewed = ref(0);
const reviewing = computed(() => toReview.value[0] ?? null);
// It. 62f: la foto que se está revisando ahora mismo, antes de su revisión: { name, canvas? }.
const pending = shallowRef(null);

// It. 62f: el detector de rostros empieza a cargar apenas se va a elegir una foto.
function warmUp() {
    if (navigator.onLine !== false) {
        loadDetector().catch(() => {});
    }
}

// It. 62c: donde el navegador da la cámara, "Tomar foto" la abre en la página;
// si no, la del teléfono (capture), como antes.
const inPage = cameraInPage();
const cameraOpen = ref(false);

async function onChoose(event) {
    await attach(Array.from(event.target.files ?? []));
    event.target.value = '';
}

async function onPhoto(photo) {
    cameraOpen.value = false;
    await attach([photo]);
}

// It. 62f: el cuadro de la cámara en la página, en su canvas: la cámara se cierra
// al instante, la foto se ve mientras se revisan sus rostros, y no hay JPEG que decodificar.
async function onFrame(frame) {
    cameraOpen.value = false;
    errors.value = [];
    const refused = cannotAdd([...evidences.value, ...toReview.value.map(() => ({ kind: 'photo' }))], 'photo', 0, limits.value);
    if (refused) {
        releaseCanvas(frame.canvas);
        addError(refused);
        return;
    }
    processing.value = true;
    pending.value = frame;
    try {
        toReview.value = [...toReview.value, await draftFrame(frame)];
    } finally {
        pending.value = null;
        processing.value = toReview.value.length > 0;
    }
}

async function attach(files) {
    errors.value = [];
    processing.value = true;

    let attached = [...evidences.value];
    for (const file of files) {
        const kind = kindOf(file);
        // Las fotos que esperan revisión ya cuentan para el máximo.
        const refused = cannotAdd([...attached, ...toReview.value.map(() => ({ kind: 'photo' }))], kind, 0, limits.value);
        if (refused) {
            addError(refused);
            continue;
        }
        try {
            if (kind === 'photo') {
                pending.value = { name: file.name };
                try {
                    toReview.value = [...toReview.value, await draftPhoto(file)];
                } finally {
                    pending.value = null;
                }
                continue;
            }
            const evidence = await prepareEvidence(file);
            const tooLarge = cannotAdd(attached, kind, evidence.file.size, limits.value);
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
}

// It. 62b: la foto revisada ya es un archivo (o no se usó): su canvas se suelta ya.
function nextPhoto() {
    releaseCanvas(toReview.value[0].canvas);
    toReview.value = toReview.value.slice(1);
    reviewed.value = toReview.value.length === 0 ? 0 : reviewed.value + 1;
    processing.value = toReview.value.length > 0;
    if (toReview.value.length === 0) {
        backToTheForm();
    }
}

// It. 62g (R-UX-08): la revisión era alta, y al cerrarse dejaba la pantalla en otra
// parte del formulario. Cerrada la última (usada o no), vuelven a la vista las fotos
// del formulario —con la que se adjuntó—, y el foco queda allí.
const picker = ref(null);
async function backToTheForm() {
    await nextTick();
    picker.value?.focus({ preventScroll: true });
    reveal(picker.value);
}

async function usePhoto(review) {
    // Un doble toque en "Usar esta foto" no la adjunta dos veces ni se salta la siguiente.
    if (finishing.value) {
        return;
    }
    finishing.value = true;
    try {
        const evidence = await finishPhoto(reviewing.value, review);
        const tooLarge = cannotAdd(evidences.value, 'photo', evidence.file.size, limits.value);
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
    <div ref="picker" tabindex="-1" role="group" :aria-label="label" class="flex scroll-mt-24 flex-col gap-2 focus:outline-none">
        <span class="text-sm font-semibold text-slate-700">{{ label }}</span>
        <p data-test="faces-warning" class="text-sm text-slate-700">Si en la foto aparecen personas, sobre todo niños, sus rostros se difuminan antes de enviarla.</p>

        <!-- It. 40d: elegir es claro, y la cámara, a un toque (la trasera). -->
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <label
                class="flex min-h-14 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-3 text-base font-semibold text-slate-800"
                :class="{ 'opacity-50': !accept || processing }"
            >
                {{ reviewing ? 'Revise la foto de abajo' : processing ? 'Revisando la foto…' : photosOnly ? 'Elegir de la galería' : 'Elegir de la galería o un PDF' }}
                <input type="file" multiple class="sr-only" :accept="accept" :disabled="!accept || processing" @click="warmUp" @change="onChoose" />
            </label>
            <button
                v-if="inPage"
                type="button"
                data-test="take-photo"
                class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-brand-700 px-3 py-3 text-base font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
                :disabled="!accept.startsWith('image') || processing"
                @click="cameraOpen = true"
            >
                <span aria-hidden="true">📷</span> Tomar foto
            </button>
            <label
                v-else
                class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-brand-700 px-3 py-3 text-base font-semibold text-white hover:bg-brand-800"
                :class="{ 'opacity-50': !accept.startsWith('image') || processing }"
            >
                <span aria-hidden="true">📷</span> Tomar foto
                <input type="file" data-test="camera" class="sr-only" accept="image/*" capture="environment" :disabled="!accept.startsWith('image') || processing" @click="warmUp" @change="onChoose" />
            </label>
        </div>

        <CameraCapture v-if="cameraOpen" @frame="onFrame" @photo="onPhoto" @close="cameraOpen = false" />

        <!-- It. 62f: la foto, al instante, mientras se buscan sus rostros (la revisión llega después). -->
        <section v-if="pending" v-reveal="pending.name" data-test="photo-pending" role="status" class="flex flex-col gap-2 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5">
            <FramePreview v-if="pending.canvas" :source="pending.canvas" />
            <p class="flex items-center gap-2 text-base font-semibold text-slate-800">
                <span aria-hidden="true" class="size-5 shrink-0 rounded-full border-2 border-brand-700 border-t-transparent motion-safe:animate-spin"></span>
                Revisando la foto: buscando rostros para difuminarlos…
            </p>
        </section>

        <!-- It. 62d: la revisión aparece debajo de los botones: se trae a la vista, foto por foto. -->
        <PhotoReview
            v-if="reviewing"
            :key="reviewed"
            v-reveal="reviewed"
            :draft="reviewing"
            :position="reviewed + 1"
            :total="reviewed + toReview.length"
            @use="usePhoto"
            @discard="nextPhoto"
        />

        <ul v-if="errors.length" v-reveal="errors.join(' ')" role="alert" class="flex flex-col gap-1 text-sm text-red-700">
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
