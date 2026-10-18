// It. 62c — US-009: el "atrás" del teléfono cierra la cámara sin salir del
// formulario. La página (Inertia) no se entera, y al cerrarse con sus botones,
// la cámara devuelve su paso en la historia sin que la página se entere tampoco.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createBackGuard } from './backGuard.js';

let win;
let page;
let holdBack;

function pressBack() {
    win.dispatchEvent(new PopStateEvent('popstate', { state: { page: 'obra' } }));
}

beforeEach(() => {
    win = new EventTarget();
    win.location = { href: 'https://veeduria.govtrace.co/worksite/abc' };
    win.history = { state: { page: 'obra' }, pushState: vi.fn(), back: vi.fn(pressBack) };
    holdBack = createBackGuard(win);
    // Inertia escucha después: app.js importa el guardián antes.
    page = vi.fn();
    win.addEventListener('popstate', page);
});

describe('El "atrás" cierra lo abierto encima de la página (it. 62c)', () => {
    it('takes a step in the history with the same state of the page, and the back button closes it without the page hearing', () => {
        const onBack = vi.fn();
        holdBack(onBack);

        expect(win.history.pushState).toHaveBeenCalledWith({ page: 'obra' }, '', 'https://veeduria.govtrace.co/worksite/abc');
        pressBack();
        expect(onBack).toHaveBeenCalledTimes(1);
        expect(page).not.toHaveBeenCalled();

        // El siguiente "atrás" ya es de la página.
        pressBack();
        expect(onBack).toHaveBeenCalledTimes(1);
        expect(page).toHaveBeenCalledTimes(1);
    });

    it('closed with its own buttons, it gives its step back, and the page does not hear that "atrás" either', () => {
        const release = holdBack(vi.fn());

        release();
        release();

        expect(win.history.back).toHaveBeenCalledTimes(1);
        expect(page).not.toHaveBeenCalled();
        pressBack();
        expect(page).toHaveBeenCalledTimes(1);
    });

    it('once the back button closed it, releasing it does not go back again', () => {
        const release = holdBack(vi.fn());
        pressBack();

        release();

        expect(win.history.back).not.toHaveBeenCalled();
    });

    it('closes the last thing opened first', () => {
        const below = vi.fn();
        const above = vi.fn();
        holdBack(below);
        holdBack(above);

        pressBack();

        expect(above).toHaveBeenCalledTimes(1);
        expect(below).not.toHaveBeenCalled();
    });
});
