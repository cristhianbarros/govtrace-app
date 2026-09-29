import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        // tools/verify: el verificador independiente de US-026 (Node, sin navegador).
        include: ['resources/js/**/*.test.js', 'tools/**/*.test.mjs'],
    },
});
