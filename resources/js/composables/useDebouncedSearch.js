// Buscar en el servidor mientras se escribe: desde 3 caracteres y cuando
// pasan 300 ms sin escribir (US-016, US-012). Una búsqueda por pausa, no
// una por tecla; y si llega tarde la respuesta de una vieja, se ignora.
import { onBeforeUnmount, ref, watch } from 'vue';

export function useDebouncedSearch(search, { minCharacters = 3, delayMs = 300 } = {}) {
    const keyword = ref('');
    const results = ref(null); // null: todavía no hay búsqueda
    const searching = ref(false);
    const failed = ref(false);

    let timer;
    let latest = 0;

    async function run(text) {
        const current = ++latest;
        searching.value = true;
        failed.value = false;
        try {
            const found = await search(text);
            if (current === latest) {
                results.value = found;
            }
        } catch {
            if (current === latest) {
                failed.value = true;
            }
        } finally {
            if (current === latest) {
                searching.value = false;
            }
        }
    }

    watch(keyword, (value) => {
        clearTimeout(timer);
        const text = value.trim();
        if (text.length < minCharacters) {
            results.value = null;
            return;
        }
        timer = setTimeout(() => run(text), delayMs);
    });

    onBeforeUnmount(() => clearTimeout(timer));

    return { keyword, results, searching, failed };
}
