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

$router = new Router();

// Routes will be registered in subsequent commits

// Resolve the current request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$router->resolve($method, $uri);
