import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // 관제 SPA는 별도 번들 엔트리(resources/js/control/main.js) — 07/FE-2.1 규정.
            // 기존 app.js 엔트리는 그대로 유지.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/control/main.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 9093,
    },
    resolve: {
        alias: {
            // 'vue': 'vue/dist/vue.esm-bundler.js', // alias 설정 추가
            //'@': 'resources/js',
        },
    },
});
