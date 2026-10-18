// It. 62d (R-UX-08): el aviso que deja una acción (enviar, tomar la foto) se trae
// a la vista si quedó fuera de la pantalla: quien tocó "Enviar" abajo no tiene
// que subir a buscar la respuesta. Con suavidad, salvo para quien le pidió al
// teléfono menos movimiento, y sin quedar debajo de la barra de arriba
// (scroll-mt-24: la barra mide unos 72 px).

/** Fuera de la pantalla, o cortado; uno más alto que la pantalla basta con que su comienzo se vea arriba. */
export function outOfView({ top, bottom }, screenHeight) {
    if (top >= 0 && bottom <= screenHeight) {
        return false;
    }
    return !(bottom - top > screenHeight && top >= 0 && top < screenHeight / 2);
}

export function reveal(element, screen = window) {
    if (typeof element?.scrollIntoView !== 'function') {
        return;
    }
    if (!outOfView(element.getBoundingClientRect(), screen.innerHeight)) {
        return;
    }
    const lessMotion = screen.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    element.scrollIntoView({ behavior: lessMotion ? 'auto' : 'smooth', block: 'start' });
}

/** v-reveal="mensaje": al aparecer el elemento, y cada vez que cambia su mensaje (no en cada redibujo). */
export const vReveal = {
    mounted(element) {
        element.classList.add('scroll-mt-24');
        reveal(element);
    },
    updated(element, { value, oldValue }) {
        if (value !== oldValue) {
            reveal(element);
        }
    },
};
