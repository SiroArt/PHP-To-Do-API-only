<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$dotenv->required([
    'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
    'JWT_ACCESS_SECRET', 'JWT_ACCESS_EXPIRY',
    'JWT_REMEMBER_SECRET', 'JWT_REMEMBER_EXPIRY',
])->notEmpty();

use App\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\TodoController;
use App\Controllers\ReminderController;

$router = new Router();

// Global middleware
$router->addGlobalMiddleware([CorsMiddleware::class, 'handle']);

// Auth routes
$router->post('/api/auth/register', [AuthController::class, 'register'], [
    [RateLimitMiddleware::class, 'forRegister'],
]);
$router->post('/api/auth/login', [AuthController::class, 'login'], [
    [RateLimitMiddleware::class, 'forLogin'],
]);
$router->post('/api/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword'], [
    [RateLimitMiddleware::class, 'forPasswordReset'],
]);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword'], [
    [RateLimitMiddleware::class, 'forPasswordReset'],
]);

// User profile routes
$authMw = [[AuthMiddleware::class, 'handle'], [RateLimitMiddleware::class, 'forApi']];
$router->get('/api/user/profile', [UserController::class, 'getProfile'], $authMw);
$router->put('/api/user/profile', [UserController::class, 'updateProfile'], $authMw);
$router->put('/api/user/password', [UserController::class, 'changePassword'], $authMw);

// Todo routes (static paths before parameterized to avoid {id} matching "reminders")
$router->get('/api/todos', [TodoController::class, 'index'], $authMw);
$router->post('/api/todos', [TodoController::class, 'store'], $authMw);
$router->get('/api/todos/reminders/triggered', [ReminderController::class, 'triggered'], $authMw);
$router->get('/api/todos/{id}', [TodoController::class, 'show'], $authMw);
$router->put('/api/todos/{id}', [TodoController::class, 'update'], $authMw);
$router->delete('/api/todos/{id}', [TodoController::class, 'destroy'], $authMw);
$router->patch('/api/todos/{id}/complete', [TodoController::class, 'toggleComplete'], $authMw);
$router->patch('/api/todos/{id}/reminder-ack', [ReminderController::class, 'acknowledge'], $authMw);

// Resolve the current request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$router->resolve($method, $uri);
