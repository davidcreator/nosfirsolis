<?php

namespace System\Engine;

class Config
{
    private array $data = [];

    public function load(string $name, string $basePath): array
    {
        $file = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.php';
        if (!is_file($file)) {
            return [];
        }

        $loaded = require $file;
        if (!is_array($loaded)) {
            return [];
        }

        $this->data[$name] = $this->merge($this->data[$name] ?? [], $loaded);

        return $loaded;
    }

    public function mergeConfig(array $config): void
    {
        $this->data = $this->merge($this->data, $config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target =& $this->data;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target =& $target[$segment];
        }

        $target = $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    private function merge(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                if (array_is_list($base[$key]) && array_is_list($value)) {
                    $base[$key] = $value;
                    continue;
                }

                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
