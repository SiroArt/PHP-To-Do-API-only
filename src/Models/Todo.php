<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Todo
{
    public static function create(int $userId, string $title, ?string $description, string $todoDate, ?string $reminderTime): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO todos (user_id, title, description, todo_date, reminder_time)
             VALUES (:user_id, :title, :description, :todo_date, :reminder_time)'
        );
        $stmt->execute([
            ':user_id'       => $userId,
            ':title'         => $title,
            ':description'   => $description,
            ':todo_date'     => $todoDate,
            ':reminder_time' => $reminderTime,
        ]);

        return self::findById((int) $db->lastInsertId(), $userId);
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM todos WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $todo = $stmt->fetch();
        return $todo ?: null;
    }

    public static function findByDate(int $userId, string $date): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM todos WHERE user_id = :user_id AND todo_date = :date ORDER BY created_at ASC'
        );
        $stmt->execute([':user_id' => $userId, ':date' => $date]);
        return $stmt->fetchAll();
    }

    public static function findAllForUser(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM todos WHERE user_id = :user_id ORDER BY todo_date ASC, created_at ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function update(int $id, int $userId, string $title, ?string $description, string $todoDate, ?string $reminderTime): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE todos SET title = :title, description = :description, todo_date = :todo_date,
             reminder_time = :reminder_time WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute([
            ':title'         => $title,
            ':description'   => $description,
            ':todo_date'     => $todoDate,
            ':reminder_time' => $reminderTime,
            ':id'            => $id,
            ':user_id'       => $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'DELETE FROM todos WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function toggleComplete(int $id, int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE todos SET is_completed = NOT is_completed WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return self::findById($id, $userId);
    }

    public static function getTriggeredReminders(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM todos WHERE user_id = :user_id AND reminder_triggered = 1 AND is_completed = 0
             ORDER BY reminder_time ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function acknowledgeReminder(int $id, int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE todos SET reminder_triggered = 0, reminder_time = NULL
             WHERE id = :id AND user_id = :user_id AND reminder_triggered = 1'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function flagTriggeredReminders(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE todos SET reminder_triggered = 1
             WHERE reminder_time <= NOW() AND reminder_triggered = 0 AND is_completed = 0'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
