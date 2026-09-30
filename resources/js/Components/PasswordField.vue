<script setup>
// It. 40c: un campo de contraseña con "Mostrar": escribir a ciegas en un
// celular es difícil, y más para un adulto mayor.
import { ref } from 'vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    autocomplete: { type: String, default: 'current-password' },
    hint: { type: String, default: null },
});

const model = defineModel({ type: String, default: '' });
const visible = ref(false);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="text-base font-semibold text-slate-800">{{ label }}</label>
        <div class="flex gap-2">
            <input
                :id="id"
                v-model="model"
                :type="visible ? 'text' : 'password'"
                :autocomplete="autocomplete"
                :aria-invalid="hint ? 'true' : undefined"
                :aria-describedby="hint ? `${id}-hint` : undefined"
                class="min-h-12 w-full flex-1 rounded-lg border border-slate-300 bg-white px-3 text-base"
            />
            <button type="button" :aria-controls="id" :aria-pressed="visible ? 'true' : 'false'" class="min-h-12 shrink-0 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold" @click="visible = !visible">
                {{ visible ? 'Ocultar' : 'Mostrar' }}
            </button>
        </div>
        <p v-if="hint" :id="`${id}-hint`" class="text-sm text-red-700">{{ hint }}</p>
    </div>
</template>
