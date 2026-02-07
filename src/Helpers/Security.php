<?php

declare(strict_types=1);

namespace App\Helpers;

class Security
{
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function getClientIp(): string
    {
        // Only trust REMOTE_ADDR — do not trust X-Forwarded-For
        // unless behind a known reverse proxy
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function getJsonInput(): ?array
    {
        $raw = file_get_contents('php://input');

        if (empty($raw)) {
            return null;
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function constantTimeCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}
