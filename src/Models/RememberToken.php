<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class RememberToken
{
    public static function store(int $userId, string $jti, string $tokenHash, string $familyId, string $expiresAt): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO remember_tokens (user_id, token_jti, token_hash, family_id, expires_at)
             VALUES (:user_id, :token_jti, :token_hash, :family_id, :expires_at)'
        );
        return $stmt->execute([
            ':user_id'    => $userId,
            ':token_jti'  => $jti,
            ':token_hash' => $tokenHash,
            ':family_id'  => $familyId,
            ':expires_at' => $expiresAt,
        ]);
    }

    public static function findByJti(string $jti): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM remember_tokens WHERE token_jti = :jti LIMIT 1'
        );
        $stmt->execute([':jti' => $jti]);
        $token = $stmt->fetch();
        return $token ?: null;
    }

    public static function revokeByJti(string $jti): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE remember_tokens SET revoked = 1 WHERE token_jti = :jti'
        );
        return $stmt->execute([':jti' => $jti]);
    }

    public static function revokeByFamily(string $familyId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE remember_tokens SET revoked = 1 WHERE family_id = :family_id'
        );
        return $stmt->execute([':family_id' => $familyId]);
    }

    public static function revokeAllForUser(int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE remember_tokens SET revoked = 1 WHERE user_id = :user_id'
        );
        return $stmt->execute([':user_id' => $userId]);
    }

    public static function deleteExpired(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'DELETE FROM remember_tokens WHERE expires_at < NOW()'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
