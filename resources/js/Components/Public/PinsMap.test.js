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

    // 2026-10-17, reportado por el usuario: en el computador, al bajar, el mapa se pintaba
    // encima de las pestañas (Validar, Mapa, Lista). Las capas de Leaflet llevan z-index de
    // 400 a 1000; aislado, el mapa las guarda adentro, como LocationMap (it. 55).
    it('keeps its layers inside the map, below the sticky tabs of the screen', async () => {
        const wrapper = await mountMap();

        expect(wrapper.classes()).toContain('isolate');
        wrapper.unmount();
    });

    // 2026-10-17, pedido por el usuario: quien se aleja de las obras no tiene que buscarlas a mano.
    it('Un botón vuelve a mostrar todas las obras del mapa: it fits every pin again, as when the map opened', async () => {
        const wrapper = await mountMap();
        const map = wrapper.vm.map;
        map.setView([4.6, -74.08], 6);
        const fit = vi.spyOn(map, 'fitBounds');

        const button = wrapper.get('button[data-test="fit-pins"]');
        expect(button.text()).toContain('Ver todas las obras');
        await button.trigger('click');

        expect(fit).toHaveBeenCalledTimes(1);
        const [bounds, options] = fit.mock.calls[0];
        expect(pins.every((pin) => bounds.contains([pin.lat, pin.lng]))).toBe(true);
        expect(options).toMatchObject({ padding: [32, 32], maxZoom: 16 });
        wrapper.unmount();
    });

    it('offers no button when there are no pins', async () => {
        const wrapper = mount(PinsMap, { props: { pins: [] }, attachTo: document.body });
        await flushPromises();

        expect(wrapper.find('button[data-test="fit-pins"]').exists()).toBe(false);
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

    // It. 60: el pin es un botón: Enter o Espacio lo tocan (Leaflet le pone el rol, pero no la tecla).
    it('La tarjeta se usa con el teclado: Enter or Space on a pin select it, and other keys do not', async () => {
        const wrapper = await mountMap();
        const press = (key) => {
            const originalEvent = new KeyboardEvent('keydown', { key, cancelable: true });
            wrapper.vm.markers[1].fire('keydown', { originalEvent });
            return originalEvent;
        };

        press('Enter');
        const space = press(' ');
        press('a');

        expect(wrapper.emitted('select')).toEqual([[7], [7]]);
        // El espacio no desplaza la página.
        expect(space.defaultPrevented).toBe(true);
        wrapper.unmount();
    });

    // It. 60: al cerrar la tarjeta de una obra, el foco vuelve a su pin.
    it('La tarjeta se usa con el teclado: focusPin gives the focus back to that pin', async () => {
        const wrapper = await mountMap();

        wrapper.vm.focusPin(7);

        expect(document.activeElement).toBe(wrapper.vm.markers[1].getElement());
        wrapper.unmount();
    });
});
