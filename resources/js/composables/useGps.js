// It. 47b — "Encontrar la obra en campo" (US-008, US-019): el GPS de "Nuevo
// reporte", una sola vez por pantalla. Se enciende al abrirla y la sigue
// mientras está abierta: el municipio, las obras cercanas y el reporte usan la
// misma lectura. La app espera una de 50 m o menos mostrando la precisión; a
// los 60 s sin lograrla, dice qué hacer. El reporte usa la última precisa si
// tiene 30 s o menos, con su hora como hora de captura.
import { onBeforeUnmount, ref } from 'vue';
import { capturePosition, followPosition, isPreciseEnough } from '@/lib/geolocation.js';

export const SLOW_GPS_MESSAGE =
    'No se consiguió una buena señal del GPS en 1 minuto. Salga a un lugar abierto y revise que su celular tenga activada la ubicación precisa. Luego toque «Intentar de nuevo».';

const SLOW_AFTER_MS = 60_000;
const FRESH_FOR_MS = 30_000;

export function useGps() {
    const latest = ref(null); // la última lectura, de cualquier precisión
    const precise = ref(null); // la última de 50 m o menos
    const status = ref('idle'); // idle | searching | ready | slow | failed
    const message = ref(null);

    let stop = () => {};
    let timer;

    function take(reading) {
        latest.value = reading;
        if (isPreciseEnough(reading.accuracy)) {
            precise.value = reading;
            status.value = 'ready';
            message.value = null;
            clearTimeout(timer);
        }
    }

    function start() {
        stop();
        clearTimeout(timer);
        status.value = precise.value ? 'ready' : 'searching';
        message.value = null;
        stop = followPosition({
            onReading: take,
            onError: (error) => {
                if (status.value !== 'ready') {
                    status.value = 'failed';
                    message.value = error.message;
                }
            },
        });
        if (status.value === 'searching') {
            timer = setTimeout(() => {
                if (status.value === 'searching') {
                    status.value = 'slow';
                    message.value = SLOW_GPS_MESSAGE;
                }
            }, SLOW_AFTER_MS);
        }
    }

    /** Una lectura más, a pedido: un celular quieto puede no enviar ninguna nueva. */
    function refresh() {
        capturePosition().then(take, () => {});
    }

    /** La última lectura de 50 m o menos, si tiene 30 s o menos; si no, null. */
    function fresh() {
        const reading = precise.value;
        return reading && Date.now() - Date.parse(reading.capturedAt) <= FRESH_FOR_MS ? reading : null;
    }

    onBeforeUnmount(() => {
        stop();
        clearTimeout(timer);
    });

    return { latest, precise, status, message, start, refresh, fresh };
}
