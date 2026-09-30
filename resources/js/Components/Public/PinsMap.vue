<script setup>
// US-027: los pines del mapa público sobre las teselas de OpenStreetMap (D8,
// R-INT-02), uno por obra, con el color de su estado. Leaflet se descarga
// solo cuando el mapa aparece.
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';

const props = defineProps({
    pins: { type: Array, required: true }, // [{ id, lat, lng, color_pin }]
});

const emit = defineEmits(['select']);

// El color, y lo que dice un lector de pantalla de cada pin.
// It. 40b: además del color, un signo (WCAG 1.4.1: el color solo no basta),
// y un pin de 36 px, fácil de tocar.
const LOOK = {
    green: { css: 'bg-green-700 text-white', sign: '✓', title: 'Obra en estado normal' },
    yellow: { css: 'bg-yellow-400 text-slate-900', sign: '!', title: 'Obra con alerta' },
    red: { css: 'bg-red-700 text-white', sign: '✕', title: 'Obra en riesgo' },
};

const container = ref(null);
const map = shallowRef(null);
const tiles = shallowRef(null);
const markers = shallowRef([]);

onMounted(async () => {
    const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);

    map.value = L.map(container.value);
    tiles.value = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map.value);

    markers.value = props.pins.map((pin) => {
        const look = LOOK[pin.color_pin] ?? LOOK.green;
        // Un punto dibujado con CSS: sin las imágenes del ícono por defecto de Leaflet.
        const icon = L.divIcon({
            className: '',
            html: `<span data-color="${pin.color_pin}" class="grid size-9 place-items-center rounded-full border-4 border-white text-base font-bold shadow ${look.css}">${look.sign}</span>`,
            iconSize: [36, 36],
        });
        const marker = L.marker([pin.lat, pin.lng], { icon, keyboard: true, title: look.title, alt: look.title }).addTo(map.value);
        marker.on('click', () => emit('select', pin.id));
        return marker;
    });

    map.value.fitBounds(L.latLngBounds(props.pins.map((pin) => [pin.lat, pin.lng])), { padding: [32, 32], maxZoom: 16 });
});

onBeforeUnmount(() => map.value?.remove());

defineExpose({ tiles, markers });
</script>

<template>
    <div ref="container" class="h-[60dvh] w-full rounded-lg border border-slate-200 md:h-[70vh]"></div>
</template>
