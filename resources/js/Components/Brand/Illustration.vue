<script>
// It. 40f (R-UX-10): las ilustraciones de GovTrace — planas, redondeadas y en
// los colores de la marca (pino, menta, sol, terracota y crema), como la de
// la bienvenida (WorksIllustration). Acompañan las pantallas vacías, los
// errores y los encabezados: nunca dicen algo que el texto no diga, así que
// se ocultan a los lectores de pantalla.
export const ILLUSTRATIONS = ['empty', 'evidence', 'inbox-done', 'search', 'team', 'reports', 'messages', 'records', 'works', 'map', 'offline', 'error', 'validator', 'stats', 'welcome', 'privacy'];
</script>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: 'empty' },
    size: { type: String, default: 'w-40' },
    // Un círculo menta detrás del dibujo; sin él, para fondos de color.
    backdrop: { type: Boolean, default: true },
});

const shown = computed(() => (ILLUSTRATIONS.includes(props.name) ? props.name : 'empty'));
</script>

<template>
    <svg viewBox="0 0 160 120" :class="size" class="block h-auto shrink-0" :data-illustration="shown" aria-hidden="true" focusable="false">
        <circle v-if="backdrop" cx="80" cy="60" r="54" fill="#edf7f4" />
        <ellipse cx="80" cy="106" rx="50" ry="5" fill="#a7d8cb" opacity=".55" />

        <!-- Nada todavía: una caja abierta, con brillos. -->
        <g v-if="shown === 'empty'">
            <path d="M50 58 38 44l30-12 12 14Z" fill="#facf72" />
            <path d="M110 58l12-14-30-12-12 14Z" fill="#f4b640" />
            <path d="M50 58l30-12 30 12-30 12Z" fill="#15665a" />
            <path d="M50 58l30 12v32L50 90Z" fill="#f4b640" />
            <path d="M80 70l30-12v32l-30 12Z" fill="#facf72" />
            <path d="M66 82l6 2.4" stroke="#fff8e6" stroke-width="3" stroke-linecap="round" />
            <path d="M128 26l2.2 6.3 6.3 2.2-6.3 2.2-2.2 6.3-2.2-6.3-6.3-2.2 6.3-2.2Z" fill="#d1603d" />
            <path d="M34 28l1.5 4 4 1.5-4 1.5-1.5 4-1.5-4-4-1.5 4-1.5Z" fill="#1f7a6c" />
            <circle cx="132" cy="74" r="3.5" fill="#a7d8cb" />
            <circle cx="26" cy="66" r="2.5" fill="#f4b640" />
        </g>

        <!-- Aún sin evidencias: la cámara del veedor y una foto por tomar. -->
        <g v-else-if="shown === 'evidence'">
            <g transform="rotate(-10 106 50)">
                <rect x="84" y="26" width="46" height="40" rx="5" fill="#fff" stroke="#a7d8cb" stroke-width="2.5" />
                <circle cx="97" cy="39" r="5" fill="#facf72" />
                <path d="M88 60l13-13 8 8 6-6 11 11Z" fill="#1f7a6c" />
            </g>
            <rect x="40" y="46" width="11" height="6" rx="2.5" fill="#d1603d" />
            <path d="M56 52l6-9h22l6 9Z" fill="#0f5047" />
            <rect x="34" y="50" width="80" height="52" rx="11" fill="#0f5047" />
            <rect x="34" y="62" width="80" height="6" fill="#15665a" />
            <circle cx="74" cy="76" r="18" fill="#fff8e6" />
            <circle cx="74" cy="76" r="12" fill="#1f7a6c" />
            <circle cx="74" cy="76" r="6" fill="#0b3a34" />
            <circle cx="69.5" cy="71.5" r="3" fill="#d3ece5" />
            <rect x="98" y="56" width="10" height="6" rx="2" fill="#f4b640" />
            <path d="M130 80l1.8 5 5 1.8-5 1.8-1.8 5-1.8-5-5-1.8 5-1.8Z" fill="#f4b640" />
        </g>

        <!-- Bandeja al día: la bandeja vacía y un visto bueno. -->
        <g v-else-if="shown === 'inbox-done'">
            <path d="M40 70l12-26h56l12 26v28a6 6 0 0 1-6 6H46a6 6 0 0 1-6-6Z" fill="#1f7a6c" />
            <path d="M52 50h56l9 20H43Z" fill="#d3ece5" />
            <path d="M40 70h24a16 9 0 0 0 32 0h24v28a6 6 0 0 1-6 6H46a6 6 0 0 1-6-6Z" fill="#0f5047" />
            <circle cx="114" cy="36" r="17" fill="#f4b640" />
            <path d="M106 36.5l5.5 5.5 10-10.5" fill="none" stroke="#0f5047" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M30 40l1.6 4.4 4.4 1.6-4.4 1.6-1.6 4.4-1.6-4.4-4.4-1.6 4.4-1.6Z" fill="#d1603d" />
            <circle cx="138" cy="70" r="3" fill="#a7d8cb" />
        </g>

        <!-- Sin resultados: la lupa sobre el mapa, con su pregunta. -->
        <g v-else-if="shown === 'search'">
            <path d="M26 42l28-8v58l-28 8Z" fill="#d3ece5" />
            <path d="M54 34l28 8v58l-28-8Z" fill="#a7d8cb" />
            <path d="M82 42l28-8v58l-28 8Z" fill="#d3ece5" />
            <path d="M34 84c10-4 14-18 26-16s14 14 26 6" fill="none" stroke="#d1603d" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 7" />
            <path d="M115 77l14 14" stroke="#0f5047" stroke-width="9" stroke-linecap="round" />
            <circle cx="102" cy="62" r="20" fill="#fff8e6" stroke="#0f5047" stroke-width="6" />
            <path d="M96 57a6 6 0 1 1 8.4 5.5c-1.7.8-2.4 1.9-2.4 3.7" fill="none" stroke="#1f7a6c" stroke-width="3.5" stroke-linecap="round" />
            <circle cx="102" cy="72" r="2.4" fill="#1f7a6c" />
            <circle cx="40" cy="58" r="4" fill="#f4b640" />
        </g>

        <!-- Sin gente todavía: tres vecinos y la invitación. -->
        <g v-else-if="shown === 'team'">
            <path d="M28 102a20 20 0 0 1 40 0Z" fill="#a7d8cb" />
            <circle cx="48" cy="68" r="11" fill="#e8b48f" />
            <path d="M37 66a11 11 0 0 1 22 0c-6-1-12-4-14-8-2 4-5 7-8 8Z" fill="#3b2a20" />
            <path d="M92 102a20 20 0 0 1 40 0Z" fill="#f4b640" />
            <circle cx="112" cy="68" r="11" fill="#f1c9a5" />
            <path d="M101 67a11 11 0 0 1 22-1c-3-5-17-6-22 1Z" fill="#a4432a" />
            <path d="M54 104a26 26 0 0 1 52 0Z" fill="#1f7a6c" />
            <path d="M74 80h12l-6 10Z" fill="#fff8e6" />
            <circle cx="80" cy="60" r="14" fill="#c98a5e" />
            <path d="M66 58a14 14 0 0 1 28 0c-8 0-16-3-20-9-1 4-4 7-8 9Z" fill="#2a1d16" />
            <circle cx="132" cy="36" r="13" fill="#d1603d" />
            <path d="M132 30v12M126 36h12" stroke="#fff8e6" stroke-width="3.5" stroke-linecap="round" />
        </g>

        <!-- Sin reportes enviados: la planilla del veedor y el punto de la obra. -->
        <g v-else-if="shown === 'reports'">
            <rect x="44" y="28" width="62" height="78" rx="9" fill="#1f7a6c" />
            <rect x="51" y="38" width="48" height="60" rx="4" fill="#fff" />
            <rect x="63" y="22" width="24" height="13" rx="5" fill="#f4b640" />
            <rect x="58" y="49" width="7" height="7" rx="2" fill="#a7d8cb" />
            <rect x="69" y="50" width="24" height="5" rx="2.5" fill="#d3ece5" />
            <rect x="58" y="63" width="7" height="7" rx="2" fill="#a7d8cb" />
            <rect x="69" y="64" width="20" height="5" rx="2.5" fill="#d3ece5" />
            <rect x="58" y="77" width="7" height="7" rx="2" fill="#a7d8cb" />
            <rect x="69" y="78" width="16" height="5" rx="2.5" fill="#d3ece5" />
            <path d="M120 42a15 15 0 0 1 15 15c0 11-15 26-15 26s-15-15-15-26a15 15 0 0 1 15-15Z" fill="#d1603d" />
            <circle cx="120" cy="57" r="5.5" fill="#fff8e6" />
            <ellipse cx="120" cy="90" rx="9" ry="2.5" fill="#a4432a" opacity=".35" />
        </g>

        <!-- Sin mensajes: el sobre y la conversación por empezar. -->
        <g v-else-if="shown === 'messages'">
            <rect x="32" y="50" width="76" height="52" rx="7" fill="#1f7a6c" />
            <path d="M35 54l35 26 35-26" fill="none" stroke="#d3ece5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M35 99l26-22M105 99 79 77" stroke="#15665a" stroke-width="3" stroke-linecap="round" />
            <path d="M100 22h34a9 9 0 0 1 9 9v18a9 9 0 0 1-9 9h-18l-11 10V58h-5a9 9 0 0 1-9-9V31a9 9 0 0 1 9-9Z" fill="#f4b640" />
            <circle cx="106" cy="40" r="3.2" fill="#0f5047" />
            <circle cx="117" cy="40" r="3.2" fill="#0f5047" />
            <circle cx="128" cy="40" r="3.2" fill="#0f5047" />
            <circle cx="28" cy="38" r="3" fill="#d1603d" />
        </g>

        <!-- Sin registros: la carpeta con sus papeles. -->
        <g v-else-if="shown === 'records'">
            <path d="M34 46a6 6 0 0 1 6-6h22l8 8h50a6 6 0 0 1 6 6v44a6 6 0 0 1-6 6H40a6 6 0 0 1-6-6Z" fill="#f4b640" />
            <rect x="46" y="38" width="56" height="40" rx="3" fill="#fff" transform="rotate(-6 74 58)" />
            <rect x="56" y="34" width="56" height="40" rx="3" fill="#fff8e6" transform="rotate(5 84 54)" />
            <rect x="64" y="44" width="30" height="4" rx="2" fill="#a7d8cb" transform="rotate(5 84 54)" />
            <rect x="64" y="52" width="22" height="4" rx="2" fill="#a7d8cb" transform="rotate(5 84 54)" />
            <path d="M34 62h92v36a6 6 0 0 1-6 6H40a6 6 0 0 1-6-6Z" fill="#facf72" />
            <rect x="68" y="74" width="24" height="6" rx="3" fill="#f4b640" />
            <path d="M130 28l1.8 5 5 1.8-5 1.8-1.8 5-1.8-5-5-1.8 5-1.8Z" fill="#1f7a6c" />
        </g>

        <!-- Sin obras todavía: la valla y el cono de la obra por venir. -->
        <g v-else-if="shown === 'works'">
            <circle cx="44" cy="30" r="10" fill="#facf72" />
            <rect x="44" y="62" width="6" height="40" rx="2" fill="#0f5047" />
            <rect x="96" y="62" width="6" height="40" rx="2" fill="#0f5047" />
            <rect x="32" y="50" width="82" height="20" rx="5" fill="#fff8e6" stroke="#0f5047" stroke-width="2.5" />
            <path d="M46 52h9l-8 16h-9ZM66 52h9l-8 16h-9ZM86 52h9l-8 16h-9ZM106 52h6l-8 16h-9Z" fill="#d1603d" />
            <path d="M120 102l9-42h6l9 42Z" fill="#d1603d" />
            <path d="M124.6 80h18.8l1.5 7h-21.8Z" fill="#fff8e6" />
            <rect x="116" y="100" width="32" height="6" rx="2" fill="#a4432a" />
            <path d="M24 102h20" stroke="#a7d8cb" stroke-width="4" stroke-linecap="round" />
        </g>

        <!-- El mapa de la veeduría: sus obras, cada una con el color de su estado. -->
        <g v-else-if="shown === 'map'">
            <path d="M26 40l30-8v62l-30 8Z" fill="#d3ece5" />
            <path d="M56 32l30 8v62l-30-8Z" fill="#a7d8cb" />
            <path d="M86 40l30-8v62l-30 8Z" fill="#d3ece5" />
            <path d="M30 72c14-2 22-14 36-10s20 18 46 6" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" />
            <path d="M44 38a10 10 0 0 1 10 10c0 7-10 17-10 17s-10-10-10-17a10 10 0 0 1 10-10Z" fill="#15803d" />
            <circle cx="44" cy="48" r="3.5" fill="#fff8e6" />
            <path d="M74 50a10 10 0 0 1 10 10c0 7-10 17-10 17s-10-10-10-17a10 10 0 0 1 10-10Z" fill="#f4b640" />
            <circle cx="74" cy="60" r="3.5" fill="#fff8e6" />
            <path d="M102 62a10 10 0 0 1 10 10c0 7-10 17-10 17s-10-10-10-17a10 10 0 0 1 10-10Z" fill="#d1603d" />
            <circle cx="102" cy="72" r="3.5" fill="#fff8e6" />
            <circle cx="128" cy="34" r="13" fill="#0f5047" />
            <path d="M121.5 34.5l4.5 4.5 8.5-9" fill="none" stroke="#facf72" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
        </g>

        <!-- Sin conexión: la nube y la señal cortada. -->
        <g v-else-if="shown === 'offline'">
            <path d="M48 92a20 20 0 0 1 1-40 26 26 0 0 1 49-7 20 20 0 0 1 16 47Z" fill="#d3ece5" />
            <path d="M62 66a25 25 0 0 1 36 0M70 74a14 14 0 0 1 20 0" fill="none" stroke="#1f7a6c" stroke-width="5" stroke-linecap="round" />
            <circle cx="80" cy="82" r="4.5" fill="#1f7a6c" />
            <path d="M60 52l40 40" stroke="#d1603d" stroke-width="5" stroke-linecap="round" />
            <circle cx="126" cy="30" r="3" fill="#f4b640" />
            <circle cx="34" cy="40" r="2.5" fill="#a7d8cb" />
        </g>

        <!-- Error: el enchufe que se soltó. -->
        <g v-else-if="shown === 'error'">
            <path d="M18 80h32" stroke="#0f5047" stroke-width="5" stroke-linecap="round" />
            <rect x="46" y="68" width="24" height="24" rx="6" fill="#1f7a6c" />
            <rect x="69" y="72" width="11" height="4.5" rx="2" fill="#a7d8cb" />
            <rect x="69" y="83.5" width="11" height="4.5" rx="2" fill="#a7d8cb" />
            <rect x="94" y="66" width="26" height="28" rx="7" fill="#d1603d" />
            <rect x="94" y="72" width="5" height="4.5" rx="2" fill="#fff8e6" />
            <rect x="94" y="83.5" width="5" height="4.5" rx="2" fill="#fff8e6" />
            <path d="M120 80h22" stroke="#a4432a" stroke-width="5" stroke-linecap="round" />
            <path d="M87 60v-10M78 63l-6-6M96 63l6-6" stroke="#f4b640" stroke-width="4" stroke-linecap="round" />
        </g>

        <!-- Validar: el archivo y el escudo de su sello. -->
        <g v-else-if="shown === 'validator'">
            <path d="M42 30a6 6 0 0 1 6-6h30l16 16v52a6 6 0 0 1-6 6H48a6 6 0 0 1-6-6Z" fill="#fff" stroke="#a7d8cb" stroke-width="2.5" />
            <path d="M78 24v12a4 4 0 0 0 4 4h12" fill="#d3ece5" />
            <rect x="52" y="50" width="30" height="5" rx="2.5" fill="#d3ece5" />
            <rect x="52" y="61" width="24" height="5" rx="2.5" fill="#d3ece5" />
            <rect x="52" y="72" width="18" height="5" rx="2.5" fill="#d3ece5" />
            <path d="M108 50l24 9v15c0 17-11 27-24 33-13-6-24-16-24-33V59Z" fill="#1f7a6c" />
            <path d="M108 56l18 6.8v11.2c0 12.6-8 20.2-18 25-10-4.8-18-12.4-18-25V62.8Z" fill="#15665a" />
            <path d="M99.5 77l6 6 11-11.5" fill="none" stroke="#facf72" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M132 30l1.8 5 5 1.8-5 1.8-1.8 5-1.8-5-5-1.8 5-1.8Z" fill="#d1603d" />
        </g>

        <!-- Estadísticas: barras que crecen. -->
        <g v-else-if="shown === 'stats'">
            <rect x="40" y="72" width="16" height="30" rx="4" fill="#a7d8cb" />
            <rect x="62" y="58" width="16" height="44" rx="4" fill="#1f7a6c" />
            <rect x="84" y="44" width="16" height="58" rx="4" fill="#f4b640" />
            <rect x="106" y="62" width="16" height="40" rx="4" fill="#d1603d" />
            <path d="M32 102h98" stroke="#0f5047" stroke-width="3" stroke-linecap="round" />
            <path d="M48 60l22-14 22-14 22 18" fill="none" stroke="#0f5047" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="48" cy="60" r="4" fill="#fff8e6" stroke="#0f5047" stroke-width="2.5" />
            <circle cx="70" cy="46" r="4" fill="#fff8e6" stroke="#0f5047" stroke-width="2.5" />
            <circle cx="92" cy="32" r="4" fill="#fff8e6" stroke="#0f5047" stroke-width="2.5" />
            <circle cx="114" cy="50" r="4" fill="#fff8e6" stroke="#0f5047" stroke-width="2.5" />
        </g>

        <!-- Bienvenida: la puerta abierta y la llave. -->
        <g v-else-if="shown === 'welcome'">
            <rect x="50" y="24" width="54" height="80" rx="7" fill="#0f5047" />
            <rect x="57" y="31" width="40" height="73" rx="3" fill="#fff8e6" />
            <path d="M57 31l26 7v68l-26-2Z" fill="#1f7a6c" />
            <circle cx="77" cy="72" r="2.8" fill="#f4b640" />
            <rect x="44" y="102" width="66" height="6" rx="3" fill="#d1603d" />
            <circle cx="126" cy="50" r="9" fill="none" stroke="#f4b640" stroke-width="4.5" />
            <path d="M126 59v28M126 78h7M126 85h5" stroke="#f4b640" stroke-width="4.5" stroke-linecap="round" />
            <path d="M30 40l1.6 4.4 4.4 1.6-4.4 1.6-1.6 4.4-1.6-4.4-4.4-1.6 4.4-1.6Z" fill="#1f7a6c" />
        </g>

        <!-- Datos personales: el candado y la cédula. -->
        <g v-else-if="shown === 'privacy'">
            <g transform="rotate(8 106 58)">
                <rect x="82" y="40" width="50" height="34" rx="5" fill="#facf72" />
                <circle cx="96" cy="55" r="6" fill="#d1603d" />
                <path d="M88 68a8 6 0 0 1 16 0" fill="#d1603d" />
                <rect x="108" y="50" width="18" height="4" rx="2" fill="#fff8e6" />
                <rect x="108" y="58" width="12" height="4" rx="2" fill="#fff8e6" />
            </g>
            <path d="M52 62V50a16 16 0 0 1 32 0v12" fill="none" stroke="#0f5047" stroke-width="7" stroke-linecap="round" />
            <rect x="40" y="58" width="56" height="44" rx="9" fill="#1f7a6c" />
            <circle cx="68" cy="76" r="5.5" fill="#fff8e6" />
            <rect x="65.8" y="78" width="4.4" height="11" rx="2.2" fill="#fff8e6" />
            <path d="M130 90l1.6 4.4 4.4 1.6-4.4 1.6-1.6 4.4-1.6-4.4-4.4-1.6 4.4-1.6Z" fill="#1f7a6c" />
        </g>
    </svg>
</template>
