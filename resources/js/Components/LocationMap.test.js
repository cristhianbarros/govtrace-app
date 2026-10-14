// Iteración 18 — D8: el mapa de Leaflet con las teselas de OpenStreetMap
// (R-INT-02) y un pin que se arrastra. Leaflet se carga solo cuando el mapa
// aparece, para no pesar en el resto de la app.
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LocationMap from './LocationMap.vue';

async function mountMap(modelValue) {
    const wrapper = mount(LocationMap, { props: { modelValue }, attachTo: document.body });
    await flushPromises();
    await vi.waitFor(() => expect(wrapper.vm.marker).toBeTruthy());
    return wrapper;
}

describe('LocationMap', () => {
    it('shows the OpenStreetMap tiles, with their attribution, and the pin on the location', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        expect(wrapper.find('.leaflet-container').exists()).toBe(true);
        expect(wrapper.text()).toContain('OpenStreetMap');
        expect(wrapper.vm.tiles._url).toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png');
        expect(wrapper.vm.marker.getLatLng()).toMatchObject({ lat: 11.2, lng: -74.23 });
        expect(wrapper.vm.marker.options.draggable).toBe(true);
        wrapper.unmount();
    });

    it('emits the new location when the pin is dragged, with 7 decimals', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        wrapper.vm.marker.setLatLng([11.240812345, -74.199012345]);
        wrapper.vm.marker.fire('dragend');

        expect(wrapper.emitted('update:modelValue')).toEqual([[{ latitude: 11.2408123, longitude: -74.1990123 }]]);
        wrapper.unmount();
    });

    it('moves the pin when the coordinates are typed', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        await wrapper.setProps({ modelValue: { latitude: 11.2408, longitude: -74.199 } });

        expect(wrapper.vm.marker.getLatLng()).toMatchObject({ lat: 11.2408, lng: -74.199 });
        wrapper.unmount();
    });

    // It. 45f: en la Bandeja, el punto que fijó la ubicación de la obra se mira, no se arrastra.
    it('shows a pin that cannot be dragged when it is read-only', async () => {
        const wrapper = mount(LocationMap, { props: { modelValue: { latitude: 11.2, longitude: -74.23 }, readonly: true }, attachTo: document.body });
        await flushPromises();
        await vi.waitFor(() => expect(wrapper.vm.marker).toBeTruthy());

        expect(wrapper.vm.marker.options.draggable).toBe(false);
        expect(wrapper.vm.marker.options.title).toBe('Donde se tomó el reporte');
        wrapper.unmount();
    });

    // It. 55: la ubicación oficial de hoy, junto al punto donde se tomó el reporte.
    it('shows the official location of today next to where the report was taken, when it was corrected', async () => {
        const wrapper = mount(LocationMap, {
            props: { modelValue: { latitude: 11.2, longitude: -74.23 }, readonly: true, official: { latitude: 11.2411, longitude: -74.1995 } },
            attachTo: document.body,
        });
        await flushPromises();
        await vi.waitFor(() => expect(wrapper.vm.officialMarker).toBeTruthy());

        expect(wrapper.vm.marker.options.title).toBe('Donde se tomó el reporte');
        expect(wrapper.vm.officialMarker.options.title).toBe('Ubicación oficial hoy');
        expect(wrapper.vm.officialMarker.options.draggable).toBe(false);
        expect(wrapper.vm.officialMarker.getLatLng()).toMatchObject({ lat: 11.2411, lng: -74.1995 });
        wrapper.unmount();
    });

    it('has no second pin when the location was not corrected', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        expect(wrapper.vm.officialMarker).toBeFalsy();
        wrapper.unmount();
    });

    // It. 45f: las capas de Leaflet (z-index 400 a 1000) se pintaban encima de las barras fijas de la app al desplazarse.
    it('keeps its layers inside the map, below the fixed bars of the screen', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        expect(wrapper.classes()).toContain('isolate');
        wrapper.unmount();
    });
});

// It. 58: las ubicaciones que propusieron los ciudadanos.
describe('LocationMap, con lo que proponen los ciudadanos (it. 58)', () => {
    it('draws the other proposals in grey, with the main point in red, all in view', async () => {
        const wrapper = mount(LocationMap, {
            props: { modelValue: { latitude: 11.1427, longitude: -74.1173 }, others: [{ latitude: 11.15, longitude: -74.12 }], label: 'Donde el ciudadano dice que queda la obra', readonly: true },
            attachTo: document.body,
        });
        await flushPromises();
        await vi.waitFor(() => expect(wrapper.vm.marker).toBeTruthy());

        expect(wrapper.vm.marker.options.title).toBe('Donde el ciudadano dice que queda la obra');
        expect(wrapper.vm.marker.options.icon.options.html).toContain('bg-red-600');
        expect(wrapper.vm.otherMarkers).toHaveLength(1);
        expect(wrapper.vm.otherMarkers[0].getLatLng()).toMatchObject({ lat: 11.15, lng: -74.12 });
        expect(wrapper.vm.otherMarkers[0].options.icon.options.html).toContain('bg-slate-500');
        wrapper.unmount();
    });

    it('moves the pin where the map is tapped, when asked to', async () => {
        const wrapper = mount(LocationMap, { props: { modelValue: { latitude: 11.2, longitude: -74.23 }, tapToMove: true }, attachTo: document.body });
        await flushPromises();
        await vi.waitFor(() => expect(wrapper.vm.marker).toBeTruthy());

        wrapper.vm.map.fire('click', { latlng: { lat: 11.142712345, lng: -74.117312345 } });

        expect(wrapper.emitted('update:modelValue')).toEqual([[{ latitude: 11.1427123, longitude: -74.1173123 }]]);
        expect(wrapper.vm.marker.getLatLng()).toMatchObject({ lat: 11.142712345, lng: -74.117312345 });
        wrapper.unmount();
    });

    it('does not move the pin with a tap unless asked to: the Administrador drags it', async () => {
        const wrapper = await mountMap({ latitude: 11.2, longitude: -74.23 });

        wrapper.vm.map.fire('click', { latlng: { lat: 11.1427, lng: -74.1173 } });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        wrapper.unmount();
    });
});
