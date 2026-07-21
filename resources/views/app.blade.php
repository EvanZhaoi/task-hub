<!DOCTYPE html>
{{-- 这个 Blade 文件不是业务页面，而是 Inertia/React 的 HTML 容器。 --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Laravel web 路由的 POST 请求需要 CSRF token，React 通过工具函数从这里读取。 --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- inertia 标记让 Inertia 可以根据 React 页面标题动态更新 title。 --}}
        <title inertia>{{ config('app.name', 'TaskHub') }}</title>
        {{-- 开发环境启用 React Refresh，修改组件后页面可以热更新。 --}}
        @viteReactRefresh
        {{-- 只加载 React 入口；CSS 由 resources/js/app.tsx import '../css/app.css' 引入。 --}}
        @vite('resources/js/app.tsx')
        {{-- React 页面通过 Inertia 设置的 head 内容会注入到这里。 --}}
        @inertiaHead
    </head>
    <body>
        {{-- Inertia 在这里挂载 React 应用，对应 resources/js/app.tsx 中的 createRoot。 --}}
        @inertia
    </body>
</html>
