<script setup>
// US-057-LEG (it. 44c): los casos en que la ley no deja ser veedor, y la
// casilla con que el veedor declara que no está en ninguno. La usan la
// activación de la cuenta y, para quien ya la tenía, "Antes de reportar".
import { IMPEDIMENTS } from '@/lib/impediments.js';

defineProps({
    modelValue: { type: Boolean, required: true },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <section data-test="impediments" aria-labelledby="impediments-title" class="rounded-lg border border-slate-300 bg-white p-3 text-base">
        <h2 id="impediments-title" class="font-semibold">Quién no puede ser veedor</h2>
        <p class="mt-1">La ley no deja ser veedor a quien tiene un conflicto de interés con la obra (Ley 850 de 2003, artículo 19). Lea estos casos:</p>
        <ul class="mt-2 flex list-disc flex-col gap-2 pl-5">
            <li v-for="impediment in IMPEDIMENTS" :key="impediment">{{ impediment }}</li>
        </ul>
        <p class="mt-2">Si más adelante llega a estar en uno de estos casos frente a una obra, no reporte sobre ella y avísele a su veeduría.</p>
        <label for="declaration" class="mt-3 flex min-h-11 items-start gap-3 font-semibold">
            <input
                id="declaration"
                type="checkbox"
                class="mt-0.5 size-6 shrink-0"
                :checked="modelValue"
                @change="emit('update:modelValue', $event.target.checked)"
            />
            Declaro que no estoy en ninguno de estos casos.
        </label>
        <p v-if="error" role="alert" class="mt-1 text-sm text-red-700">{{ error }}</p>
    </section>
</template>
