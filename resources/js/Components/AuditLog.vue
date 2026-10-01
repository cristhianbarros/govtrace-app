<script setup>
// US-043-MON: el log de auditoría, de 20 en 20 y lo más reciente primero.
// Cada entrada dice quién, cuándo, qué, y el valor anterior y el nuevo. La
// misma lista para el Super Administrador (todo, con la organización de
// cada entrada) y para el Administrador (solo lo de su organización).
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import { formatDateTime } from '@/lib/format.js';

const props = defineProps({
    fetchPage: { type: Function, required: true }, // (página) → { data, meta }
    showOrganization: { type: Boolean, default: false },
});

const pageNumber = ref(1);
const { data: log, loading, error, load } = useLoader(props.fetchPage);

function goTo(next) {
    pageNumber.value = next;
    load(next);
}

/** { nit: "900123456-8" } → ["nit: 900123456-8"] */
const lines = (values) => Object.entries(values ?? {}).map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : value}`);

onMounted(() => load(1));
</script>

<template>
    <LoadState :loading="loading" :error="error" :empty="log?.meta.total === 0" loading-text="Cargando el registro…" empty-text="Aún no hay nada registrado." illustration="records" @retry="load(pageNumber)">
        <ul class="flex flex-col gap-2">
            <li v-for="entry in log.data" :key="entry.id" data-test="audit-entry" class="rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                <p class="font-semibold">{{ entry.action }}</p>
                <p class="text-xs text-slate-600">
                    {{ entry.actor }} · {{ formatDateTime(entry.created_at) }}<template v-if="showOrganization && entry.organization"> · {{ entry.organization }}</template>
                </p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <p class="text-slate-500">Antes</p>
                        <p v-for="line in lines(entry.before)" :key="line" class="break-all">{{ line }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Después</p>
                        <p v-for="line in lines(entry.after)" :key="line" class="break-all">{{ line }}</p>
                    </div>
                </div>
            </li>
        </ul>

        <nav class="flex items-center justify-between gap-2 text-sm" aria-label="Páginas">
            <button type="button" class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="pageNumber <= 1" @click="goTo(pageNumber - 1)">
                Anterior
            </button>
            <span class="text-slate-600">Página {{ log.meta.current_page }} de {{ log.meta.last_page }} · {{ log.meta.total }} entradas</span>
            <button
                type="button"
                class="min-h-11 rounded-lg border bg-white px-3 py-2 font-semibold disabled:opacity-40"
                :disabled="pageNumber >= log.meta.last_page"
                @click="goTo(pageNumber + 1)"
            >
                Siguiente
            </button>
        </nav>
    </LoadState>
</template>
