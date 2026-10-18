// It. 62c: el "atrás" del teléfono cierra lo que se abrió encima de la página
// (la cámara), sin que Inertia rehaga la página y se pierda lo escrito. Se
// importa en app.js antes que Inertia: en window, los oyentes del "atrás" se
// llaman en el orden en que se registraron (con captura o sin ella), y este va
// primero.

export function createBackGuard(win = window) {
    const held = []; // lo abierto, el último encima
    let returning = 0; // los "atrás" que pidió la página para devolver su paso

    win.addEventListener('popstate', (event) => {
        if (returning > 0) {
            returning--;
            event.stopImmediatePropagation();
            return;
        }
        const entry = held.pop();
        if (entry) {
            event.stopImmediatePropagation();
            entry.onBack();
        }
    });

    /** Ocupa un paso de la historia, con el mismo estado de la página: el "atrás" llama a onBack. Devuelve con qué devolver el paso. */
    return function holdBack(onBack) {
        const entry = { onBack };
        win.history.pushState(win.history.state, '', win.location.href);
        held.push(entry);

        return function release() {
            const index = held.indexOf(entry);
            if (index === -1) {
                return; // ya lo cerró el "atrás"
            }
            held.splice(index, 1);
            returning++;
            win.history.back();
        };
    };
}

export const holdBack = typeof window === 'undefined' ? () => () => {} : createBackGuard();
