// Solo para los tests: un doble de @inertiajs/vue3. En useForm, `post` queda
// registrado en `submissions` y el test decide qué responde el servidor
// (errores de validación o éxito) con `respondWith`. `page` es lo que
// devuelve usePage(): las props compartidas y la URL actual.
//
//   vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
import { defineComponent, h, reactive } from 'vue';
import { vi } from 'vitest';

export const Head = { render: () => null };

export const Link = defineComponent({
    props: { href: { type: String, required: true } },
    setup: (props, { slots, attrs }) => () => h('a', { ...attrs, href: props.href }, slots.default?.()),
});

export const page = reactive({ props: { organization: 'Veeduría Ciudadana Santa Marta' }, url: '/admin/inbox' });

export const usePage = () => page;

/** router.visit y router.reload quedan registrados: el test mira a dónde navegó la pantalla, o qué volvió a pedir. */
export const router = { visit: vi.fn(), reload: vi.fn() };

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
