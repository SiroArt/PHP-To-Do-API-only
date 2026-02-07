<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Models\User;

class UserController
{
    public static function getProfile(array $params): void
    {
        $user = User::findById($GLOBALS['auth_user_id']);

        if (!$user) {
            Response::error('User not found.', 404);
            return;
        }

        Response::success(['user' => User::toSafeArray($user)]);
    }

    public static function updateProfile(array $params): void
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
        ]);

        if (!$isValid) {
            Response::validationError($validator->getErrors());
            return;
        }

        $userId = $GLOBALS['auth_user_id'];
        $username = Security::sanitizeString($data['username']);
        $email = strtolower(trim($data['email']));

        // Check uniqueness (excluding current user)
        $existingEmail = User::findByEmail($email);
        if ($existingEmail && (int) $existingEmail['id'] !== $userId) {
            Response::error('Email already in use.', 409);
            return;
        }

        $existingUsername = User::findByUsername($username);
        if ($existingUsername && (int) $existingUsername['id'] !== $userId) {
            Response::error('Username already taken.', 409);
            return;
        }

        User::updateProfile($userId, $username, $email);

        $user = User::findById($userId);
        Response::success(['user' => User::toSafeArray($user)], 'Profile updated.');
    }

    public static function changePassword(array $params): void
    {
        $data = Security::getJsonInput();

        if (!$data) {
            Response::error('Invalid JSON input.', 400);
            return;
        }

        $validator = new Validator();
        $isValid = $validator->validate($data, [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'password'],
        ]);

        if (!$isValid) {
            Response::validationError($validator->getErrors());
            return;
        }

        $userId = $GLOBALS['auth_user_id'];
        $user = User::findById($userId);

        if (!$user) {
            Response::error('User not found.', 404);
            return;
        }

        // Verify current password
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            Response::error('Current password is incorrect.', 403);
            return;
        }

        // Hash and update new password
        $newHash = password_hash($data['new_password'], PASSWORD_ARGON2ID);
        User::updatePassword($userId, $newHash);

        Response::success([], 'Password changed successfully.');
    }
}
