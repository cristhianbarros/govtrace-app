// It. 62c (US-009): la foto se toma en la página, con la cámara del navegador.
// Con la app de la cámara del teléfono al frente, GovTrace queda detrás, y un
// celular con poca memoria la cierra: al volver se reinicia sin la foto ni lo
// escrito. Aquí nunca queda detrás, y la foto sale del cuadro del video, cerca
// de los 1920 px que se guardan, sin decodificar una de 12 o 50 megapíxeles.
import { MAX_PHOTO_SIDE, releaseCanvas, scaledSize } from './photos.js';

/** La trasera, sin micrófono, con su lado mayor cerca de lo que se guarda. */
export const CAMERA_CONSTRAINTS = {
    audio: false,
    video: { facingMode: { ideal: 'environment' }, width: { ideal: MAX_PHOTO_SIDE }, height: { ideal: 1440 } },
};

// La foto se codifica otra vez al revisarla (al 80 %): aquí, casi sin pérdida.
const GRAB_QUALITY = 0.92;
const NO_FRAME = 'La cámara todavía no muestra la imagen. Intente de nuevo.';

/** Solo donde el navegador da la cámara: con HTTPS y getUserMedia. */
export function cameraInPage(nav = globalThis.navigator, secure = globalThis.isSecureContext) {
    return Boolean(secure && nav?.mediaDevices?.getUserMedia);
}

export function openCamera(nav = navigator) {
    return nav.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS);
}

export function stopCamera(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

/** El cuadro que muestra el video, en JPEG, a su tamaño y sin pasar de 1920 px. */
export async function grabPhoto(video, { createCanvas = () => document.createElement('canvas'), now = Date.now } = {}) {
    if (!video.videoWidth || !video.videoHeight) {
        throw new Error(NO_FRAME);
    }
    const { width, height } = scaledSize(video.videoWidth, video.videoHeight);
    const canvas = createCanvas();
    canvas.width = width;
    canvas.height = height;
    try {
        canvas.getContext('2d').drawImage(video, 0, 0, width, height);
        const jpeg = await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error(NO_FRAME))), 'image/jpeg', GRAB_QUALITY);
        });
        const time = new Date(now()).toTimeString().slice(0, 8).replaceAll(':', '-');
        return new File([jpeg], `foto-${time}.jpg`, { type: 'image/jpeg', lastModified: now() });
    } finally {
        releaseCanvas(canvas);
    }
}
