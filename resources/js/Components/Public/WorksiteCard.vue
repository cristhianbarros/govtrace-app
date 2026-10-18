<script setup>
// It. 60 (US-029): la tarjeta de una obra sobre el mapa, al tocar su pin —
// su estado y por qué, su nombre, su municipio y su valor, la última
// evidencia y hasta 3 miniaturas —, sin salir del mapa. Sube desde abajo en el
// celular y va al costado en el escritorio. Es una tarjeta que se toca, no un
// tooltip que aparece al pasar el cursor (WCAG 1.4.13): el foco pasa a ella,
// y Esc o "Cerrar" la cierran.
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import { useLoader } from '@/composables/useLoader.js';
import { formatCop, formatDay } from '@/lib/format.js';
import { fetchWorksiteCard } from '@/services/api.js';

const props = defineProps({
    worksiteId: { type: [String, Number], required: true }, // it. 46c: su identificador público
});

const emit = defineEmits(['close']);

// El mismo signo y color del pin (it. 40b): el color solo no basta.
const LOOK = {
    green: { sign: '✓', css: 'bg-green-700 text-white' },
    yellow: { sign: '!', css: 'bg-yellow-400 text-slate-900' },
    red: { sign: '✕', css: 'bg-red-700 text-white' },
};

const titleId = `tarjeta-obra-${props.worksiteId}`;
const dialog = ref(null);
const { data: card, loading, error, load } = useLoader(() => fetchWorksiteCard(props.worksiteId));

const place = () => [card.value.municipality, card.value.value !== null ? formatCop(card.value.value) : null].filter(Boolean).join(' · ');

onMounted(() => {
    dialog.value?.focus();
    load();
});
</script>

<template>
    <section
        ref="dialog"
        role="dialog"
        aria-modal="false"
        :aria-labelledby="titleId"
        tabindex="-1"
        data-test="worksite-card"
        class="fixed inset-x-0 bottom-0 z-[1100] max-h-[75dvh] overflow-y-auto rounded-t-3xl bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl ring-1 ring-slate-900/10 outline-none md:inset-x-auto md:bottom-4 md:right-4 md:top-28 md:w-96 md:rounded-3xl"
        @keydown.esc="emit('close')"
    >
        <div class="flex items-start gap-2">
            <h2 v-if="card" :id="titleId" class="flex-1 text-xl font-semibold" :class="card.name && card.name.length > 70 ? 'text-lg' : ''">{{ card.name }}</h2>
            <span v-else class="flex-1"></span>
            <button type="button" aria-label="Cerrar la tarjeta" class="grid size-11 shrink-0 place-items-center rounded-full text-2xl text-slate-700 hover:bg-slate-100" @click="emit('close')">×</button>
        </div>
        <LoadState :loading="loading" :error="error" loading-text="Cargando la obra…" empty-text="" @retry="load">
            <template v-if="card">
                <p data-test="condition" class="mt-2 flex items-start gap-2 text-base"><span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full font-bold" :class="LOOK[card.condition.color].css">{{ LOOK[card.condition.color].sign }}</span> <span><strong class="font-semibold">{{ card.condition.label }}</strong> {{ card.condition.reason }}</span></p>
                <p data-test="place" class="mt-2 text-base text-slate-800">{{ place() }}</p>
                <p data-test="last-evidence" class="mt-1 text-base text-slate-700">{{ card.last_evidence_at ? `Última evidencia publicada: ${formatDay(card.last_evidence_at)}` : 'Sin evidencias publicadas todavía.' }}</p>
                <!-- It. 66b (US-069): cuántos ciudadanos informaron, y qué hace la veeduría. Solo el número. -->
                <p v-if="card.citizens" data-test="card-citizens" class="mt-1 text-base font-semibold text-brand-900">{{ card.citizens.count === 1 ? '1 ciudadano informó' : `${card.citizens.count} ciudadanos informaron` }} sobre esta obra · {{ card.citizens.status }}</p>
                <div v-if="card.photos.length" class="mt-3 grid grid-cols-3 gap-2">
                    <Link
                        v-for="(photo, index) in card.photos"
                        :key="index"
                        data-test="card-thumbnail"
                        :href="`/worksite/${card.id}#evidencia-${photo.report_id}`"
                        :aria-label="`Ver la foto ${index + 1} de ${card.photos.length} en la página de la obra`"
                        class="aspect-square overflow-hidden rounded-xl bg-slate-100"
                    >
                        <img :src="photo.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" />
                    </Link>
                </div>
                <div class="mt-4 flex flex-col gap-2">
                    <Link :href="`/worksite/${card.id}`" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-4 text-base font-semibold text-white hover:bg-brand-800">Ver la obra</Link>
                    <Link :href="`/worksite/${card.id}#informar`" class="inline-flex min-h-12 items-center justify-center rounded-xl border-2 border-brand-700 bg-white px-4 text-base font-semibold text-brand-800 hover:bg-brand-50">Informar a la veeduría</Link>
                </div>
            </template>
        </LoadState>
    </section>
</template>
