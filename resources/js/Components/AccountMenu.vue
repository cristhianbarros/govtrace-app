<script setup>
// It. 40b (V1 de docs/mapa-funcional.md): en la cabecera de cada panel, quién
// tiene la sesión abierta y cómo salir. Salir siempre pregunta antes: un toque
// por error y una contraseña olvidada dejan a alguien fuera. Al veedor además
// le avisa si quedan reportes sin enviar, porque cerrar sesión los borra del
// teléfono (US-018).
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { clearOutbox, outboxState, refreshOutbox } from '@/composables/useOutbox.js';
import { MESSAGES } from '@/lib/outbox.js';
import { forgetOfflineCopies } from '@/lib/pwa.js';
import { logout } from '@/services/api.js';

const page = usePage();
const account = computed(() => page.props.account ?? null);
const isVeedor = computed(() => account.value?.role === 'Veedor de Campo');
const inOrganization = computed(() => Boolean(page.props.organization));

const open = ref(false);
const confirming = ref(false);
const pendingReports = ref(false);
const leaving = ref(false);
const root = ref(null);

function closeOnOutsideClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}
document.addEventListener('click', closeOnOutsideClick);
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));

async function askToLeave() {
    open.value = false;
    pendingReports.value = false;
    if (isVeedor.value) {
        await refreshOutbox();
        pendingReports.value = outboxState.count > 0;
    }
    confirming.value = true;
}

async function leave() {
    leaving.value = true;
    if (isVeedor.value) {
        await clearOutbox();
        await forgetOfflineCopies();
    }
    await logout();
    router.visit('/login');
}
</script>

<template>
    <div v-if="account" ref="root" class="relative ml-auto">
        <button
            type="button"
            aria-haspopup="menu"
            :aria-expanded="open"
            class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-base font-semibold hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-white"
            @click="open = !open"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" stroke-linecap="round" />
            </svg>
            <span class="max-w-[9rem] truncate md:max-w-[16rem]">{{ account.name }}</span>
            <span aria-hidden="true">▾</span>
        </button>

        <div v-if="open" role="menu" class="absolute right-0 z-30 mt-2 w-72 rounded-lg bg-white p-2 text-slate-900 shadow-lg ring-1 ring-slate-200">
            <p class="px-3 py-2">
                <span class="block text-base font-semibold">{{ account.name }}</span>
                <span class="block text-sm text-slate-600">{{ account.role }}</span>
                <span class="block truncate text-sm text-slate-600">{{ account.email }}</span>
            </p>
            <Link v-if="inOrganization" href="/" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-base hover:bg-slate-100">Ver el sitio público</Link>
            <Link href="/account/password" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-base hover:bg-slate-100">Cambiar contraseña</Link>
            <button type="button" role="menuitem" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-base font-semibold text-red-700 hover:bg-red-50" @click="askToLeave">Salir</button>
        </div>

        <div v-if="confirming" role="alertdialog" aria-modal="true" aria-labelledby="leave-title" class="fixed inset-0 z-50 flex items-end bg-black/60 p-4 md:items-center md:justify-center">
            <div class="w-full rounded-lg bg-white p-4 text-slate-900 md:max-w-md">
                <p id="leave-title" class="text-lg font-semibold">{{ pendingReports ? MESSAGES.logout : '¿Cerrar la sesión?' }}</p>
                <p v-if="!pendingReports" class="mt-1 text-base text-slate-700">Para volver a entrar necesitará su correo y su contraseña.</p>
                <div class="mt-4 flex flex-col gap-2 md:flex-row-reverse">
                    <button type="button" :disabled="leaving" class="min-h-12 rounded-lg bg-red-700 px-4 text-base font-semibold text-white disabled:opacity-40" @click="leave">Sí, cerrar sesión</button>
                    <button type="button" class="min-h-12 rounded-lg border border-slate-300 px-4 text-base font-semibold" @click="confirming = false">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</template>
