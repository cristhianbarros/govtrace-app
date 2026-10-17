// It. 52 — un listado que llega de a una página ("Ver 20 más"), con búsqueda.
// Como useLoader (data, loading, error, load), más: `more` trae la página siguiente y
// la suma; `search` busca con una pausa de 300 ms (una por pausa, no una por tecla);
// `meta` dice cuántas hay (total) y si hay más. Cambiar los parámetros (`load(params)`)
// vuelve a la primera página, y la respuesta de una petición vieja no pisa a la nueva.
// `fetch` recibe { ...params, page } y responde { data, total, has_more }.
import { onBeforeUnmount, ref } from 'vue';
import { errorMessage } from '@/services/errors.js';

export function usePagedList(fetch, { delayMs = 300 } = {}) {
    const data = ref(null);
    const meta = ref(null);
    const loading = ref(true);
    const loadingMore = ref(false);
    const error = ref(null);

    let params = {};
    let page = 1;
    let latest = 0;
    let timer;

    async function run({ more = false } = {}) {
        const current = ++latest;
        (more ? loadingMore : loading).value = true;
        error.value = null;
        try {
            const body = await fetch({ ...params, page });
            if (current === latest) {
                data.value = more ? [...(data.value ?? []), ...body.data] : body.data;
                // It. 65a: lo que traiga además la respuesta (el número de cada pestaña, las opciones de los filtros).
                const { data: _rows, ...rest } = body;
                meta.value = rest;
            }
        } catch (failure) {
            if (current === latest) {
                error.value = errorMessage(failure);
            }
        } finally {
            if (current === latest) {
                loading.value = false;
                loadingMore.value = false;
            }
        }
    }

    /** Desde la primera página; con parámetros nuevos si se dan, y con los mismos si no. */
    function load(newParams) {
        if (newParams !== undefined) {
            params = newParams;
        }
        page = 1;
        return run();
    }

    function more() {
        page += 1;
        return run({ more: true });
    }

    /** Busca `text` (en `key`, por defecto q) tras una pausa; vacío, trae todo. */
    function search(text, key = 'q') {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const { [key]: _old, ...rest } = params;
            load(text.trim() === '' ? rest : { ...rest, [key]: text.trim() });
        }, delayMs);
    }

    onBeforeUnmount(() => clearTimeout(timer));

    return { data, meta, loading, loadingMore, error, load, more, search };
}
