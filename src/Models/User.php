<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use Ramsey\Uuid\Uuid;

class User
{
    public static function create(string $username, string $email, string $passwordHash): array
    {
        $db = Database::getConnection();
        $uuid = Uuid::uuid4()->toString();

        $stmt = $db->prepare(
            'INSERT INTO users (uuid, username, email, password_hash)
             VALUES (:uuid, :username, :email, :password_hash)'
        );
        $stmt->execute([
            ':uuid'          => $uuid,
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $passwordHash,
        ]);

        return self::findByUuid($uuid);
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE uuid = :uuid LIMIT 1');
        $stmt->execute([':uuid' => $uuid]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function updateProfile(int $id, string $username, string $email): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE users SET username = :username, email = :email WHERE id = :id'
        );
        return $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':id'       => $id,
        ]);
    }

    public static function updatePassword(int $id, string $passwordHash): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        return $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id'            => $id,
        ]);
    }

    public static function incrementFailedAttempts(int $id): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        // Lock account after 5 failed attempts
        $user = self::findById($id);
        if ($user && (int) $user['failed_login_attempts'] >= 5) {
            self::lockAccount($id);
        }
    }

    public static function resetFailedAttempts(int $id): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function lockAccount(int $id): void
    {
        $db = Database::getConnection();
        $lockedUntil = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        $stmt = $db->prepare(
            'UPDATE users SET locked_until = :locked_until WHERE id = :id'
        );
        $stmt->execute([
            ':locked_until' => $lockedUntil,
            ':id'           => $id,
        ]);
    }

    public static function isLocked(array $user): bool
    {
        if ($user['locked_until'] === null) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    public static function toSafeArray(array $user): array
    {
        return [
            'uuid'       => $user['uuid'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
        ];
    }
}
