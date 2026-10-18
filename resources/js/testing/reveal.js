// It. 62d: en las pruebas, cada elemento queda lejos, abajo de la pantalla; lo
// que se trae a la vista se ve en las llamadas a scrollIntoView (jsdom no lo tiene).
import { vi } from 'vitest';

export function everythingFarBelow() {
    const scrollIntoView = vi.fn();
    const rect = vi.spyOn(Element.prototype, 'getBoundingClientRect').mockReturnValue({ top: 2000, bottom: 2060, left: 0, right: 360, width: 360, height: 60, x: 0, y: 2000 });
    Element.prototype.scrollIntoView = scrollIntoView;

    return {
        /** The elements brought into view, in order. */
        revealed: () => scrollIntoView.mock.contexts,
        restore() {
            rect.mockRestore();
            delete Element.prototype.scrollIntoView;
        },
    };
}
