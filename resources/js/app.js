import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import locale from 'element-plus/dist/locale/es.mjs';

// 1. Importar librería de íconos
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Creamos la instancia de la app y la asignamos a una constante
        const app = createApp({ render: () => h(App, props) });

        // Usamos los plugins
        app.use(plugin)
           .use(ZiggyVue)
           .use(ElementPlus, { locale });

        // 2. Registrar cada ícono globalmente
        for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
            app.component(key, component)
        }

        // Finalmente montamos la app
        app.mount(el);
    },
    progress: {
        color: '#f26c17',
    },
});