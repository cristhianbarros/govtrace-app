<script setup>
// Las pestañas de la app del veedor, al alcance del pulgar, y "Salir". Si
// quedan reportes sin enviar en el teléfono, avisa antes: cerrar sesión los
// borra (US-018). Una sola raíz con display: contents, para que las pestañas
// sigan siendo parte de la barra de abajo.
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { clearOutbox, outboxState, refreshOutbox } from '@/composables/useOutbox.js';
import { MESSAGES } from '@/lib/outbox.js';
import { forgetOfflineCopies } from '@/lib/pwa.js';
import { logout } from '@/services/api.js';

defineProps({
    current: { type: String, required: true }, // la ruta de la pantalla abierta
});

const TABS = [
    { href: '/reports/new', label: 'Nuevo Reporte' },
    { href: '/my-reports', label: 'Mis Reportes' },
];

const confirming = ref(false);

async function leave() {
    await refreshOutbox();
    if (outboxState.count > 0) {
        confirming.value = true;
        return;
    }
    await closeSession();
}

async function closeSession() {
    confirming.value = false;
    await clearOutbox();
    await forgetOfflineCopies();
    await logout();
    router.visit('/login');
}
</script>

<template>
    <div class="contents">
    <Link
        v-for="tab in TABS"
        :key="tab.href"
        :href="tab.href"
        :aria-current="tab.href === current ? 'page' : undefined"
        class="flex min-h-14 flex-1 items-center justify-center text-sm font-semibold"
        :class="tab.href === current ? 'text-slate-900' : 'text-slate-500'"
    >{{ tab.label }}</Link>
    <button type="button" class="min-h-14 px-4 text-sm font-semibold text-slate-500" @click="leave">Salir</button>

    <div v-if="confirming" role="alertdialog" aria-modal="true" aria-labelledby="leave-warning" class="fixed inset-0 z-50 flex items-end bg-black/60 p-4 sm:items-center sm:justify-center">
        <div class="w-full rounded-lg bg-white p-4 sm:max-w-md">
            <p id="leave-warning" class="font-semibold">{{ MESSAGES.logout }}</p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row-reverse">
                <button type="button" class="min-h-11 rounded-lg bg-red-600 px-4 font-semibold text-white" @click="closeSession">Sí, cerrar sesión</button>
                <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 font-semibold" @click="confirming = false">Cancelar</button>
            </div>
        </div>
    </div>
    </div>
</template>
