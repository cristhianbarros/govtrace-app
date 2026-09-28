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
});
