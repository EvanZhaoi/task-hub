<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    // Laravel 默认示例命令，当前项目没有业务依赖；保留它不影响 Web 功能。
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
