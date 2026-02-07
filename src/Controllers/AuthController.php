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
}
