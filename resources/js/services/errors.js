// Lo que el servidor explicó cuando dijo que no: los mensajes de un 422
// (con las palabras del dominio) o el de un 409. Si no respondió, la conexión.

const NO_CONNECTION = 'No se pudo conectar con el servidor. Revise su conexión y vuelva a intentarlo.';

/** @returns {string[]} */
export function errorMessages(error) {
    const response = error?.response;
    if (!response) {
        return [NO_CONNECTION];
    }
    if (response.data?.errors) {
        return [...new Set(Object.values(response.data.errors).flat())];
    }
    return [response.data?.message ?? NO_CONNECTION];
}

export const errorMessage = (error) => errorMessages(error)[0];
