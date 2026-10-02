<script setup>
// It. 46b (US-062-ALT, US-001): lo que dicen los datos abiertos del RUES de
// una veeduría. Es una ayuda para decidir, no la decisión: el Super
// Administrador compara con el PDF de la resolución o del certificado. Sin
// role="status": la respuesta llega al cargar, no después de una acción.
import { formatDate } from '@/lib/format.js';

defineProps({
    answer: { type: Object, default: null }, // { status, message, records, data_date }
    loading: { type: Boolean, default: false },
});

const MESSAGE_STYLE = {
    not_found: 'bg-amber-50 text-amber-900',
    unavailable: 'bg-amber-50 text-amber-900',
};
</script>

<template>
    <section v-if="loading || answer" data-test="rues" aria-label="Datos abiertos del RUES" class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-sm">
        <p v-if="loading" class="text-slate-700">Consultando el RUES…</p>
        <template v-else-if="answer.status === 'found'">
            <p class="font-semibold text-emerald-800">Encontrada en el RUES</p>
            <div v-for="record in answer.records" :key="`${record.chamber}-${record.registration}`" class="flex flex-col gap-1 rounded-lg bg-white p-2 ring-1 ring-slate-900/5">
                <dl class="grid grid-cols-1 gap-1 md:grid-cols-[auto_1fr] md:gap-x-4">
                    <dt class="font-semibold text-slate-700">Razón social</dt><dd>{{ record.name }}</dd>
                    <dt class="font-semibold text-slate-700">Cámara de comercio</dt><dd>{{ record.chamber }} · Matrícula {{ record.registration }}</dd>
                    <dt class="font-semibold text-slate-700">Estado de la matrícula</dt><dd>{{ record.status }}</dd>
                    <dt class="font-semibold text-slate-700">Organización jurídica</dt><dd>{{ record.legal_form }}</dd>
                    <template v-if="record.nit"><dt class="font-semibold text-slate-700">NIT</dt><dd>{{ record.nit }}</dd></template>
                    <template v-if="record.registered_on"><dt class="font-semibold text-slate-700">Matriculada el</dt><dd>{{ formatDate(record.registered_on) }}</dd></template>
                </dl>
                <p v-if="record.updated_on" class="text-slate-600">Sus datos se actualizaron en el RUES el {{ formatDate(record.updated_on) }}.</p>
            </div>
        </template>
        <p v-else class="rounded-lg p-2" :class="MESSAGE_STYLE[answer.status] ?? 'text-slate-800'">{{ answer.message }}</p>
        <!-- La fecha del extracto mensual: una veeduría inscrita después todavía no está. -->
        <p v-if="!loading && answer.data_date" class="text-slate-600">Datos del RUES al {{ formatDate(answer.data_date) }}. Compárelos con el PDF antes de decidir.</p>
    </section>
</template>
