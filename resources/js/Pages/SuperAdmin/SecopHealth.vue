<script setup>
// US-014: la salud de la sincronización con SECOP II — la última corrida:
// cuándo, cómo terminó, qué trajo para cada organización y qué descartó.
// Verde si terminó bien; rojo, con el motivo y el reintento, si falló.
import { onMounted } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { fetchSecopHealth } from '@/services/api.js';

const { data: run, loading, error, load } = useLoader(fetchSecopHealth);

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Salud de SECOP II">
        <LoadState
            :loading="loading"
            :error="error"
            :empty="run === null"
            loading-text="Cargando la última sincronización…"
            empty-text="Aún no ha corrido ninguna sincronización con SECOP II."
            @retry="load"
        >
            <section class="flex flex-col gap-3 rounded-lg bg-white p-3 text-sm">
                <div class="flex items-center gap-2">
                    <span data-test="health" class="size-3 rounded-full" :class="run.healthy ? 'bg-emerald-500' : 'bg-red-600'" aria-hidden="true"></span>
                    <span class="font-semibold">{{ run.status }}</span>
                    <span class="text-slate-600">{{ run.date }} · {{ run.started_at }} a {{ run.finished_at ?? '—' }}</span>
                </div>

                <p v-if="run.message" data-test="failure" class="rounded bg-red-50 p-2 text-red-800">{{ run.message }}</p>

                <p>
                    <span class="font-semibold">{{ run.processed }} contratos procesados</span>:
                    {{ run.inserted }} nuevos, {{ run.updated }} actualizados.
                </p>

                <ul v-if="run.per_organization.length" class="flex flex-col gap-1">
                    <li v-for="row in run.per_organization" :key="row.organization" class="flex justify-between gap-2 border-t pt-1">
                        <span>{{ row.organization }}</span>
                        <span class="shrink-0 text-slate-600">{{ row.inserted }} nuevos · {{ row.updated }} actualizados</span>
                    </li>
                </ul>

                <div>
                    <p class="font-semibold">{{ run.discarded }} descartados por no emparejar con DIVIPOLA</p>
                    <ul class="text-xs text-slate-600">
                        <li v-for="(count, location) in run.unmatched_locations" :key="location">{{ location }}: {{ count }}</li>
                    </ul>
                </div>
            </section>
        </LoadState>
    </SuperAdminLayout>
</template>
