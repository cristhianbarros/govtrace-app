// Iteración 26 — US-027 (UI): los pines del mapa público sobre las teselas
// de OpenStreetMap (D8, R-INT-02), cada uno con su color y tocable.
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PinsMap from './PinsMap.vue';

const pins = [
    { id: 3, lat: 11.241, lng: -74.199, color_pin: 'green' },
    { id: 7, lat: 11.235, lng: -74.21, color_pin: 'yellow' },
    { id: 9, lat: 11.25, lng: -74.19, color_pin: 'red' },
];

async function mountMap() {
    const wrapper = mount(PinsMap, { props: { pins }, attachTo: document.body });
    await flushPromises();
    await vi.waitFor(() => expect(wrapper.vm.markers).toHaveLength(3));
    return wrapper;
}

// It. 53 — el punto del mapa público es aproximado (R-PRIV-02): se ve una zona, no un punto exacto.

describe('PinsMap', () => {
    it('El mapa dice que cada punto es aproximado: a circle of about 100 m around each pin, that does not take the touch', async () => {
        const wrapper = await mountMap();

        expect(wrapper.vm.circles).toHaveLength(3);
        expect(wrapper.vm.circles.map((circle) => circle.getRadius())).toEqual([100, 100, 100]);
        expect(wrapper.vm.circles[0].getLatLng()).toMatchObject({ lat: 11.241, lng: -74.199 });
        // El que se toca es el pin, no el círculo.
        expect(wrapper.vm.circles.every((circle) => circle.options.interactive === false)).toBe(true);
        wrapper.unmount();
    });

    it('shows the OpenStreetMap tiles, with their attribution', async () => {
        const wrapper = await mountMap();

        expect(wrapper.find('.leaflet-container').exists()).toBe(true);
        expect(wrapper.text()).toContain('OpenStreetMap');
        expect(wrapper.vm.tiles._url).toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png');
        wrapper.unmount();
    });

    it('puts each pin in its place, with its color and a name a screen reader can say', async () => {
        const wrapper = await mountMap();

        expect(wrapper.vm.markers.map((marker) => marker.getLatLng())).toEqual([
            expect.objectContaining({ lat: 11.241, lng: -74.199 }),
            expect.objectContaining({ lat: 11.235, lng: -74.21 }),
            expect.objectContaining({ lat: 11.25, lng: -74.19 }),
        ]);
        expect(wrapper.vm.markers.map((marker) => marker.options.title)).toEqual(['Obra en estado normal', 'Obra con alerta', 'Obra en riesgo']);
        expect(wrapper.findAll('.leaflet-marker-icon span').map((dot) => dot.attributes('data-color'))).toEqual(['green', 'yellow', 'red']);
        wrapper.unmount();
    });

    it('tells which worksite was touched', async () => {
        const wrapper = await mountMap();

        wrapper.vm.markers[2].fire('click');

        expect(wrapper.emitted('select')).toEqual([[9]]);
        wrapper.unmount();
    });
});

describe('PinsMap, ícono además del color (it. 40b)', () => {
    it('draws each state with its own sign, not only its color, and a pin big enough to touch', async () => {
        const wrapper = await mountMap();

        expect(wrapper.findAll('.leaflet-marker-icon span').map((dot) => dot.text())).toEqual(['✓', '!', '✕']);
        expect(wrapper.vm.markers[0].options.icon.options.iconSize).toEqual([36, 36]);
        wrapper.unmount();
    });
});
