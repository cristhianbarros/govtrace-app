// It. 46e — R-PRIV-05: cada zona (un rostro, una placa) se difumina hasta no
// reconocerse: se encoge a unos pocos píxeles y se estira de vuelta sobre sí
// misma. Funciona en cualquier navegador (no necesita el filtro de canvas,
// que Safari no tuvo hasta hace poco), y lo que queda no se puede deshacer.

/** El lado mayor de cada zona, encogida: ni un rostro cercano se reconoce. */
export const BLUR_PIXELS = 6;
/** La zona que difumina un toque: casi un quinto del lado menor de la foto. */
const TOUCH_ZONE = 0.18;

const newCanvas = () => document.createElement('canvas');

function inside(zone, width, height) {
    const x = Math.max(0, Math.round(zone.x));
    const y = Math.max(0, Math.round(zone.y));
    return { x, y, width: Math.min(width, Math.round(zone.x + zone.width)) - x, height: Math.min(height, Math.round(zone.y + zone.height)) - y };
}

export function blurZones(canvas, zones, { createCanvas = newCanvas } = {}) {
    const context = canvas.getContext('2d');
    for (const zone of zones) {
        const { x, y, width, height } = inside(zone, canvas.width, canvas.height);
        if (width <= 0 || height <= 0) {
            continue;
        }
        const scale = BLUR_PIXELS / Math.max(width, height);
        const tiny = createCanvas();
        tiny.width = Math.max(1, Math.round(width * scale));
        tiny.height = Math.max(1, Math.round(height * scale));
        tiny.getContext('2d').drawImage(canvas, x, y, width, height, 0, 0, tiny.width, tiny.height);
        context.imageSmoothingEnabled = true;
        context.drawImage(tiny, 0, 0, tiny.width, tiny.height, x, y, width, height);
    }
}

/** Where a touch on the photo, as it is seen on screen, falls on the photo itself. */
export function pointOnImage(clientX, clientY, rect, width, height) {
    return { x: Math.round(((clientX - rect.left) * width) / rect.width), y: Math.round(((clientY - rect.top) * height) / rect.height) };
}

export function zoneAround(point, width, height) {
    const size = Math.round(Math.min(width, height) * TOUCH_ZONE);
    return {
        x: Math.max(0, Math.min(width - size, Math.floor(point.x - size / 2))),
        y: Math.max(0, Math.min(height - size, Math.floor(point.y - size / 2))),
        width: size,
        height: size,
    };
}

export const containsPoint = (zone, point) => point.x >= zone.x && point.x <= zone.x + zone.width && point.y >= zone.y && point.y <= zone.y + zone.height;

/**
 * The photo as it will be sent: the zones blurred, each one outlined —
 * solid what the detector found, dashed what was blurred by hand.
 */
export function renderPreview(target, source, zones) {
    target.width = source.width;
    target.height = source.height;
    const context = target.getContext('2d');
    context.drawImage(source, 0, 0);
    blurZones(target, zones);
    context.lineWidth = Math.max(3, Math.round(source.width / 300));
    context.strokeStyle = '#facc15';
    for (const zone of zones) {
        context.setLineDash(zone.byHand ? [context.lineWidth * 3, context.lineWidth * 2] : []);
        context.strokeRect(zone.x, zone.y, zone.width, zone.height);
    }
}
