// US-018: subir la bandeja de salida cuando vuelve la señal — el más antiguo
// primero, cada uno tal como se capturó. Si el servidor no responde (o no
// puede recibirlo por ahora: su error, una sesión vencida, la organización
// suspendida), el reporte se queda y se reintenta más tarde. Si lo rechaza
// para siempre (422: fuera de la geocerca, hash alterado…), se descarta y se
// dice por qué: reintentarlo no cambiaría la respuesta.
import { errorMessage } from '@/services/errors.js';

export function toFormData(record) {
    const form = new FormData();
    for (const [field, value] of Object.entries(record.fields)) {
        form.append(field, value);
    }
    record.files.forEach((file, index) => {
        form.append('files[]', new File([file.blob], file.name, { type: file.type }));
        form.append('hashes[]', record.hashes[index]);
    });
    return form;
}

/** @returns {Promise<{ sent: number, rejected: string[], failed: boolean }>} */
export async function flushOutbox({ outbox, send }) {
    const result = { sent: 0, rejected: [], failed: false };

    for (const record of await outbox.pending()) {
        try {
            await send(toFormData(record));
        } catch (error) {
            if (error?.response?.status === 422) {
                await outbox.remove(record.id);
                result.rejected.push(errorMessage(error));
                continue;
            }
            result.failed = true;
            break;
        }
        await outbox.remove(record.id);
        result.sent++;
    }

    return result;
}
