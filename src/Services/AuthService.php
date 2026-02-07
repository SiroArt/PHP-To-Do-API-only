<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\RememberToken;
use App\Models\PasswordReset;
use App\Helpers\Security;

class AuthService
{
    public static function register(string $username, string $email, string $password): array
    {
        // Check uniqueness
        if (User::findByEmail($email)) {
            return ['error' => 'Email already in use.', 'status' => 409];
        }

        if (User::findByUsername($username)) {
            return ['error' => 'Username already taken.', 'status' => 409];
        }

        // Hash password with Argon2id
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $user = User::create($username, $email, $hash);

        return [
            'user'   => User::toSafeArray($user),
            'status' => 201,
        ];
    }

    public static function login(string $email, string $password, bool $rememberMe = false): array
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return ['error' => 'Invalid credentials.', 'status' => 401];
        }

        // Check account lockout
        if (User::isLocked($user)) {
            return ['error' => 'Account is temporarily locked. Try again later.', 'status' => 423];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            User::incrementFailedAttempts((int) $user['id']);
            return ['error' => 'Invalid credentials.', 'status' => 401];
        }

        // Reset failed attempts on successful login
        User::resetFailedAttempts((int) $user['id']);

        // Generate access token
        $accessToken = JWTService::createAccessToken((int) $user['id'], $user['uuid']);

        $result = [
            'access_token' => $accessToken['token'],
            'token_type'   => 'Bearer',
            'expires_in'   => (int) $_ENV['JWT_ACCESS_EXPIRY'],
            'user'         => User::toSafeArray($user),
            'status'       => 200,
        ];

        // Generate remember-me token if requested
        if ($rememberMe) {
            $rememberToken = JWTService::createRememberMeToken((int) $user['id'], $user['uuid']);

            // Store hashed token in DB
            RememberToken::store(
                (int) $user['id'],
                $rememberToken['jti'],
                Security::hashToken($rememberToken['token']),
                $rememberToken['family_id'],
                $rememberToken['expires_at']
            );

            $result['remember_me_token'] = $rememberToken['token'];
        }

        return $result;
    }

    public static function refresh(string $rememberMeTokenRaw): array
    {
        // Validate JWT signature and expiry
        $decoded = JWTService::validateRememberMeToken($rememberMeTokenRaw);

        if (!$decoded) {
            return ['error' => 'Invalid or expired remember-me token.', 'status' => 401];
        }

        // Look up token in DB by JTI
        $storedToken = RememberToken::findByJti($decoded->jti);

        if (!$storedToken) {
            return ['error' => 'Token not found.', 'status' => 401];
        }

        // Check if token was revoked (theft detection)
        if ((int) $storedToken['revoked'] === 1) {
            // Revoked token reuse detected — invalidate entire family
            RememberToken::revokeByFamily($storedToken['family_id']);
            return ['error' => 'Token reuse detected. All sessions in this family have been revoked.', 'status' => 401];
        }

        // Verify token hash matches
        $tokenHash = Security::hashToken($rememberMeTokenRaw);
        if (!Security::constantTimeCompare($storedToken['token_hash'], $tokenHash)) {
            return ['error' => 'Token verification failed.', 'status' => 401];
        }

        // Check expiry in DB
        if (strtotime($storedToken['expires_at']) < time()) {
            RememberToken::revokeByJti($decoded->jti);
            return ['error' => 'Token expired.', 'status' => 401];
        }

        $user = User::findById((int) $storedToken['user_id']);
        if (!$user) {
            return ['error' => 'User not found.', 'status' => 401];
        }

        // Revoke old token
        RememberToken::revokeByJti($decoded->jti);

        // Issue new token pair (rotation — same family)
        $accessToken = JWTService::createAccessToken((int) $user['id'], $user['uuid']);
        $newRememberToken = JWTService::createRememberMeToken(
            (int) $user['id'],
            $user['uuid'],
            $storedToken['family_id']
        );

        // Store new remember token hash
        RememberToken::store(
            (int) $user['id'],
            $newRememberToken['jti'],
            Security::hashToken($newRememberToken['token']),
            $newRememberToken['family_id'],
            $newRememberToken['expires_at']
        );

        return [
            'access_token'     => $accessToken['token'],
            'remember_me_token' => $newRememberToken['token'],
            'token_type'       => 'Bearer',
            'expires_in'       => (int) $_ENV['JWT_ACCESS_EXPIRY'],
            'status'           => 200,
        ];
    }

    public static function logout(int $userId, ?string $jti = null): void
    {
        if ($jti) {
            RememberToken::revokeByJti($jti);
        } else {
            RememberToken::revokeAllForUser($userId);
        }
    }

    public static function forgotPassword(string $email): array
    {
        $user = User::findByEmail($email);

        // Always return success to prevent email enumeration
        if (!$user) {
            return ['status' => 200];
        }

        // Generate a cryptographically secure random token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = Security::hashToken($rawToken);

        $expiry = (int) ($_ENV['PASSWORD_RESET_EXPIRY'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiry);

        PasswordReset::create((int) $user['id'], $tokenHash, $expiresAt);

        return [
            'reset_token' => $rawToken,
            'expires_in'  => $expiry,
            'status'      => 200,
        ];
    }

    public static function resetPassword(string $rawToken, string $newPassword): array
    {
        $tokenHash = Security::hashToken($rawToken);
        $record = PasswordReset::findValidByHash($tokenHash);

        if (!$record) {
            return ['error' => 'Invalid or expired reset token.', 'status' => 400];
        }

        $user = User::findById((int) $record['user_id']);
        if (!$user) {
            return ['error' => 'User not found.', 'status' => 404];
        }

        // Update password with Argon2id
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        User::updatePassword((int) $user['id'], $hash);

        // Mark token as used
        PasswordReset::markAsUsed((int) $record['id']);

        // Revoke all remember-me tokens — force re-login everywhere
        RememberToken::revokeAllForUser((int) $user['id']);

        // Reset any account lockout
        User::resetFailedAttempts((int) $user['id']);

        return ['status' => 200];
    }
}
