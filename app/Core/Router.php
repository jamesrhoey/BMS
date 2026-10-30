<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<mixed>}> */
    private array $routes = [];

    /** @var list<mixed> */
    private array $globalMiddleware = [];

    /**
     * @param mixed $middleware
     */
    public function addGlobalMiddleware(mixed $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<mixed> $middleware
     */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<mixed> $middleware
     */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<mixed> $middleware
     */
    private function addRoute(string $method, string $pattern, array $handler, array $middleware): void
    {
        $normalized = '/' . trim($pattern, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        $regexPattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $normalized
        );

        $regex = '#^' . $regexPattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalized,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        // Execute global middleware first (e.g., CSRF protection for all POST requests)
        foreach ($this->globalMiddleware as $mw) {
            $this->runMiddleware($mw, $request);
        }

        $pathMatchedDifferentMethod = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] !== $method) {
                    $pathMatchedDifferentMethod = true;
                    continue;
                }

                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw, $request);
                }

                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                [$controllerClass, $action] = $route['handler'];
                if (!class_exists($controllerClass)) {
                    throw new RuntimeException("Controller [{$controllerClass}] not found.");
                }

                $controller = new $controllerClass();
                if (!method_exists($controller, $action)) {
                    throw new RuntimeException("Action [{$controllerClass}::{$action}] not found.");
                }

                $controller->{$action}($request, $params);
                return;
            }
        }

        if ($pathMatchedDifferentMethod) {
            http_response_code(405);
            View::render('errors/404', [
                'pageTitle' => 'Method Not Allowed',
                'errorHeading' => '405 - Method Not Allowed',
                'errorMessage' => 'The requested action is not supported for this HTTP method.',
            ], Session::has('auth_user_id') ? 'layouts/app' : 'layouts/auth');
            return;
        }

        http_response_code(404);
        View::render('errors/404', [
            'pageTitle' => 'Page Not Found',
        ], Session::has('auth_user_id') ? 'layouts/app' : 'layouts/auth');
    }

    private function runMiddleware(mixed $definition, Request $request): void
    {
        if (is_string($definition)) {
            $instance = new $definition();
            $instance->handle($request);
            return;
        }

        if (is_array($definition) && isset($definition[0]) && is_string($definition[0])) {
            $class = $definition[0];
            $args = array_slice($definition, 1);
            $instance = new $class(...$args);
            $instance->handle($request);
            return;
        }

        throw new RuntimeException('Invalid middleware definition.');
    }
}

