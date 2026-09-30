<script setup>
// El dominio central de GovTrace. No hay un mapa que mezcle organizaciones
// (R-MAP-01): cada una publica el suyo. It. 40d (V5): es la puerta de entrada
// para cualquiera — qué es, cómo funciona y el directorio de veedurías, cada
// una con el enlace a su mapa. El acceso del Super Administrador, al pie.
import { BuildingOffice2Icon, CameraIcon, CheckBadgeIcon, MapIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import WorksIllustration from '@/Components/Brand/WorksIllustration.vue';
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
        <div class="flex flex-col gap-8">
            <!-- It. 40e: la bienvenida — una banda en verde pino, con la ilustración y el camino a las veedurías. -->
            <section class="overflow-hidden rounded-3xl bg-linear-to-br from-brand-800 via-brand-700 to-brand-600 p-6 text-white shadow-soft md:p-10">
                <div class="grid items-center gap-6 md:grid-cols-[1fr_16rem]">
                    <div>
                        <p class="text-base font-semibold text-accent-300">Control social, con evidencia que nadie puede cambiar</p>
                        <h1 class="mt-2 text-3xl leading-tight text-white md:text-5xl">Veeduría ciudadana de obras públicas</h1>
                        <p class="mt-3 max-w-prose text-lg text-brand-50">Aquí las veedurías ciudadanas muestran, con fotos, cómo avanzan las obras públicas de su territorio.</p>
                        <a href="#veedurias" class="mt-5 inline-flex min-h-12 items-center rounded-xl bg-accent-400 px-5 text-base font-semibold text-slate-900 shadow-sm hover:bg-accent-300">Ver las veedurías</a>
                    </div>
                    <div class="mx-auto w-48 md:w-full"><WorksIllustration /></div>
                </div>
            </section>

            <section aria-labelledby="how-title">
                <h2 id="how-title" class="text-2xl">¿Cómo funciona?</h2>
                <ol data-test="how" class="mt-4 grid gap-3 md:grid-cols-3">
                    <li v-for="(step, index) in STEPS" :key="index" class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700">
                                <component :is="step.icon" aria-hidden="true" class="size-7" />
                            </span>
                            <span aria-hidden="true" class="font-display text-3xl font-semibold text-warm-500">{{ index + 1 }}</span>
                        </div>
                        <p class="text-base"><span class="sr-only">{{ index + 1 }}.</span> {{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section id="veedurias" aria-labelledby="directory-title" class="flex scroll-mt-20 flex-col gap-3">
                <h2 id="directory-title" class="text-2xl">Veedurías en GovTrace</h2>
                <p v-if="organizations.length === 0" class="rounded-2xl bg-white p-5 text-base text-slate-700 shadow-soft ring-1 ring-slate-900/5">Aún no hay veedurías publicando en GovTrace.</p>
                <ul v-else class="grid gap-3 md:grid-cols-2">
                    <li v-for="organization in organizations" :key="organization.url" data-test="organization" class="flex flex-col justify-between gap-4 rounded-2xl border-l-8 border-brand-600 bg-white p-5 shadow-soft">
                        <div class="flex items-start gap-3">
                            <BuildingOffice2Icon aria-hidden="true" class="mt-1 size-7 shrink-0 text-brand-700" />
                            <div>
                                <p class="font-display text-xl font-semibold text-brand-900">{{ organization.name }}</p>
                                <p v-if="organization.territory" class="text-base text-slate-700">Vigila: {{ organization.territory }}</p>
                                <p v-if="organization.suspended" class="mt-1 text-sm font-semibold text-amber-900">Suspendida por ahora: sus evidencias siguen a la vista.</p>
                            </div>
                        </div>
                        <a :href="organization.url" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-4 text-base font-semibold text-white shadow-sm hover:bg-brand-800">Ver su mapa de obras</a>
                    </li>
                </ul>
                <p class="text-base text-slate-700">¿Es veedor? Entre desde el mapa de su veeduría, con el botón "Entrar".</p>
            </section>

            <footer class="flex flex-wrap gap-x-6 border-t border-slate-200 pt-4">
                <Link href="/login" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Acceso para administradores de GovTrace</Link>
                <!-- US-058-LEG (it. 44e). -->
                <Link href="/privacidad" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-700 underline">Política de tratamiento de datos</Link>
            </footer>
        </div>
    </AppLayout>
</template>
