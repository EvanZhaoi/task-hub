<?php

namespace App\Providers;

use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskChangeRequest;
use App\Models\TaskDelivery;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // register 阶段适合把服务绑定到 Laravel 容器。
        // 当前 SsoClient、Service 等类都可以自动解析，暂不手写无必要绑定。
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // AttachmentRef 使用 owner_type + owner_id 做多态关联。
        // enforceMorphMap 让数据库保存 TASK/BID 等稳定业务类型，而不是保存 PHP 类全名。
        Relation::enforceMorphMap([
            // 任务发布附件。
            'TASK' => Task::class,
            // 投标附件。
            'BID' => Bid::class,
            // 交付附件。
            'DELIVERY' => TaskDelivery::class,
            // 变更申请附件。
            'CHANGE_REQUEST' => TaskChangeRequest::class,
        ]);
    }
}
