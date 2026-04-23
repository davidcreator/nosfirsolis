<?php

namespace System\Engine;

class Request
{
    public array $get;
    public array $post;
    public array $server;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function route(): string
    {
        $queryRoute = $this->get('route', '');
        if (is_string($queryRoute) && $queryRoute !== '') {
            return trim($queryRoute, '/');
        }

        $uriPath = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim($scriptDir, '/');

        if ($scriptDir !== '' && str_starts_with($uriPath, $scriptDir)) {
            $uriPath = substr($uriPath, strlen($scriptDir));
        }

        $uriPath = trim($uriPath, '/');
        if ($uriPath === '' || str_contains($uriPath, '.php')) {
            return '';
        }

        return $uriPath;
    }
}
