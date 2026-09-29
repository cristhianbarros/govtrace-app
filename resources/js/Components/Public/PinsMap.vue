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
const LOOK = {
    green: { css: 'bg-green-600', title: 'Obra en estado normal' },
    yellow: { css: 'bg-yellow-400', title: 'Obra con alerta' },
    red: { css: 'bg-red-600', title: 'Obra en riesgo' },
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
            html: `<span data-color="${pin.color_pin}" class="block size-7 rounded-full border-4 border-white shadow ${look.css}"></span>`,
            iconSize: [28, 28],
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
