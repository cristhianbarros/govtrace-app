<script setup>
// It. 46g — US-065-SEC: los códigos de recuperación del Super Administrador,
// para entrar sin su teléfono. Se muestran una sola vez, al configurar la app.
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    codes: { type: Array, required: true },
});
</script>

<template>
    <Head title="Códigos de recuperación" />
    <AppLayout title="GovTrace">
        <section class="mx-auto mt-2 flex w-full max-w-md flex-col gap-5 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 md:p-7" aria-labelledby="recovery-title">
            <div class="flex flex-col gap-2 text-center">
                <h1 id="recovery-title" class="text-2xl">Sus códigos de recuperación</h1>
                <p class="text-base text-slate-700">Si pierde su celular, entre con uno de estos códigos. Cada uno sirve una sola vez.</p>
            </div>

            <p class="rounded-lg bg-amber-50 p-3 text-base font-semibold text-amber-900">
                Guárdelos fuera del teléfono: impresos o en su gestor de contraseñas. No se volverán a mostrar.
            </p>

            <ul class="grid grid-cols-2 gap-2">
                <li v-for="code in codes" :key="code" data-test="recovery-code" class="rounded-lg bg-slate-50 p-3 text-center font-mono text-lg font-semibold tracking-wider text-slate-900 ring-1 ring-slate-200">
                    {{ code }}
                </li>
            </ul>

            <Link href="/admin/organizations" class="rounded-xl bg-brand-700 px-3 py-4 text-center text-base font-semibold text-white hover:bg-brand-800">Ya los guardé, ir al panel</Link>
        </section>
    </AppLayout>
</template>
