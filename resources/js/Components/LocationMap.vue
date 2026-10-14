<script setup>
// D8: un mapa de Leaflet con las teselas de OpenStreetMap (R-INT-02) y un
// pin que se arrastra hasta el sitio real de la obra (US-035). Leaflet se
// descarga solo cuando el mapa aparece: el resto de la app no lo carga.
// It. 45f: de solo lectura, en la Bandeja, el punto donde se tomó un reporte.
// It. 55: con `official`, además, dónde está hoy la ubicación oficial de la obra (si se
// corrigió): el punto del reporte pasa a gris y los dos entran en la vista.
// Y aislado (isolate): las capas de Leaflet, con z-index de 400 a 1000, se
// pintaban encima de las barras fijas de la app al desplazarse.
// It. 58: con `others`, otros puntos en gris (las demás ubicaciones que propusieron
// los ciudadanos para la misma obra); y con `tapToMove`, tocar el mapa lleva el pin
// a ese punto: más fácil que arrastrarlo, sobre todo en el celular.
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

const location = defineModel({ type: Object, required: true }); // { latitude, longitude }
const props = defineProps({
    readonly: { type: Boolean, default: false },
    official: { type: Object, default: null }, // { latitude, longitude }
    others: { type: Array, default: () => [] }, // [{ latitude, longitude }]
    label: { type: String, default: null }, // lo que dice el pin a un lector de pantalla
    tapToMove: { type: Boolean, default: false },
});

const container = ref(null);
const map = shallowRef(null);
const tiles = shallowRef(null);
const marker = shallowRef(null);
const officialMarker = shallowRef(null);
const otherMarkers = shallowRef([]);

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
    const title = props.label ?? (props.readonly ? 'Donde se tomó el reporte' : 'Ubicación oficial de la obra');
    // Con la oficial al lado, el del reporte es el gris y la oficial es la roja.
    marker.value = L.marker(point, { draggable: !props.readonly, icon: pinOf(props.official ? 'bg-slate-500' : 'bg-red-600'), keyboard: !props.readonly, title }).addTo(map.value);
    if (props.official) {
        const official = [props.official.latitude, props.official.longitude];
        officialMarker.value = L.marker(official, { draggable: false, icon: pinOf('bg-red-600'), keyboard: false, title: 'Ubicación oficial hoy' }).addTo(map.value);
        map.value.fitBounds(L.latLngBounds([point, official]), { padding: [32, 32], maxZoom: 17 });
    }
    if (props.others.length > 0) {
        const others = props.others.map((other) => [other.latitude, other.longitude]);
        otherMarkers.value = others.map((other) => L.marker(other, { draggable: false, icon: pinOf('bg-slate-500'), keyboard: false, title: 'Otra ubicación propuesta' }).addTo(map.value));
        map.value.fitBounds(L.latLngBounds([point, ...others]), { padding: [32, 32], maxZoom: 17 });
    }
    marker.value.on('dragend', () => {
        const { lat, lng } = marker.value.getLatLng();
        location.value = { latitude: round7(lat), longitude: round7(lng) };
    });
    if (props.tapToMove && !props.readonly) {
        map.value.on('click', ({ latlng }) => {
            marker.value.setLatLng(latlng);
            location.value = { latitude: round7(latlng.lat), longitude: round7(latlng.lng) };
        });
    }
});

watch(location, (value) => {
    if (marker.value && value && Number.isFinite(value.latitude) && Number.isFinite(value.longitude)) {
        marker.value.setLatLng([value.latitude, value.longitude]);
        map.value.panTo([value.latitude, value.longitude]);
    }
});

onBeforeUnmount(() => map.value?.remove());

defineExpose({ tiles, marker, officialMarker, otherMarkers, map });
</script>

<template>
    <div ref="container" class="isolate w-full rounded-lg border border-slate-200" :class="readonly ? 'h-48' : 'h-72'"></div>
</template>
