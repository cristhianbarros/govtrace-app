<script setup>
// US-038-CFG: los parámetros globales. Los configurables se cambian de a
// uno y rigen desde ese momento, sin desplegar; un reporte capturado antes
// se valida con el valor de entonces. Los fijos se muestran, sin editar.
import { onMounted, reactive, ref, watch } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { fetchParameters, updateParameter } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: panel, loading, error, load } = useLoader(fetchParameters);

const values = reactive({});
const refused = reactive({});
const saving = ref(null);
const saved = ref(null);

watch(panel, (current) => {
    for (const parameter of current?.configurable ?? []) {
        values[parameter.key] = parameter.value;
    }
});

async function save(key) {
    saved.value = null;
    refused[key] = null;
    saving.value = key;
    try {
        saved.value = (await updateParameter(key, values[key])).message;
        await load();
    } catch (failure) {
        refused[key] = errorMessage(failure);
    } finally {
        saving.value = null;
    }
}

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Parámetros">
        <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

        <LoadState :loading="loading" :error="error" loading-text="Cargando parámetros…" empty-text="" @retry="load">
            <section class="flex flex-col gap-2">
                <h3 class="text-sm font-semibold text-slate-700">Configurables</h3>
                <form
                    v-for="parameter in panel.configurable"
                    :key="parameter.key"
                    :data-test="`parameter-${parameter.key}`"
                    class="flex flex-col gap-2 rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5"
                    novalidate
                    @submit.prevent="save(parameter.key)"
                >
                    <label :for="parameter.key" class="font-semibold">{{ parameter.label }} <span class="font-normal text-slate-500">({{ parameter.unit }})</span></label>
                    <div class="flex gap-2">
                        <input :id="parameter.key" v-model="values[parameter.key]" type="text" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-base" />
                        <button type="submit" :disabled="saving === parameter.key" class="min-h-11 rounded-xl bg-brand-700 px-3 py-2 font-semibold text-white disabled:opacity-40 hover:bg-brand-800">Guardar</button>
                    </div>
                    <p v-if="refused[parameter.key]" role="alert" class="text-xs text-red-700">{{ refused[parameter.key] }}</p>
                </form>
            </section>

            <section class="flex flex-col gap-2">
                <h3 class="text-sm font-semibold text-slate-700">Fijos en el código</h3>
                <p class="text-xs text-slate-500">Son las reglas con que la plataforma certifica la evidencia: no se configuran.</p>
                <div v-for="parameter in panel.fixed" :key="parameter.key" :data-test="`fixed-${parameter.key}`" class="flex justify-between gap-2 rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                    <span>{{ parameter.label }}</span>
                    <span class="text-slate-600">{{ parameter.value }}</span>
                </div>
            </section>
        </LoadState>
    </SuperAdminLayout>
</template>
