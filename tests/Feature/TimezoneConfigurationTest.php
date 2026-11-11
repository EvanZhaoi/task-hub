<?php

test('taskhub uses china standard time for application and mysql connections', function (): void {
    // Laravel 的业务时间使用地区时区，确保 now()、格式化和 Scheduler 都按中国标准时间运行。
    expect(config('app.timezone'))->toBe('Asia/Shanghai')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Shanghai')
        // MySQL 使用固定偏移，避免数据库没有安装命名时区表时连接失败。
        ->and(config('database.connections.mysql.timezone'))->toBe('+08:00');
});
