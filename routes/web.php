<?php

use App\Http\Controllers\SsoController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\EnsureSsoAuthenticated;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 首页只用于验证 Laravel + Inertia + React 应用壳是否正常。
// 真正业务入口目前是 /tasks。
Route::get('/', function () {
    return Inertia::render('Welcome', [
        // 通过 props 把 Laravel 配置传给 React 页面。
        'appName' => config('app.name'),
    ]);
})->name('home');

// 登录入口：浏览器跳转到公司 SSO。
Route::get('/login', [SsoController::class, 'redirect'])->name('sso.login');
// SSO 回调路径可配置，便于和公司登记的 callback 保持一致。
Route::get(config('sso.callback_path', '/sso/callback'), [SsoController::class, 'callback'])->name('sso.callback');
// React 回调页把 accessToken 提交到这里，由 Laravel 后端换取当前登录人信息并建立 Session。
Route::post('/sso/session', [SsoController::class, 'store'])->name('sso.session.store');
// 退出必须走原生 POST 表单，避免 Inertia Ajax 跟随外部 SSO 302 造成 CORS。
Route::post('/logout', [SsoController::class, 'logout'])->name('sso.logout');

// 业务页面统一受 SSO Session 保护；未登录时会被 EnsureSsoAuthenticated 重定向到 /login。
Route::middleware(EnsureSsoAuthenticated::class)->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    // 发布任务在任务大厅模态框内提交，不单独创建 /tasks/create 页面。
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
});
