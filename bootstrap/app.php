<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Laravel 13 的应用启动配置入口。
// 这里相当于 Spring Boot 中启动类 + Web 配置的一部分，但 Laravel 用链式配置保持轻量。
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Web 路由加载 routes/web.php，默认包含 Session、CSRF、Cookie 等 web 中间件。
        web: __DIR__.'/../routes/web.php',
        // 控制台命令路由，当前项目暂未定义自定义命令。
        commands: __DIR__.'/../routes/console.php',
        // 健康检查端点，部署平台可访问 /up 判断应用是否启动。
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // Inertia 共享 props 和根 Blade 配置必须挂到 web 中间件组。
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // API 请求发生异常时返回 JSON；普通 Web/Inertia 页面仍走 Laravel 默认错误页。
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
