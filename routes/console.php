<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    // Laravel 默认示例命令，当前项目没有业务依赖；保留它不影响 Web 功能。
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('taskhub:sync-external-directories', function (): int {
    // 外部付款账号和人员列表接口现在要求使用当前登录用户的动态 SSO accessToken。
    // Artisan 定时命令运行在后台，没有浏览器 Session，也没有当前登录用户。
    // 因此这里不再从 .env 读取固定 accessToken，避免把个人 token 写死在配置里。
    // 后续如果总部提供服务端凭证、client_credentials 或专用同步账号，再恢复真实后台刷新。
    $this->warn('External directory sync requires a dynamic SSO accessToken.');
    $this->warn('Scheduled remote refresh is disabled until a server-side token strategy is confirmed.');

    return 0;
})->purpose('Refresh external payment account and personnel directories into cache');

// 每分钟扫描已截止的 OPEN 招标任务；锁最多保留10分钟，防止上一轮未结束时重复启动。
// 命令内部按100条分批并限制单轮45秒，不为每个任务单独创建 Cron。
Schedule::command('taskhub:expire-open-tasks')
    ->everyMinute()
    ->withoutOverlapping(10);
