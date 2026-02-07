<?php

/**
 * Cron job to flag triggered reminders.
 *
 * Run every minute via system cron:
 *   * * * * * php /path/to/project/cron/check_reminders.php
 *
 * Finds todos where reminder_time <= NOW() and reminder_triggered = 0
 * and is_completed = 0, then sets reminder_triggered = 1.
 *
 * The frontend polls GET /api/todos/reminders/triggered to pick up
 * triggered reminders for the authenticated user.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Models\Todo;
use App\Models\RememberToken;

// Flag triggered reminders
$flagged = Todo::flagTriggeredReminders();

// Clean up expired remember tokens
$cleaned = RememberToken::deleteExpired();

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Flagged {$flagged} reminder(s), cleaned {$cleaned} expired token(s).\n";
