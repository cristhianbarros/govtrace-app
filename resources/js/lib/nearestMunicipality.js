// It. 59 (US-066): el municipio cuya cabecera está más cerca de donde está el
// ciudadano. Se calcula aquí, en su navegador: su ubicación no viaja al
// servidor. Cerca de un límite, la cabecera más cercana puede ser la de otro
// municipio: por eso la pantalla pide confirmarlo.

const EARTH_RADIUS_METERS = 6_371_000;
const radians = (degrees) => (degrees * Math.PI) / 180;

function distanceInMeters(a, b) {
    const dLat = radians(b.latitude - a.latitude);
    const dLng = radians(b.longitude - a.longitude);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(radians(a.latitude)) * Math.cos(radians(b.latitude)) * Math.sin(dLng / 2) ** 2;

    return 2 * EARTH_RADIUS_METERS * Math.asin(Math.sqrt(h));
}

/** @returns {{code: string, name: string, department: string}|null} */
export function nearestMunicipality(municipalities, here) {
    let nearest = null;
    let shortest = Infinity;
    for (const municipality of municipalities) {
        const distance = distanceInMeters(here, municipality);
        if (distance < shortest) {
            shortest = distance;
            nearest = municipality;
        }
    }

    return nearest;
}

/** Una lectura aproximada basta para saber el municipio: sin alta precisión, y una de hace un minuto sirve. */
export function roughPosition(geolocation = globalThis.navigator?.geolocation) {
    return new Promise((resolve, reject) => {
        if (!geolocation) {
            reject(new Error('Sin ubicación'));
            return;
        }
        geolocation.getCurrentPosition(
            ({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude }),
            () => reject(new Error('Sin ubicación')),
            { enableHighAccuracy: false, maximumAge: 60_000, timeout: 15_000 },
        );
    });
}
