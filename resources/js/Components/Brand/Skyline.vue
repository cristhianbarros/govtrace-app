<script setup>
// It. 40f: el barrio en silueta — casas, la iglesia, árboles y una obra con
// su grúa — sobre el pie de página del sitio de una veeduría. Se repite a lo
// ancho a su tamaño natural, sin estirarse ni cortarse. Decorativa.
</script>

<template>
    <svg class="block h-12 w-full" data-illustration="skyline" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="govtrace-skyline" width="640" height="48" patternUnits="userSpaceOnUse">
                <g fill="currentColor">
                    <path d="M0 48V34h28V24l12-8 12 8v24Z" />
                    <circle cx="70" cy="30" r="10" />
                    <rect x="68" y="34" width="4" height="14" />
                    <path d="M84 48V20h34v28Z" />
                    <path d="M126 48V30l16-12 16 12v18Z" />
                    <path d="M170 48V22h6v-8l5-6 5 6v8h6v26Z" />
                    <path d="M200 48V28h40v20Z" />
                    <circle cx="256" cy="32" r="8" />
                    <rect x="254" y="36" width="4" height="12" />
                    <path d="M272 48V14h26v34Z" />
                    <path d="M306 48V26h18l10-8 10 8v22Z" />
                    <path d="M350 48V32h28v16Z" />
                    <rect x="400" y="20" width="10" height="7" rx="1.5" />
                    <path d="M428 48V24h30v24Z" />
                    <circle cx="474" cy="30" r="10" />
                    <rect x="472" y="34" width="4" height="14" />
                    <path d="M490 48V30l14-10 14 10v18Z" />
                    <path d="M526 48V18h36v30Z" />
                    <path d="M570 48V28h22l14-10 14 10h20v20Z" />
                </g>
                <path d="M360 48V8h52M360 16l10-8M404 8v12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </pattern>
        </defs>
        <rect width="100%" height="48" fill="url(#govtrace-skyline)" />
    </svg>
</template>
