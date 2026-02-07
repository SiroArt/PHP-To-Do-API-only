<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Ramsey\Uuid\Uuid;

class JWTService
{
    public static function createAccessToken(int $userId, string $userUuid): array
    {
        $jti = Uuid::uuid4()->toString();
        $now = time();
        $expiry = $now + (int) $_ENV['JWT_ACCESS_EXPIRY'];

        $payload = [
            'iss' => 'todo-api',
            'sub' => $userUuid,
            'uid' => $userId,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $expiry,
            'type' => 'access',
        ];

        $token = JWT::encode($payload, $_ENV['JWT_ACCESS_SECRET'], 'HS256');

        return [
            'token' => $token,
            'jti'   => $jti,
            'expires_at' => $expiry,
        ];
    }

    public static function createRememberMeToken(int $userId, string $userUuid, ?string $familyId = null): array
    {
        $jti = Uuid::uuid4()->toString();
        $familyId = $familyId ?? Uuid::uuid4()->toString();
        $now = time();
        $expiry = $now + (int) $_ENV['JWT_REMEMBER_EXPIRY'];

        $payload = [
            'iss' => 'todo-api',
            'sub' => $userUuid,
            'uid' => $userId,
            'jti' => $jti,
            'fid' => $familyId,
            'iat' => $now,
            'exp' => $expiry,
            'type' => 'remember',
        ];

        $token = JWT::encode($payload, $_ENV['JWT_REMEMBER_SECRET'], 'HS256');

        return [
            'token'      => $token,
            'jti'        => $jti,
            'family_id'  => $familyId,
            'expires_at' => date('Y-m-d H:i:s', $expiry),
        ];
    }

    public static function validateAccessToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_ACCESS_SECRET'], 'HS256'));

            if (($decoded->type ?? '') !== 'access') {
                return null;
            }

            return $decoded;
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function validateRememberMeToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_REMEMBER_SECRET'], 'HS256'));

            if (($decoded->type ?? '') !== 'remember') {
                return null;
            }

            return $decoded;
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
