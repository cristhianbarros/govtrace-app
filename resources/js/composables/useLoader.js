// Cargar datos del servidor con sus estados: cargando, error (con el motivo)
// y los datos. `load` también sirve para "Reintentar". Empieza cargando: la
// pantalla pide sus datos al montarse, y nunca muestra un "vacío" falso.
import { ref } from 'vue';
import { errorMessage } from '@/services/errors.js';

export function useLoader(fetch) {
    const data = ref(null);
    const loading = ref(true);
    const error = ref(null);

    async function load(...args) {
        loading.value = true;
        error.value = null;
        try {
            data.value = await fetch(...args);
        } catch (failure) {
            error.value = errorMessage(failure);
        } finally {
            loading.value = false;
        }
    }

    return { data, loading, error, load };
}
