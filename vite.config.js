import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    publicDir: false,
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@blockforge-cms/editor-sdk': fileURLToPath(new URL('../blockforge-cms/resources/js/editor-sdk/browser.js', import.meta.url)),
            vue: fileURLToPath(new URL('../blockforge-cms/resources/js/editor-sdk/browser-vue.js', import.meta.url)),
            pinia: fileURLToPath(new URL('../blockforge-cms/resources/js/editor-sdk/browser-pinia.js', import.meta.url)),
        },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                editorCss: 'resources/css/editor.css',
                editor: 'resources/js/editor.js',
            },
        },
    },
})
