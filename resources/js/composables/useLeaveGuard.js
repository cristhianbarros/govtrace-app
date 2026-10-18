// It. 62e (US-067): con un reporte empezado, salir de la pantalla pregunta
// antes. Una visita de Inertia (la ruta, una pestaña) a otra pantalla, con la
// pregunta de la app; recargar, cerrar la app o un enlace a otro sitio (GovTrace),
// con el aviso del navegador. "Salir" de la cuenta ya preguntó en su menú
// (AccountMenu): su visita a /login no vuelve a preguntar.
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

export const LEAVE_REPORT = '¿Salir sin enviar el reporte? Se perderá lo que lleva.';

const LOGGED_OUT = '/login';

export function useLeaveGuard(started, message = LEAVE_REPORT) {
    let stop = null;

    const onBeforeUnload = (event) => {
        if (started.value) {
            event.preventDefault();
            event.returnValue = ''; // Chrome y Safari aún lo piden para mostrar el aviso
        }
    };

    onMounted(() => {
        stop = router.on('before', (event) => {
            const visit = event.detail.visit;
            const to = new URL(visit.url, window.location.href).pathname;
            const elsewhere = visit.method !== 'get' || to !== window.location.pathname;
            if (started.value && elsewhere && to !== LOGGED_OUT && !window.confirm(message)) {
                return false;
            }
            return undefined;
        });
        window.addEventListener('beforeunload', onBeforeUnload);
    });

    onBeforeUnmount(() => {
        if (typeof stop === 'function') {
            stop();
        }
        window.removeEventListener('beforeunload', onBeforeUnload);
    });
}
