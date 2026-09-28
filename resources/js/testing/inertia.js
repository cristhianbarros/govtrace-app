// Solo para los tests: un doble de useForm de Inertia. `post` queda
// registrado en `submissions`; el test decide qué responde el servidor
// (errores de validación o éxito) con `respondWith`.
import { reactive } from 'vue';
import { vi } from 'vitest';

export const submissions = [];
let serverAnswer = () => {};

/** errors: {campo: mensaje} como los devuelve Laravel; nada = éxito. */
export function respondWith(errors = null) {
    serverAnswer = (form, options) => {
        if (errors) {
            form.errors = { ...errors };
            options.onError?.(form.errors);
        } else {
            options.onSuccess?.();
        }
        options.onFinish?.();
    };
}

export function resetInertia() {
    submissions.length = 0;
    serverAnswer = () => {};
}

export function useForm(initial) {
    const form = reactive({
        ...initial,
        errors: {},
        processing: false,
        post: vi.fn((url, options = {}) => {
            submissions.push({ url, data: Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]])) });
            serverAnswer(form, options);
        }),
        reset: (...fields) => fields.forEach((field) => (form[field] = initial[field])),
        clearErrors: () => (form.errors = {}),
    });
    return form;
}
