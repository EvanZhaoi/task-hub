<?php

use App\Http\Controllers\SsoController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\EnsureSsoAuthenticated;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
    ]);
})->name('home');

Route::get('/login', [SsoController::class, 'redirect'])->name('sso.login');
Route::get(config('sso.callback_path', '/sso/callback'), [SsoController::class, 'callback'])->name('sso.callback');
Route::post('/sso/session', [SsoController::class, 'store'])->name('sso.session.store');
Route::post('/logout', [SsoController::class, 'logout'])->name('sso.logout');

Route::middleware(EnsureSsoAuthenticated::class)->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
});
