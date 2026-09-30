<script setup>
// El dominio central de GovTrace. No hay un mapa que mezcle organizaciones
// (R-MAP-01): cada una publica el suyo. It. 40d (V5): es la puerta de entrada
// para cualquiera — qué es, cómo funciona y el directorio de veedurías, cada
// una con el enlace a su mapa. El acceso del Super Administrador, al pie.
import { BuildingOffice2Icon, CameraIcon, CheckBadgeIcon, MapIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    organizations: { type: Array, default: () => [] }, // [{ name, territory, url, suspended }]
});

const STEPS = [
    { icon: CameraIcon, text: 'Los veedores de cada veeduría ciudadana visitan las obras públicas y toman fotos con su celular.' },
    { icon: CheckBadgeIcon, text: 'Cada foto recibe un sello digital en el momento: nadie puede borrarla ni cambiarla después.' },
    { icon: MapIcon, text: 'La veeduría las revisa y las publica en su mapa, donde cualquiera las ve y comprueba que son originales.' },
];
</script>

<template>
    <Head title="Inicio" />
    <AppLayout title="GovTrace">
        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">Veeduría ciudadana de obras públicas</h1>
                <p class="text-base text-slate-700">Aquí las veedurías ciudadanas muestran, con fotos, cómo avanzan las obras públicas de su territorio.</p>
            </section>

            <section aria-labelledby="how-title" class="rounded-lg bg-white p-4">
                <h2 id="how-title" class="text-lg font-semibold">¿Cómo funciona?</h2>
                <ol data-test="how" class="mt-3 flex flex-col gap-3">
                    <li v-for="(step, index) in STEPS" :key="index" class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-900 text-white">
                            <component :is="step.icon" aria-hidden="true" class="size-6" />
                        </span>
                        <p class="text-base"><span class="font-semibold">{{ index + 1 }}.</span> {{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section aria-labelledby="directory-title" class="flex flex-col gap-3">
                <h2 id="directory-title" class="text-lg font-semibold">Veedurías en GovTrace</h2>
                <p v-if="organizations.length === 0" class="rounded-lg bg-white p-4 text-base text-slate-700">Aún no hay veedurías publicando en GovTrace.</p>
                <ul v-else class="flex flex-col gap-3">
                    <li v-for="organization in organizations" :key="organization.url" data-test="organization" class="flex flex-col gap-3 rounded-lg bg-white p-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start gap-3">
                            <BuildingOffice2Icon aria-hidden="true" class="mt-0.5 size-7 shrink-0 text-slate-700" />
                            <div>
                                <p class="text-lg font-semibold">{{ organization.name }}</p>
                                <p v-if="organization.territory" class="text-base text-slate-700">Vigila: {{ organization.territory }}</p>
                                <p v-if="organization.suspended" class="mt-1 text-sm font-semibold text-amber-900">Suspendida por ahora: sus evidencias siguen a la vista.</p>
                            </div>
                        </div>
                        <a :href="organization.url" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-slate-900 px-4 text-base font-semibold text-white">Ver su mapa de obras</a>
                    </li>
                </ul>
                <p class="text-sm text-slate-700">¿Es veedor? Entre desde el mapa de su veeduría, con el botón "Entrar".</p>
            </section>

            <footer class="border-t border-slate-200 pt-4">
                <Link href="/login" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Acceso para administradores de GovTrace</Link>
                <!-- US-058-LEG (it. 44e). -->
                <Link href="/privacidad" class="ml-4 inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Política de tratamiento de datos</Link>
            </footer>
        </div>
    </AppLayout>
</template>
