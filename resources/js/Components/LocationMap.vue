<script setup>
// D8: un mapa de Leaflet con las teselas de OpenStreetMap (R-INT-02) y un
// pin que se arrastra hasta el sitio real de la obra (US-035). Leaflet se
// descarga solo cuando el mapa aparece: el resto de la app no lo carga.
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

const location = defineModel({ type: Object, required: true }); // { latitude, longitude }

const container = ref(null);
const map = shallowRef(null);
const tiles = shallowRef(null);
const marker = shallowRef(null);

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
    const pin = L.divIcon({ className: '', html: '<span class="block size-6 rounded-full border-4 border-white bg-red-600 shadow"></span>', iconSize: [24, 24] });
    marker.value = L.marker(point, { draggable: true, icon: pin, keyboard: true, title: 'Ubicación oficial de la obra' }).addTo(map.value);
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

defineExpose({ tiles, marker });
</script>

<template>
    <div ref="container" class="h-72 w-full rounded-lg border border-slate-200"></div>
</template>
