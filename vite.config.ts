import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { defineConfig } from 'vite';
import { bunny } from 'laravel-vite-plugin/fonts';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    resolve: {
        alias: {
            // 与 tsconfig.json 中的 @/* 保持一致。
            // TypeScript 负责类型检查，Vite 负责开发服务和生产构建解析。
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            // 只把 React 入口交给 Vite；CSS 在 resources/js/app.tsx 中 import。
            input: ['resources/js/app.tsx'],
            // PHP/Blade/React 文件变化时自动刷新浏览器。
            refresh: true,
            fonts: [
                // 当前项目额外使用 Bunny Fonts 加载 Instrument Sans，不是 Inertia 必需配置。
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            // Laravel 会生成编译后的 Blade 缓存文件；忽略它们可以减少开发时无意义刷新。
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
