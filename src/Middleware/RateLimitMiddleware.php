<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Security;

class RateLimitMiddleware
{
    public static function forLogin(array $params = []): bool
    {
        $limit = (int) ($_ENV['RATE_LIMIT_LOGIN'] ?? 5);
        return self::check(Security::getClientIp(), '/api/auth/login', $limit, 60);
    }

    public static function forRegister(array $params = []): bool
    {
        $limit = (int) ($_ENV['RATE_LIMIT_REGISTER'] ?? 3);
        return self::check(Security::getClientIp(), '/api/auth/register', $limit, 60);
    }

    public static function forPasswordReset(array $params = []): bool
    {
        return self::check(Security::getClientIp(), '/api/auth/password-reset', 3, 60);
    }

    public static function forApi(array $params = []): bool
    {
        $limit = (int) ($_ENV['RATE_LIMIT_API'] ?? 60);
        $identifier = $GLOBALS['auth_user_id'] ?? Security::getClientIp();
        return self::check((string) $identifier, 'api_general', $limit, 60);
    }

    private static function check(string $identifier, string $endpoint, int $maxHits, int $windowSeconds): bool
    {
        $db = Database::getConnection();
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

        // Clean up old entries
        $cleanup = $db->prepare(
            'DELETE FROM rate_limits WHERE window_start < :window_start'
        );
        $cleanup->execute([':window_start' => $windowStart]);

        // Check current hits
        $stmt = $db->prepare(
            'SELECT id, hits FROM rate_limits
             WHERE identifier = :identifier
             AND endpoint = :endpoint
             AND window_start >= :window_start
             LIMIT 1'
        );
        $stmt->execute([
            ':identifier'   => $identifier,
            ':endpoint'     => $endpoint,
            ':window_start' => $windowStart,
        ]);
        $record = $stmt->fetch();

        if ($record) {
            if ((int) $record['hits'] >= $maxHits) {
                Response::json([
                    'error' => 'Too many requests. Please try again later.',
                ], 429);
                return false;
            }

            $update = $db->prepare(
                'UPDATE rate_limits SET hits = hits + 1 WHERE id = :id'
            );
            $update->execute([':id' => $record['id']]);
        } else {
            $insert = $db->prepare(
                'INSERT INTO rate_limits (identifier, endpoint, hits, window_start)
                 VALUES (:identifier, :endpoint, 1, :window_start)'
            );
            $insert->execute([
                ':identifier'   => $identifier,
                ':endpoint'     => $endpoint,
                ':window_start' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }
}
