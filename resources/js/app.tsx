import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

type PageModule = {
    // 每个 Inertia 页面文件都必须 default export 一个 React 组件。
    default: ComponentType;
};

// Vite 在构建时扫描 Pages 目录，生成“页面路径 -> 动态 import 函数”的映射。
// 例如 Inertia::render('Tasks/Index') 会对应 ./Pages/Tasks/Index.tsx。
const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx');

createInertiaApp({
    // 统一页面标题格式；具体页面标题由 Inertia 页面后续按需要传入。
    title: (title) => (title ? `${title} - TaskHub` : 'TaskHub'),
    resolve: async (name) => {
        // Laravel 传来的页面名不带 ./Pages 和 .tsx，需要在这里拼成真实文件路径。
        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            // 页面名大小写必须和文件路径一致，Linux 部署环境对大小写敏感。
            throw new Error(`Page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        if (!el) {
            // app.blade.php 中的 @inertia 会生成根节点；缺失说明 Blade 容器配置错误。
            throw new Error('Inertia root element was not found.');
        }

        // React 从 Blade 容器接管页面，之后页面切换由 Inertia 协调 Laravel 和 React。
        createRoot(el).render(<App {...props} />);
    },
});
