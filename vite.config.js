import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: resolve(__dirname, 'dist'),
        emptyOutDir: true,
        lib: {
            entry: resolve(__dirname, 'resources/js/field.js'),
            name: 'JsonWrapper',
            formats: ['umd'],
            fileName: () => 'js/field.js',
        },
        rollupOptions: {
            external: ['vue', 'laravel-nova'],
            output: {
                globals: {
                    vue: 'Vue',
                    'laravel-nova': 'LaravelNova',
                },
                assetFileNames: 'css/field[extname]',
            },
        },
    },
})
