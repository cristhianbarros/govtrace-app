// It. 43b (V6 de docs/mapa-funcional.md): instalar la app en el celular. El
// navegador avisa que se puede con "beforeinstallprompt"; se guarda ese aviso
// y el menú de cuenta ofrece "Instalar la app en este celular". En los que no
// avisan (iPhone), se instala desde el menú del navegador: "Agregar a inicio".
import { reactive } from 'vue';

export const installation = reactive({ available: false });
let deferred = null;

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault?.();
        deferred = event;
        installation.available = true;
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        installation.available = false;
    });
}

export async function install() {
    if (!deferred) {
        return;
    }
    const event = deferred;
    deferred = null;
    installation.available = false;
    await event.prompt();
    await event.userChoice?.catch(() => null);
}
