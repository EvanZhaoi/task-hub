<?php

namespace App\Providers;

use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskChangeRequest;
use App\Models\TaskDelivery;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'TASK' => Task::class,
            'BID' => Bid::class,
            'DELIVERY' => TaskDelivery::class,
            'CHANGE_REQUEST' => TaskChangeRequest::class,
        ]);
    }
}
