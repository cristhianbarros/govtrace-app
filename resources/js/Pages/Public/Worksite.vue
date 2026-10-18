<script setup>
// US-029 / US-017: la vista pública de una obra — sus contratos y la línea
// de tiempo de sus evidencias publicadas, pedidas al abrirla (R-MAP-02).
import { ShareIcon } from '@heroicons/vue/24/outline';
import { Head, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import Illustration from '@/Components/Brand/Illustration.vue';
import LoadState from '@/Components/LoadState.vue';
import CitizenReportForm from '@/Components/Public/CitizenReportForm.vue';
import CitizenVoices from '@/Components/Public/CitizenVoices.vue';
import ContractCard from '@/Components/Public/ContractCard.vue';
import EvidenceCard from '@/Components/Public/EvidenceCard.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import OversightChannels from '@/Components/Public/OversightChannels.vue';
import { useLoader } from '@/composables/useLoader.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fetchWorksite } from '@/services/api.js';

const props = defineProps({
    worksiteId: { type: String, required: true }, // it. 46c: su identificador público
});

const page = usePage();
const CONDITION = {
    green: { sign: '✓', css: 'bg-green-700 text-white', border: 'border-green-700' },
    yellow: { sign: '!', css: 'bg-yellow-400 text-slate-900', border: 'border-yellow-500' },
    red: { sign: '✕', css: 'bg-red-700 text-white', border: 'border-red-700' },
};
const { data: worksite, loading, error, load } = useLoader(() => fetchWorksite(props.worksiteId));

// It. 66c (US-069): compartir la obra — el menú del celular, o copiar el enlace. La vista previa
// (Open Graph) va en el HTML de la página, para WhatsApp y las redes.
const shared = ref(null);
async function share() {
    const url = window.location.href;
    const title = worksite.value.condition ? `${worksite.value.name} · ${worksite.value.condition.label}` : worksite.value.name;
    if (navigator.share) {
        try {
            await navigator.share({ title, text: 'Mire esta obra en GovTrace: su estado y sus evidencias selladas.', url });
        } catch {
            // La persona cerró el menú: nada que decir.
        }
        return;
    }
    await navigator.clipboard?.writeText(url);
    shared.value = 'Enlace copiado: péguelo donde quiera compartirlo.';
}

// It. 60: desde la tarjeta del mapa se llega a una evidencia (#evidencia-…) o a "Informar" (#informar).
// Esas partes existen solo después de cargar la obra: entonces se va a la que pide la dirección.
onMounted(async () => {
    await load();
    const anchor = window.location.hash.slice(1);
    if (anchor) {
        await nextTick();
        document.getElementById(anchor)?.scrollIntoView({ block: 'start' });
    }
});
</script>

<template>
    <Head :title="worksite?.name ?? 'Obra'" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo" sections :here="worksite?.name ?? 'Obra'">
        <OrganizationNotice />
        <LoadState :loading="loading" :error="error" loading-text="Cargando la obra…" empty-text="" @retry="load">
            <template v-if="worksite">
                <!-- Los objetos de SECOP II pueden ser largos y en mayúsculas: entonces, un punto más pequeño. -->
                <h1 class="mb-3" :class="worksite.name.length > 70 ? 'text-xl md:text-2xl' : 'text-2xl md:text-3xl'">{{ worksite.name }}</h1>
                <!-- It. 40b: el estado de la obra (el color de su pin) y por qué, en palabras. -->
                <section
                    v-if="worksite.condition"
                    data-test="condition"
                    :data-color="worksite.condition.color"
                    aria-label="Estado de la obra"
                    class="mb-4 flex items-start gap-3 rounded-2xl border-2 bg-white p-4 shadow-soft"
                    :class="CONDITION[worksite.condition.color].border"
                >
                    <span aria-hidden="true" class="grid size-11 shrink-0 place-items-center rounded-full text-xl font-bold" :class="CONDITION[worksite.condition.color].css">{{ CONDITION[worksite.condition.color].sign }}</span>
                    <div class="text-base">
                        <p>
                            <span class="block text-lg font-semibold">{{ worksite.condition.label }}</span>
                            {{ worksite.condition.reason }}
                        </p>
                        <!-- It. 44a (R-LEG-01): una alerta, no la decisión de una autoridad ni una "obra inconclusa". -->
                        <p class="mt-2 text-sm text-slate-700">Es una alerta de GovTrace, calculada con los datos de SECOP II y las evidencias publicadas. No es la decisión de una autoridad.</p>
                        <template v-if="worksite.condition.color === 'red'">
                            <p class="mt-2 text-sm text-slate-700">«En riesgo» no es lo mismo que «obra inconclusa». Para la ley, una obra es inconclusa cuando, un año después de vencido el plazo para liquidar su contrato, no se terminó o no presta el servicio (Ley 2020 de 2020).</p>
                            <a href="#contraloria" class="mt-1 inline-flex min-h-11 items-center text-base font-semibold underline">¿Qué puede hacer?</a>
                        </template>
                    </div>
                </section>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" class="inline-flex min-h-12 items-center gap-2 rounded-xl border-2 border-brand-700 bg-white px-4 text-base font-semibold text-brand-800 hover:bg-brand-50" @click="share">
                        <ShareIcon aria-hidden="true" class="size-5" />
                        Compartir esta obra
                    </button>
                    <p v-if="shared" role="status" class="text-base font-semibold text-emerald-800">{{ shared }}</p>
                </div>
                <!-- It. 58: sin ubicación, no está en el mapa; el ciudadano puede decir dónde queda. -->
                <p v-if="worksite.located === false" data-test="unlocated" class="mb-4 rounded-2xl bg-white p-4 text-base shadow-soft ring-1 ring-slate-900/5">Esta obra aún no está en el mapa: su ubicación está por confirmar. Si sabe dónde queda, dígaselo a la veeduría.</p>
                <section aria-label="Contratos" class="flex flex-col gap-3">
                    <ContractCard v-for="contract in worksite.contracts" :key="contract.secop_contract_id" :contract="contract" />
                </section>

                <h2 class="mb-3 mt-6 text-lg font-semibold">Evidencias publicadas</h2>
                <div v-if="worksite.timeline.length === 0" data-test="empty" class="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-6 text-center shadow-soft ring-1 ring-slate-900/5">
                    <Illustration name="evidence" size="w-36" />
                    <p class="text-base text-slate-700">Aún no hay evidencias publicadas de esta obra.</p>
                </div>
                <ol v-else class="flex flex-col gap-3">
                    <li v-for="evidence in worksite.timeline" :id="`evidencia-${evidence.report_id}`" :key="evidence.report_id" class="scroll-mt-28">
                        <EvidenceCard :evidence="evidence" />
                    </li>
                </ol>

                <!-- It. 66b (US-069): cuántos ciudadanos informaron, y "Yo también lo vi". -->
                <CitizenVoices v-if="worksite.citizens" :worksite-id="worksite.id" :citizens="worksite.citizens" />
                <!-- It. 44f (US-059-LEG): primero, a la veeduría que la vigila; después, a la Contraloría. -->
                <CitizenReportForm :worksite-id="worksite.id" :propose-from="worksite.located === false ? worksite.seat : null" />
                <OversightChannels />
            </template>
        </LoadState>
    </AppLayout>
</template>
