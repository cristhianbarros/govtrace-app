<script setup>
// It. 62c (US-009): "Tomar foto" abre la cámara trasera dentro de GovTrace, a
// pantalla completa. Con la app de la cámara del teléfono al frente, un celular
// con poca memoria cerraba GovTrace y, al volver, se reiniciaba sin la foto ni
// lo escrito. La cámara se apaga apenas se toma la foto o se cierra.
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { grabFrame, openCamera, stopCamera } from '@/lib/evidence/camera.js';
import { loadDetector } from '@/lib/evidence/faces.js';
import { holdBack } from '@/lib/backGuard.js';

// frame: el cuadro, { canvas, name } (it. 62f); photo: el archivo de la cámara del teléfono, si esta no abrió.
const emit = defineEmits(['frame', 'photo', 'close']);

const video = ref(null);
const shutter = ref(null);
const state = ref('opening'); // opening, ready, taking o failed
const error = ref('');
let stream = null;
let closed = false;
// El "atrás" del teléfono cierra la cámara, no la página: lo escrito en el formulario no se pierde.
let releaseBack = () => {};

onMounted(async () => {
    releaseBack = holdBack(() => emit('close'));
    // It. 62f: mientras la persona encuadra, el detector de rostros ya carga; la primera foto no lo espera.
    if (navigator.onLine !== false) {
        loadDetector().catch(() => {});
    }
    try {
        const opened = await openCamera();
        if (closed) {
            stopCamera(opened);
            return;
        }
        stream = opened;
        video.value.srcObject = stream;
        await video.value.play();
        state.value = 'ready';
        await nextTick();
        shutter.value?.focus();
    } catch {
        stopCamera(stream);
        state.value = 'failed';
    }
});

onBeforeUnmount(() => {
    closed = true;
    stopCamera(stream);
    releaseBack();
});

function take() {
    if (state.value !== 'ready') {
        return;
    }
    state.value = 'taking';
    error.value = '';
    try {
        const frame = grabFrame(video.value);
        stopCamera(stream);
        emit('frame', frame);
    } catch (problem) {
        error.value = problem.message;
        state.value = 'ready';
    }
}

// Si el navegador no da la cámara (sin permiso, o sin cámara), la del teléfono, como antes.
function onPhoneCamera(event) {
    const photo = event.target.files?.[0];
    if (photo) {
        emit('photo', photo);
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Tomar foto"
            class="fixed inset-0 z-[2000] flex flex-col bg-black pt-[env(safe-area-inset-top)] text-white"
            @keydown.esc="emit('close')"
        >
            <div class="relative min-h-0 flex-1">
                <!-- Se ve entera, como sale en la foto. -->
                <video ref="video" class="size-full object-contain" autoplay muted playsinline aria-hidden="true"></video>
                <p v-if="state === 'opening'" class="absolute inset-x-4 top-1/3 text-center text-lg">Abriendo la cámara…</p>
                <div v-if="state === 'failed'" role="alert" class="absolute inset-x-4 top-1/4 flex flex-col gap-3 rounded-xl bg-white p-4 text-base text-slate-900">
                    <p>No pudimos abrir la cámara aquí. Puede tomar la foto con la cámara del teléfono, o elegirla de la galería.</p>
                    <label class="flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-4 font-semibold text-white">
                        Abrir la cámara del teléfono
                        <input type="file" data-test="phone-camera" class="sr-only" accept="image/*" capture="environment" @change="onPhoneCamera" />
                    </label>
                </div>
                <p v-if="error" role="alert" class="absolute inset-x-4 bottom-4 rounded-lg bg-white p-3 text-center text-base text-red-800">{{ error }}</p>
            </div>
            <div class="flex items-center justify-between gap-3 px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <button type="button" data-test="camera-cancel" class="min-h-12 rounded-lg px-4 text-base font-semibold ring-2 ring-white/80" @click="emit('close')">Cancelar</button>
                <button
                    ref="shutter"
                    type="button"
                    data-test="shutter"
                    class="flex min-h-16 items-center gap-2 rounded-full bg-white px-6 text-lg font-bold text-slate-900 disabled:opacity-50"
                    :disabled="state !== 'ready'"
                    @click="take"
                >
                    <span aria-hidden="true">📷</span> Tomar la foto
                </button>
            </div>
        </div>
    </Teleport>
</template>
