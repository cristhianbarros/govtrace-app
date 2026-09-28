// US-008: la posición sale del GPS del teléfono, fresca y con alta
// precisión. La precisión mínima es fija, 50 m (R-CFG-02), y el servidor la
// vuelve a validar (App\Domain\Reports\GpsReading) con las mismas palabras.

export const MAX_GPS_ACCURACY_METERS = 50;

export const GPS_DENIED_MESSAGE =
    'GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.';

const GPS_UNAVAILABLE_MESSAGE = 'No se pudo obtener su ubicación. Verifique que el GPS esté encendido y vuelva a intentarlo.';

/** Metros como los escribe el servidor: hasta 2 decimales, sin ceros de más. */
export const formatMeters = (meters) => String(Number(meters.toFixed(2)));

export const isPreciseEnough = (accuracy) => accuracy <= MAX_GPS_ACCURACY_METERS;

export const imprecisionMessage = (accuracy) =>
    `La precisión del GPS es de ${formatMeters(accuracy)} m y se requieren ${MAX_GPS_ACCURACY_METERS} m o menos. Espere a tener mejor señal y vuelva a intentarlo.`;

/** @returns {Promise<{latitude: number, longitude: number, accuracy: number, capturedAt: string}>} */
export function capturePosition(geolocation = globalThis.navigator?.geolocation) {
    return new Promise((resolve, reject) => {
        if (!geolocation) {
            reject(new Error(GPS_DENIED_MESSAGE));
            return;
        }

        geolocation.getCurrentPosition(
            ({ coords, timestamp }) =>
                resolve({
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy: coords.accuracy,
                    capturedAt: new Date(timestamp).toISOString(),
                }),
            (error) => reject(new Error(error.code === error.PERMISSION_DENIED ? GPS_DENIED_MESSAGE : GPS_UNAVAILABLE_MESSAGE)),
            // Nunca una lectura guardada: la evidencia se certifica aquí y ahora.
            { enableHighAccuracy: true, maximumAge: 0, timeout: 30_000 },
        );
    });
}
