<?php

namespace App\Enums;

enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case TaskCompleted = 'task_completed';
    case TaskCommented = 'task_commented';
}
