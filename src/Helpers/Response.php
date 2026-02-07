<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);

        // Security headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        self::json(['error' => $message], $statusCode);
    }

    public static function validationError(array $errors): void
    {
        self::json(['error' => 'Validation failed', 'details' => $errors], 422);
    }

    public static function success(array $data = [], string $message = 'Success'): void
    {
        self::json(array_merge(['message' => $message], $data), 200);
    }

    public static function created(array $data = [], string $message = 'Created'): void
    {
        self::json(array_merge(['message' => $message], $data), 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }
}
