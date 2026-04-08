<?php

namespace App\Providers;

use App\Events\TaskAssigned;
use App\Listeners\SendTaskAssignedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        Event::listen(
            TaskAssigned::class,
            SendTaskAssignedNotification::class,
        );
    }
}
