<?php

use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Payment\PaymentAccountException;
use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    // Laravel 默认示例命令，当前项目没有业务依赖；保留它不影响 Web 功能。
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('taskhub:sync-external-directories', function (): int {
    // 外部大列表统一由定时任务刷新到 Redis。
    // 运行时页面和登录流程优先读缓存，避免每次请求都慢查询外部接口。
    $paymentAccounts = app(PaymentAccountClient::class);
    $personnel = app(PersonnelClient::class);

    try {
        $paymentAccountCount = $paymentAccounts->refreshCache();
        $this->info("Payment account cache refreshed: {$paymentAccountCount} item(s).");
    } catch (PaymentAccountException $exception) {
        // 刷新失败时不清空旧缓存，保证页面仍可使用上一次成功同步的数据。
        $this->warn('Payment account cache was not refreshed: '.$exception->getMessage());
    }

    try {
        $personnelCount = $personnel->refreshCache();
        $this->info("Personnel cache refreshed: {$personnelCount} item(s).");
    } catch (PersonnelException $exception) {
        // 刷新失败时不清空旧缓存，保证登录增强和未来选择器仍可使用旧数据。
        $this->warn('Personnel cache was not refreshed: '.$exception->getMessage());
    }

    return 0;
})->purpose('Refresh external payment account and personnel directories into cache');

// 每 30 分钟刷新一次外部大列表。
// 服务器上需要配置 cron 执行 php artisan schedule:run，Laravel 调度器才会触发这里。
Schedule::command('taskhub:sync-external-directories')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
