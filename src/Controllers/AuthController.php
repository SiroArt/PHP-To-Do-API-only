<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Services\AuthService;

class AuthController
{
    public static function register(array $params): void
    {
        $data = Security::getJsonInput();

        if (!$data) {
            Response::error('Invalid JSON input.', 400);
            return;
        }

        $validator = new Validator();
        $isValid = $validator->validate($data, [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'password'],
        ]);

        if (!$isValid) {
            Response::validationError($validator->getErrors());
            return;
        }

        $username = Security::sanitizeString($data['username']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];

        $result = AuthService::register($username, $email, $password);

        if (isset($result['error'])) {
            Response::error($result['error'], $result['status']);
            return;
        }

        Response::created(['user' => $result['user']]);
    }

    public static function login(array $params): void
    {
        $data = Security::getJsonInput();

        if (!$data) {
            Response::error('Invalid JSON input.', 400);
            return;
        }

        $validator = new Validator();
        $isValid = $validator->validate($data, [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$isValid) {
            Response::validationError($validator->getErrors());
            return;
        }

        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        $rememberMe = !empty($data['remember_me']);

        $result = AuthService::login($email, $password, $rememberMe);

        if (isset($result['error'])) {
            Response::error($result['error'], $result['status']);
            return;
        }

        $response = [
            'access_token' => $result['access_token'],
            'token_type'   => $result['token_type'],
            'expires_in'   => $result['expires_in'],
            'user'         => $result['user'],
        ];

        if (isset($result['remember_me_token'])) {
            $response['remember_me_token'] = $result['remember_me_token'];
        }

        Response::success($response, 'Login successful.');
    }

    public static function refresh(array $params): void
    {
        $data = Security::getJsonInput();

        if (!$data || empty($data['remember_me_token'])) {
            Response::error('remember_me_token is required.', 400);
            return;
        }

        $result = AuthService::refresh($data['remember_me_token']);

        if (isset($result['error'])) {
            Response::error($result['error'], $result['status']);
            return;
        }

        Response::success([
            'access_token'      => $result['access_token'],
            'remember_me_token' => $result['remember_me_token'],
            'token_type'        => $result['token_type'],
            'expires_in'        => $result['expires_in'],
        ], 'Token refreshed.');
    }

    public static function logout(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $data = Security::getJsonInput();

        // Optionally revoke a specific token by JTI, otherwise revoke all
        $jti = $data['token_jti'] ?? null;

        AuthService::logout($userId, $jti);

        Response::success([], 'Logged out successfully.');
    }
}
