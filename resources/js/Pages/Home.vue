<script setup>
// El dominio central de GovTrace. No hay un mapa que mezcle organizaciones
// (R-MAP-01): cada una publica el suyo en su propio subdominio. Desde aquí
// entra el Super Administrador a su panel.
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Inicio" />
    <AppLayout title="GovTrace">
        <section class="flex flex-col gap-3 rounded-lg bg-white p-4">
            <p class="text-base font-semibold">Veeduría ciudadana de obras públicas, con evidencia sellada en la red Stellar.</p>
            <p class="text-sm text-slate-600">Cada organización veedora publica su mapa de obras en su propio subdominio.</p>
            <Link href="/login" class="self-start rounded-lg border border-slate-300 px-3 py-3 text-sm font-semibold">Entrar al panel global</Link>
        </section>
    </AppLayout>
</template>
