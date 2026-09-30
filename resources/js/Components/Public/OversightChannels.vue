<script setup>
// It. 44a (US-055-LEG): cualquier ciudadano puede denunciar ante la
// Contraloría, sin ser veedor ni tener cuenta (Ley 1757 de 2015, art. 69), y
// avisarle de una obra inconclusa (Ley 2020 de 2020, art. 8).
import { CONTRALORIA } from '@/lib/oversight.js';

const LINK = 'inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-brand-700 bg-white px-3 text-base font-semibold text-brand-800 hover:bg-brand-50';
</script>

<template>
    <section id="contraloria" aria-labelledby="contraloria-title" class="mt-6 rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 p-4">
        <h2 id="contraloria-title" class="text-lg font-semibold">¿Sabe de un problema en esta obra?</h2>
        <p class="mt-2 text-base">
            Cualquier ciudadano puede denunciar ante la Contraloría el mal uso de recursos públicos, o avisarle de una obra inconclusa.
            No hace falta ser veedor ni tener cuenta en GovTrace (Ley 1757 de 2015, art. 69, y Ley 2020 de 2020, art. 8).
        </p>
        <div class="mt-3 flex flex-col gap-2 md:flex-row md:flex-wrap">
            <a v-for="phone in CONTRALORIA.phones" :key="phone.href" :href="phone.href" :class="LINK">{{ phone.label }}</a>
            <a :href="CONTRALORIA.online.href" target="_blank" rel="noopener" :class="LINK">{{ CONTRALORIA.online.label }}</a>
            <a :href="CONTRALORIA.channels.href" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-base font-semibold underline">{{ CONTRALORIA.channels.label }}</a>
        </div>
        <p class="mt-3 text-sm text-slate-700">
            La Contraloría General atiende las denuncias sobre recursos nacionales. Si la obra se paga con recursos del departamento o del municipio, puede ser competente la contraloría de ese territorio.
        </p>
    </section>
</template>
