<script setup>
// US-016: "Buscar Obra" — por nombre de la obra, contratista o número de
// proceso. Busca en el servidor desde 3 caracteres y cuando pasan 300 ms sin
// que el veedor escriba más.
import { useDebouncedSearch } from '@/composables/useDebouncedSearch.js';
import { searchContracts } from '@/services/api.js';

const props = defineProps({
    // It. 43g: dónde buscar; por defecto, en la organización del veedor.
    search: { type: Function, default: searchContracts },
});
const emit = defineEmits(['select']);

const { keyword, results, searching, failed } = useDebouncedSearch(props.search);
</script>

<template>
    <div class="flex flex-col gap-2">
        <label for="contract-search" class="text-sm font-semibold text-slate-700">Buscar Obra</label>
        <input
            id="contract-search"
            v-model="keyword"
            type="search"
            autocomplete="off"
            placeholder="Obra, contratista o número de proceso"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base focus:border-brand-700 focus:outline-none"
        />
        <p class="text-xs text-slate-500">Escriba al menos 3 caracteres.</p>

        <p v-if="searching" class="text-sm text-slate-500">Buscando…</p>
        <p v-else-if="failed" role="alert" class="text-sm text-red-700">No se pudo buscar. Revise su conexión y vuelva a intentarlo.</p>
        <p v-else-if="results && results.length === 0" class="text-sm text-slate-700">
            No se encontraron obras activas. Verifique el número de proceso, nombre del contratista o palabras clave de la obra.
        </p>

        <ul v-if="results && results.length" class="flex flex-col gap-2">
            <li v-for="contract in results" :key="contract.secop_contract_id">
                <button
                    type="button"
                    data-test="contract-result"
                    class="w-full rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-3 text-left active:bg-slate-100"
                    @click="emit('select', contract)"
                >
                    <span class="block font-semibold">{{ contract.object }}</span>
                    <span class="block text-sm text-slate-600">{{ contract.entity_name }}</span>
                    <span class="block text-xs text-slate-500">{{ contract.process_number }} · {{ contract.contractor_name }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
