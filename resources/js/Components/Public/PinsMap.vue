<script setup>
// US-027: los pines del mapa público sobre las teselas de OpenStreetMap (D8,
// R-INT-02), uno por obra, con el color de su estado. Leaflet se descarga
// solo cuando el mapa aparece.
// Aislado (isolate): las capas de Leaflet llevan z-index de 400 a 1000, y en
// el computador, al bajar, se pintaban encima de las pestañas fijas (Validar,
// Mapa, Lista). Como LocationMap (it. 55).
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';

const props = defineProps({
    pins: { type: Array, required: true }, // [{ id, lat, lng, color_pin }]
});

const emit = defineEmits(['select']);

// It. 53 (R-PRIV-02): la ubicación pública es aproximada, a unos 100 m (se redondea a una grilla,
// GeoPoint::approximate). Alrededor de cada pin va un círculo de ese radio: se ve una zona, no un
// punto exacto. No recibe el toque: el que se toca es el pin.
const APPROXIMATE_METERS = 100;

// El color, y lo que dice un lector de pantalla de cada pin.
// It. 40b: además del color, un signo (WCAG 1.4.1: el color solo no basta),
// y un pin de 36 px, fácil de tocar.
const LOOK = {
    green: { css: 'bg-green-700 text-white', sign: '✓', title: 'Obra en estado normal', zone: '#15803d' },
    yellow: { css: 'bg-yellow-400 text-slate-900', sign: '!', title: 'Obra con alerta', zone: '#ca8a04' },
    red: { css: 'bg-red-700 text-white', sign: '✕', title: 'Obra en riesgo', zone: '#b91c1c' },
};

const container = ref(null);
const map = shallowRef(null);
const tiles = shallowRef(null);
const markers = shallowRef([]);
const circles = shallowRef([]);
const markerOf = {}; // it. 60: cada pin por el id de su obra, para devolverle el foco

onMounted(async () => {
    const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);

    map.value = L.map(container.value);
    tiles.value = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map.value);

    circles.value = props.pins.map((pin) => L.circle([pin.lat, pin.lng], {
        radius: APPROXIMATE_METERS,
        color: (LOOK[pin.color_pin] ?? LOOK.green).zone,
        weight: 1,
        fillOpacity: 0.12,
        interactive: false,
    }).addTo(map.value));

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
        // It. 60: Leaflet le pone al pin el rol de botón, pero no la tecla: Enter o Espacio lo tocan, como a un botón.
        marker.on('keydown', ({ originalEvent }) => {
            if (originalEvent.key === 'Enter' || originalEvent.key === ' ') {
                originalEvent.preventDefault();
                emit('select', pin.id);
            }
        });
        markerOf[pin.id] = marker;
        return marker;
    });

    map.value.fitBounds(L.latLngBounds(props.pins.map((pin) => [pin.lat, pin.lng])), { padding: [32, 32], maxZoom: 16 });
});

onBeforeUnmount(() => map.value?.remove());

/** It. 60: al cerrar la tarjeta de una obra, el foco vuelve a su pin. */
function focusPin(id) {
    markerOf[id]?.getElement()?.focus();
}

defineExpose({ tiles, markers, circles, focusPin });
</script>

<template>
    <div ref="container" class="isolate h-[60dvh] w-full rounded-lg border border-slate-200 md:h-[70vh]"></div>
</template>
