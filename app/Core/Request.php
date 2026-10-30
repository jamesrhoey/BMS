<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $basePath;
    private string $path;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed> */
    private array $files;
    /** @var array<string, mixed> */
    private array $server;

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     */
    public function __construct(
        ?array $query = null,
        ?array $post = null,
        ?array $files = null,
        ?array $server = null
    ) {
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;

        $this->method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        [$this->basePath, $this->path] = $this->resolvePaths();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtoupper(str_replace('-', '_', $key));
        if (isset($this->server['HTTP_' . $normalized])) {
            return (string) $this->server['HTTP_' . $normalized];
        }
        if (isset($this->server[$normalized])) {
            return (string) $this->server[$normalized];
        }
        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            $val = $this->post[$key];
            return is_string($val) ? trim($val) : $val;
        }
        if (array_key_exists($key, $this->query)) {
            $val = $this->query[$key];
            return is_string($val) ? trim($val) : $val;
        }
        return $default;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        $cleaned = [];
        foreach ($this->post as $key => $val) {
            $cleaned[$key] = is_string($val) ? trim($val) : $val;
        }
        return $cleaned;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || !is_array($this->files[$key])) {
            return null;
        }
        return $this->files[$key];
    }

    public function ip(): ?string
    {
        $remoteAddr = (string) ($this->server['REMOTE_ADDR'] ?? '');
        if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
            return $remoteAddr;
        }
        return null;
    }

    public function isHttps(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        $port = (int) ($this->server['SERVER_PORT'] ?? 80);
        if ($port === 443) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePaths(): array
    {
        $requestUri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $rawPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $rawPath = '/' . ltrim($rawPath, '/');

        $scriptName = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }

        $basePath = '';
        if ($scriptDir !== '' && ($rawPath === $scriptDir || str_starts_with($rawPath, $scriptDir . '/'))) {
            $basePath = $scriptDir;
        } elseif (str_ends_with($scriptDir, '/public')) {
            $parentDir = substr($scriptDir, 0, -7);
            if ($parentDir !== '' && ($rawPath === $parentDir || str_starts_with($rawPath, $parentDir . '/'))) {
                $basePath = $parentDir;
            }
        }

        $relativePath = $basePath !== '' ? substr($rawPath, strlen($basePath)) : $rawPath;
        if ($relativePath === '' || $relativePath === false) {
            $relativePath = '/';
        } else {
            $relativePath = '/' . trim($relativePath, '/');
            if ($relativePath !== '/') {
                $relativePath = rtrim($relativePath, '/');
            }
        }

        return [$basePath, $relativePath];
    }
}

