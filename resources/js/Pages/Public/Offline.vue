<script setup>
// US-003b: el mapa de una organización dada de baja sale de línea. Sus
// evidencias siguen verificables: el validador compara un archivo con su
// sello en Stellar, sin subirlo (US-024).
import { Head, Link, usePage } from '@inertiajs/vue3';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
</script>

<template>
    <Head title="Mapa no disponible" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <OrganizationNotice />
        <section class="flex flex-col gap-3 rounded-lg bg-white p-4">
            <p class="font-semibold">El mapa de obras de esta organización ya no está disponible.</p>
            <p class="text-sm text-slate-600">
                Si tiene un archivo que ella publicó, puede comprobar que no fue alterado: el validador lo compara con su sello en la red Stellar, sin subirlo.
            </p>
            <Link href="/verify" class="self-start rounded-lg bg-slate-900 px-3 py-3 text-sm font-semibold text-white">Verificar un archivo en el validador</Link>
        </section>
    </AppLayout>
</template>
