<?php

class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $route, callable $handler): void
    {
        $this->routes['GET'][$route] = $handler;
    }

    public function post(string $route, callable $handler): void
    {
        $this->routes['POST'][$route] = $handler;
    }

    public function dispatch(string $method, string $route): void
    {
        $handler = $this->routes[$method][$route] ?? null;

        if ($handler === null) {
            http_response_code(404);
            require __DIR__ . '/../app/views/errors/404.php';
            return;
        }

        try {
            $handler();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            require __DIR__ . '/../app/views/errors/500.php';
        }
    }
}
