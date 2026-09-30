<script setup>
// US-057-LEG (it. 44c): el veedor que ya tenía cuenta declara, una sola vez
// y antes de su próximo reporte, que no tiene impedimentos para serlo. Quien
// llega invitado lo hace al activar su cuenta (Auth/SetPassword).
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import ImpedimentsDeclaration from '@/Components/ImpedimentsDeclaration.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DECLARATION_REQUIRED } from '@/lib/impediments.js';

const page = usePage();
const form = useForm({ declaration: false });
const missing = ref(null);

function submit() {
    missing.value = form.declaration ? null : DECLARATION_REQUIRED;
    if (!missing.value) {
        form.post('/declaration');
    }
}
</script>

<template>
    <Head title="Antes de reportar" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <form class="mx-auto flex w-full max-w-xl flex-col gap-4" novalidate @submit.prevent="submit">
            <h1 class="text-xl font-semibold">Antes de reportar</h1>
            <p class="text-base">Solo se hace una vez. Después sigue a «Nuevo Reporte».</p>
            <ImpedimentsDeclaration v-model="form.declaration" :error="missing ?? form.errors.declaration ?? null" />
            <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-slate-900 px-3 py-4 text-base font-semibold text-white disabled:opacity-40">
                Declarar y continuar
            </button>
        </form>
    </AppLayout>
</template>
