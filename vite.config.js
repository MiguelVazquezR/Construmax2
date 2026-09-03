import { defineConfig } from 'vite';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        AutoImport({
            resolvers: [ElementPlusResolver()],
        }),
        Components({
            resolvers: [ElementPlusResolver()],
        }),
    ],
    // comentar los siguientes bloques si no se usa el dominio construmax.test
    server: {
        detectTls: 'construmax.test',
        // 💡 Añade este bloque hmr para corregir el WebSocket de Vite:
        hmr: {
            host: 'construmax.test',
            protocol: 'wss',
        },
    },
});
