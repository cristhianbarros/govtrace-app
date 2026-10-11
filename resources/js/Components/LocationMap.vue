<script setup>
// D8: un mapa de Leaflet con las teselas de OpenStreetMap (R-INT-02) y un
// pin que se arrastra hasta el sitio real de la obra (US-035). Leaflet se
// descarga solo cuando el mapa aparece: el resto de la app no lo carga.
// It. 45f: de solo lectura, en la Bandeja, el punto donde se tomó un reporte.
// It. 55: con `official`, además, dónde está hoy la ubicación oficial de la obra (si se
// corrigió): el punto del reporte pasa a gris y los dos entran en la vista.
// Y aislado (isolate): las capas de Leaflet, con z-index de 400 a 1000, se
// pintaban encima de las barras fijas de la app al desplazarse.
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

const location = defineModel({ type: Object, required: true }); // { latitude, longitude }
const props = defineProps({
    readonly: { type: Boolean, default: false },
    official: { type: Object, default: null }, // { latitude, longitude }
});

const container = ref(null);
const map = shallowRef(null);
const tiles = shallowRef(null);
const marker = shallowRef(null);
const officialMarker = shallowRef(null);

const round7 = (value) => Number(value.toFixed(7));

onMounted(async () => {
    const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    const point = [location.value.latitude, location.value.longitude];

    map.value = L.map(container.value).setView(point, 17);
    tiles.value = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map.value);

    // Un pin dibujado con CSS: sin las imágenes del ícono por defecto de Leaflet.
    const pinOf = (color) => L.divIcon({ className: '', html: `<span class="block size-6 rounded-full border-4 border-white ${color} shadow"></span>`, iconSize: [24, 24] });
    const title = props.readonly ? 'Donde se tomó el reporte' : 'Ubicación oficial de la obra';
    // Con la oficial al lado, el del reporte es el gris y la oficial es la roja.
    marker.value = L.marker(point, { draggable: !props.readonly, icon: pinOf(props.official ? 'bg-slate-500' : 'bg-red-600'), keyboard: !props.readonly, title }).addTo(map.value);
    if (props.official) {
        const official = [props.official.latitude, props.official.longitude];
        officialMarker.value = L.marker(official, { draggable: false, icon: pinOf('bg-red-600'), keyboard: false, title: 'Ubicación oficial hoy' }).addTo(map.value);
        map.value.fitBounds(L.latLngBounds([point, official]), { padding: [32, 32], maxZoom: 17 });
    }
    marker.value.on('dragend', () => {
        const { lat, lng } = marker.value.getLatLng();
        location.value = { latitude: round7(lat), longitude: round7(lng) };
    });
});

watch(location, (value) => {
    if (marker.value && value && Number.isFinite(value.latitude) && Number.isFinite(value.longitude)) {
        marker.value.setLatLng([value.latitude, value.longitude]);
        map.value.panTo([value.latitude, value.longitude]);
    }
});

onBeforeUnmount(() => map.value?.remove());

defineExpose({ tiles, marker, officialMarker, map });
</script>

<template>
    <div ref="container" class="isolate w-full rounded-lg border border-slate-200" :class="readonly ? 'h-48' : 'h-72'"></div>
</template>
