<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class PasswordReset
{
    public static function create(int $userId, string $tokenHash, string $expiresAt): bool
    {
        $db = Database::getConnection();

        // Invalidate any existing unused tokens for this user
        self::invalidateForUser($userId);

        $stmt = $db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        return $stmt->execute([
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    public static function findValidByHash(string $tokenHash): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = :token_hash
             AND used = 0
             AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $record = $stmt->fetch();
        return $record ?: null;
    }

    public static function markAsUsed(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE password_resets SET used = 1 WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    public static function invalidateForUser(int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE password_resets SET used = 1 WHERE user_id = :user_id AND used = 0'
        );
        return $stmt->execute([':user_id' => $userId]);
    }

    public static function deleteExpired(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
