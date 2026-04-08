<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateTaskNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @param Task $task
     * @param User $assigner
     */
    public function __construct(
        public readonly Task $task,
        public readonly User $assigner,
    ) {}

    /**
     * @return void
     */
    public function handle(): void
    {
        $message = "User {$this->assigner->name} assigned you task: {$this->task->title}";
        Notification::firstOrCreate(
            [
                'user_id' => $this->task->assigned_to,
                'type' => NotificationType::TaskAssigned,
                'message' => $message,
            ]
        );
    }
}
