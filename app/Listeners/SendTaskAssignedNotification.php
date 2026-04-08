<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Jobs\CreateTaskNotification;

class SendTaskAssignedNotification
{
    /**
     * @param TaskAssigned $event
     * @return void
     */
    public function handle(TaskAssigned $event): void
    {
        CreateTaskNotification::dispatch($event->task, $event->assigner);
    }
}
