<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Services\JWTService;

class AuthMiddleware
{
    public static function handle(array $params = []): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            Response::error('Missing or invalid Authorization header.', 401);
            return false;
        }

        $token = $matches[1];
        $decoded = JWTService::validateAccessToken($token);

        if (!$decoded) {
            Response::error('Invalid or expired access token.', 401);
            return false;
        }

        // Make user info available to controllers
        $GLOBALS['auth_user_id'] = $decoded->uid;
        $GLOBALS['auth_user_uuid'] = $decoded->sub;

        return true;
    }
}
