<script setup>
// It. 40e: el logotipo de GovTrace — una lupa que vigila, con un visto bueno
// adentro: la evidencia comprobada. En los colores de la marca (sol y pino).
defineProps({
    size: { type: String, default: 'size-9' },
});
</script>

<template>
    <svg viewBox="0 0 40 40" :class="size" class="shrink-0" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="11" fill="#f4b640" />
        <circle cx="17.5" cy="17.5" r="9" fill="#fff8e6" stroke="#0f5047" stroke-width="3.5" />
        <path d="M13.4 17.9l2.9 2.9 5.4-5.7" fill="none" stroke="#0f5047" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M24.6 24.6l6.4 6.4" stroke="#0f5047" stroke-width="4.2" stroke-linecap="round" />
    </svg>
</template>
