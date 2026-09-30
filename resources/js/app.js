import '../css/app.css';
// It. 43b: escuchar desde el primer momento si el celular deja instalar la app.
import './lib/install.js';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'GovTrace';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    // Cada pantalla en su propio archivo: el teléfono del veedor descarga
    // "Nuevo Reporte", no el panel del Administrador.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f172a',
    },
});
