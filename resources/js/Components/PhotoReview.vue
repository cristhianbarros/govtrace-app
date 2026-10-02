<script setup>
// It. 46e — R-PRIV-05 reescrita: la foto se revisa antes de adjuntarla, ya
// difuminada. El detector propone los rostros; quien la envía difumina a mano
// lo que no vio (un rostro lejano, una placa) tocando la foto, y puede quitar
// un recuadro que no es un rostro: la veeduría lo verá en la Bandeja.
import { computed, onMounted, ref, watch } from 'vue';
import { containsPoint, pointOnImage, renderPreview, zoneAround } from '@/lib/evidence/blur.js';

const props = defineProps({
    draft: { type: Object, required: true }, // { name, canvas, faces, detector }
    position: { type: Number, default: 1 },
    total: { type: Number, default: 1 },
});

const emit = defineEmits(['use', 'discard']);

const preview = ref(null);
const dismissed = ref([]);
const manual = ref([]);

const width = computed(() => props.draft.canvas.width);
const height = computed(() => props.draft.canvas.height);
const zones = computed(() => [...props.draft.faces.filter((_, index) => !dismissed.value.includes(index)), ...manual.value]);

const found = computed(() => {
    const count = props.draft.faces.length;
    if (props.draft.detector !== 'ok') {
        return 'No pudimos revisar si hay rostros. Mire la foto y toque a cada persona que se vea para difuminarla.';
    }
    if (count === 0) {
        return 'No encontramos rostros.';
    }
    return count === 1 ? 'Encontramos 1 rostro y lo difuminamos.' : `Encontramos ${count} rostros y los difuminamos.`;
});

// Para difuminar sin tocar la foto (con el teclado): nueve zonas, de arriba a abajo.
const GRID = [
    ['arriba a la izquierda', 1, 1], ['arriba', 3, 1], ['arriba a la derecha', 5, 1],
    ['a la izquierda', 1, 3], ['el centro', 3, 3], ['a la derecha', 5, 3],
    ['abajo a la izquierda', 1, 5], ['abajo', 3, 5], ['abajo a la derecha', 5, 5],
];

function blurAround(point) {
    manual.value = [...manual.value, { ...zoneAround(point, width.value, height.value), byHand: true }];
}

function touch(event) {
    const point = pointOnImage(event.clientX, event.clientY, preview.value.getBoundingClientRect(), width.value, height.value);
    const touched = manual.value.find((zone) => containsPoint(zone, point));
    if (touched) {
        manual.value = manual.value.filter((zone) => zone !== touched);
    } else {
        blurAround(point);
    }
}

function toggle(index) {
    dismissed.value = dismissed.value.includes(index) ? dismissed.value.filter((other) => other !== index) : [...dismissed.value, index].sort((a, b) => a - b);
}

const render = () => preview.value && renderPreview(preview.value, props.draft.canvas, zones.value);
onMounted(render);
watch(zones, render);
</script>

<template>
    <section class="flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-900/5" aria-labelledby="photo-review-title">
        <h2 id="photo-review-title" class="font-display text-lg font-semibold text-brand-900">
            Revise la foto antes de adjuntarla<template v-if="total > 1"> ({{ position }} de {{ total }})</template>
        </h2>
        <p data-test="faces-found" class="text-base font-semibold" :class="draft.detector === 'ok' ? 'text-slate-800' : 'text-amber-900'">{{ found }}</p>
        <p class="text-base text-slate-700">Si ve a una persona, sobre todo un niño, o una placa, toque la foto sobre ella para difuminarla. Toque otra vez una zona difuminada a mano para quitarla.</p>

        <canvas
            ref="preview"
            role="img"
            aria-label="La foto como se enviará; las zonas difuminadas están marcadas en amarillo"
            class="h-auto w-full cursor-crosshair touch-manipulation rounded-lg bg-slate-100"
            @click="touch"
        ></canvas>

        <ul v-if="draft.faces.length" class="flex flex-col gap-2">
            <li v-for="(face, index) in draft.faces" :key="index" data-test="detected-face" class="flex flex-wrap items-center justify-between gap-2 text-base">
                <span v-if="dismissed.includes(index)" class="text-amber-900">Rostro {{ index + 1 }}: sin difuminar. La veeduría verá que quitó este difuminado.</span>
                <span v-else>Rostro {{ index + 1 }}: difuminado.</span>
                <button type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800" @click="toggle(index)">
                    {{ dismissed.includes(index) ? 'Volver a difuminar' : 'No es un rostro' }}
                </button>
            </li>
        </ul>
        <p v-if="manual.length" class="text-base text-slate-700">Zonas difuminadas a mano: {{ manual.length }}</p>

        <details class="text-base">
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-brand-800">Difuminar una zona sin tocar la foto</summary>
            <div class="mt-2 grid grid-cols-3 gap-2">
                <button
                    v-for="[label, column, row] in GRID"
                    :key="label"
                    type="button"
                    class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-800"
                    @click="blurAround({ x: (width * column) / 6, y: (height * row) / 6 })"
                >
                    Difuminar {{ label }}
                </button>
            </div>
        </details>

        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <button type="button" class="min-h-12 rounded-xl bg-brand-700 px-4 text-base font-semibold text-white hover:bg-brand-800" @click="emit('use', { dismissed: [...dismissed], manual: [...manual] })">
                Usar esta foto
            </button>
            <button type="button" class="min-h-12 rounded-xl border border-red-300 bg-white px-4 text-base font-semibold text-red-800 hover:bg-red-50" @click="emit('discard')">
                No usar esta foto
            </button>
        </div>
    </section>
</template>
