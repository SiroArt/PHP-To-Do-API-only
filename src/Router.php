<?php

declare(strict_types=1);

namespace App;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware'  => $middleware,
        ];
    }

    public function resolve(string $method, string $uri): void
    {
        // Strip query string
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        $matchedRoutes = [];

        foreach ($this->routes as $route) {
            $pattern = $this->pathToRegex($route['path']);

            if (preg_match($pattern, $uri, $matches)) {
                $matchedRoutes[] = $route;

                if ($route['method'] !== $method) {
                    continue;
                }

                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run global middleware
                foreach ($this->globalMiddleware as $middleware) {
                    $result = $middleware($params);
                    if ($result === false) {
                        return;
                    }
                }

                // Run route-specific middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware($params);
                    if ($result === false) {
                        return;
                    }
                }

                // Call the handler
                ($route['handler'])($params);
                return;
            }
        }

        // If we found matching paths but wrong method → 405
        if (!empty($matchedRoutes)) {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // No match at all → 404
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not found']);
    }

    private function pathToRegex(string $path): string
    {
        // Convert {param} to named capture groups (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
