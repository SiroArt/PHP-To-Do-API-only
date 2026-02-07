<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\Todo;

class ReminderController
{
    public static function triggered(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $reminders = Todo::getTriggeredReminders($userId);

        Response::success(['reminders' => $reminders]);
    }

    public static function acknowledge(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $id = (int) ($params['id'] ?? 0);

        $acknowledged = Todo::acknowledgeReminder($id, $userId);

        if (!$acknowledged) {
            Response::error('Reminder not found or already acknowledged.', 404);
            return;
        }

        Response::success([], 'Reminder acknowledged.');
    }
}
