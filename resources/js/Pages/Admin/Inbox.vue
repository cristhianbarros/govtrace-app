<script setup>
// US-036 / US-037: la bandeja de entrada. "Por revisar": las evidencias
// selladas y ocultas, para publicarlas o rechazarlas de a una (no hay
// publicación masiva). "Publicadas": para retirarlas dejando una lápida.
import { onMounted, ref } from 'vue';
import EvidenceCard from '@/Components/Admin/EvidenceCard.vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fetchInbox } from '@/services/api.js';

const tabs = [
    { status: 'hidden', label: 'Por revisar', empty: 'No hay evidencias por revisar.' },
    { status: 'published', label: 'Publicadas', empty: 'No hay evidencias publicadas.' },
];

const tab = ref(tabs[0]);
const notice = ref(null);
const { data: evidences, loading, error, load } = useLoader(fetchInbox);

function open(chosen) {
    tab.value = chosen;
    notice.value = null;
    load(chosen.status);
}

function decided(id, message) {
    evidences.value = evidences.value.filter((evidence) => evidence.id !== id);
    notice.value = message;
}

onMounted(() => open(tabs[0]));
</script>

<template>
    <AdminLayout title="Bandeja de entrada">
        <div class="flex rounded-lg bg-slate-200 p-1" role="tablist">
            <button
                v-for="candidate in tabs"
                :key="candidate.status"
                type="button"
                role="tab"
                :aria-selected="tab.status === candidate.status"
                class="min-h-11 flex-1 rounded-md py-2 text-sm font-semibold"
                :class="tab.status === candidate.status ? 'bg-white shadow-sm' : 'text-slate-600'"
                @click="open(candidate)"
            >{{ candidate.label }}</button>
        </div>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ notice }}</p>

        <LoadState
            :loading="loading"
            :error="error"
            :empty="evidences?.length === 0"
            loading-text="Cargando evidencias…"
            :empty-text="tab.empty"
            @retry="load(tab.status)"
        >
            <div class="flex flex-col gap-4">
                <EvidenceCard v-for="evidence in evidences" :key="evidence.id" :evidence="evidence" @decided="decided" />
            </div>
        </LoadState>
    </AdminLayout>
</template>
