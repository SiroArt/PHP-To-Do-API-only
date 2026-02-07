<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Models\Todo;

class TodoController
{
    public static function index(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $date = $_GET['date'] ?? null;

        if ($date) {
            $validator = new Validator();
            if (!$validator->validate(['date' => $date], ['date' => ['required', 'date']])) {
                Response::validationError($validator->getErrors());
                return;
            }
            $todos = Todo::findByDate($userId, $date);
        } else {
            $todos = Todo::findAllForUser($userId);
        }

        Response::success(['todos' => $todos]);
    }

    public static function show(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $id = (int) ($params['id'] ?? 0);

        $todo = Todo::findById($id, $userId);

        if (!$todo) {
            Response::error('Todo not found.', 404);
            return;
        }

        Response::success(['todo' => $todo]);
    }

    public static function store(array $params): void
    {
        $data = Security::getJsonInput();

        if (!$data) {
            Response::error('Invalid JSON input.', 400);
            return;
        }

        $validator = new Validator();
        $isValid = $validator->validate($data, [
            'title'         => ['required', 'string', 'min:1', 'max:255'],
            'todo_date'     => ['required', 'date'],
        ]);

        // Validate optional fields only if present
        if (isset($data['description'])) {
            $validator->validate($data, [
                'description' => ['string', 'max:5000'],
            ]);
        }
        if (isset($data['reminder_time']) && $data['reminder_time'] !== null) {
            $validator->validate($data, [
                'reminder_time' => ['datetime'],
            ]);
        }

        if (!$isValid || !empty($validator->getErrors())) {
            Response::validationError($validator->getErrors());
            return;
        }

        $userId = $GLOBALS['auth_user_id'];
        $title = Security::sanitizeString($data['title']);
        $description = isset($data['description']) ? Security::sanitizeString($data['description']) : null;
        $todoDate = $data['todo_date'];
        $reminderTime = $data['reminder_time'] ?? null;

        $todo = Todo::create($userId, $title, $description, $todoDate, $reminderTime);

        Response::created(['todo' => $todo]);
    }

    public static function update(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $id = (int) ($params['id'] ?? 0);

        $existing = Todo::findById($id, $userId);
        if (!$existing) {
            Response::error('Todo not found.', 404);
            return;
        }

        $data = Security::getJsonInput();

        if (!$data) {
            Response::error('Invalid JSON input.', 400);
            return;
        }

        $validator = new Validator();
        $isValid = $validator->validate($data, [
            'title'     => ['required', 'string', 'min:1', 'max:255'],
            'todo_date' => ['required', 'date'],
        ]);

        if (isset($data['description'])) {
            $validator->validate($data, [
                'description' => ['string', 'max:5000'],
            ]);
        }
        if (isset($data['reminder_time']) && $data['reminder_time'] !== null) {
            $validator->validate($data, [
                'reminder_time' => ['datetime'],
            ]);
        }

        if (!$isValid || !empty($validator->getErrors())) {
            Response::validationError($validator->getErrors());
            return;
        }

        $title = Security::sanitizeString($data['title']);
        $description = isset($data['description']) ? Security::sanitizeString($data['description']) : null;
        $todoDate = $data['todo_date'];
        $reminderTime = $data['reminder_time'] ?? null;

        Todo::update($id, $userId, $title, $description, $todoDate, $reminderTime);

        $todo = Todo::findById($id, $userId);
        Response::success(['todo' => $todo], 'Todo updated.');
    }

    public static function destroy(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $id = (int) ($params['id'] ?? 0);

        $deleted = Todo::delete($id, $userId);

        if (!$deleted) {
            Response::error('Todo not found.', 404);
            return;
        }

        Response::success([], 'Todo deleted.');
    }

    public static function toggleComplete(array $params): void
    {
        $userId = $GLOBALS['auth_user_id'];
        $id = (int) ($params['id'] ?? 0);

        $todo = Todo::toggleComplete($id, $userId);

        if (!$todo) {
            Response::error('Todo not found.', 404);
            return;
        }

        Response::success(['todo' => $todo], 'Todo completion toggled.');
    }
}
