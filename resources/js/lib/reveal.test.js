// It. 62d — R-UX-08: el aviso que deja una acción (enviar, tomar la foto) se
// trae a la vista si quedó fuera de la pantalla: quien tocó "Enviar" abajo no
// tiene que subir a buscar la respuesta.
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref, withDirectives } from 'vue';
import { outOfView, reveal, vReveal } from './reveal.js';

const SCREEN = 800;
const phone = (reduceMotion = false) => ({ innerHeight: SCREEN, matchMedia: () => ({ matches: reduceMotion }) });
const message = (top, bottom) => ({ scrollIntoView: vi.fn(), getBoundingClientRect: () => ({ top, bottom }) });

describe('El aviso, a la vista (it. 62d)', () => {
    it('knows when a message is out of view: above, below or cut; not a tall one whose beginning shows', () => {
        expect(outOfView({ top: 100, bottom: 160 }, SCREEN)).toBe(false);
        expect(outOfView({ top: -80, bottom: -20 }, SCREEN)).toBe(true);
        expect(outOfView({ top: 900, bottom: 960 }, SCREEN)).toBe(true);
        expect(outOfView({ top: 760, bottom: 820 }, SCREEN)).toBe(true);
        expect(outOfView({ top: 120, bottom: 1500 }, SCREEN)).toBe(false);
        expect(outOfView({ top: 600, bottom: 2000 }, SCREEN)).toBe(true);
    });

    it('brings it into view smoothly, its beginning on top, and without motion for whoever asked the phone for less', () => {
        const below = message(900, 960);

        reveal(below, phone());
        expect(below.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        reveal(below, phone(true));
        expect(below.scrollIntoView).toHaveBeenLastCalledWith({ behavior: 'auto', block: 'start' });
    });

    it('leaves alone a message already in view, and does nothing where the browser cannot scroll to it', () => {
        const inView = message(100, 160);

        reveal(inView, phone());
        reveal({ getBoundingClientRect: () => ({ top: 900, bottom: 960 }) }, phone());

        expect(inView.scrollIntoView).not.toHaveBeenCalled();
    });

    it('v-reveal: when the message appears and each time it changes, not on every redraw; and it stops below the bar on top', async () => {
        const text = ref(null);
        const other = ref(0);
        const Screen = defineComponent({
            setup: () => () => h('div', [h('span', other.value), text.value ? withDirectives(h('p', { 'data-test': 'notice' }, text.value), [[vReveal, text.value]]) : null]),
        });
        const scrollIntoView = vi.fn();
        const rect = vi.spyOn(Element.prototype, 'getBoundingClientRect').mockReturnValue({ top: 2000, bottom: 2060 });
        Element.prototype.scrollIntoView = scrollIntoView;
        const wrapper = mount(Screen);

        text.value = 'Su informe llegó a la veeduría.';
        await wrapper.vm.$nextTick();
        other.value = 1;
        await wrapper.vm.$nextTick();
        text.value = 'Escriba el código de 6 dígitos que le llegó al correo.';
        await wrapper.vm.$nextTick();

        expect(scrollIntoView).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-test="notice"]').classes()).toContain('scroll-mt-24');
        rect.mockRestore();
        delete Element.prototype.scrollIntoView;
    });
});
