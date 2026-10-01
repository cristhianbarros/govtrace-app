<script setup>
// US-003b: el mapa de una organización dada de baja sale de línea. Sus
// evidencias siguen verificables: el validador compara un archivo con su
// sello en Stellar, sin subirlo (US-024).
import { Head, Link, usePage } from '@inertiajs/vue3';
import Illustration from '@/Components/Brand/Illustration.vue';
import OrganizationNotice from '@/Components/Public/OrganizationNotice.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
</script>

<template>
    <Head title="Mapa no disponible" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <OrganizationNotice />
        <section class="flex flex-col items-center gap-3 rounded-3xl bg-white p-5 text-center shadow-soft ring-1 ring-slate-900/5">
            <Illustration name="offline" size="w-36" />
            <p class="text-lg font-semibold">El mapa de obras de esta organización ya no está disponible.</p>
            <p class="max-w-prose text-base text-slate-700">
                Si tiene un archivo que ella publicó, puede comprobar que no fue alterado: el validador lo compara con su sello en la red Stellar, sin subirlo.
            </p>
            <Link href="/verify" class="inline-flex min-h-12 items-center rounded-xl bg-brand-700 px-4 text-base font-semibold text-white hover:bg-brand-800">Verificar un archivo en el validador</Link>
        </section>
    </AppLayout>
</template>
