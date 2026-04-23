<?php

namespace System\Library;

class Language
{
    private array $data = [];
    private array $loadedFiles = [];
    private string $normalizedCode;
    private string $normalizedFallback;

    public function __construct(
        private readonly string $area,
        string $code,
        string $fallbackCode = 'en-us'
    )
    {
        $this->normalizedCode = $this->normalizeCode($code);
        $this->normalizedFallback = $this->normalizeCode($fallbackCode);
    }

    public function load(string $file): void
    {
        $file = $this->normalizeFile($file);
        if ($file === '' || isset($this->loadedFiles[$file])) {
            return;
        }

        $fallback = $this->loadFile($this->normalizedFallback, $file);
        $current = $this->normalizedCode === $this->normalizedFallback
            ? []
            : $this->loadFile($this->normalizedCode, $file);

        $this->data = array_merge($this->data, $fallback, $current);
        $this->loadedFiles[$file] = true;
    }

    public function loadAll(): void
    {
        $files = $this->availableFiles();
        foreach ($files as $file) {
            $this->load($file);
        }
    }

    public function get(string $key, string $default = ''): string
    {
        $this->loadForKey($key);

        return (string) ($this->data[$key] ?? $default);
    }

    public function code(): string
    {
        return $this->normalizedCode;
    }

    private function loadForKey(string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        $prefix = 'common';
        $dotPos = strpos($key, '.');
        if ($dotPos !== false && $dotPos > 0) {
            $prefix = substr($key, 0, $dotPos);
        }

        $this->load($prefix);
    }

    private function loadFile(string $code, string $file): array
    {
        $path = DIR_ROOT . DIRECTORY_SEPARATOR
            . $this->area . DIRECTORY_SEPARATOR
            . 'Language' . DIRECTORY_SEPARATOR
            . $code . DIRECTORY_SEPARATOR
            . $file . '.php';

        if (!is_file($path)) {
            return [];
        }

        $values = require $path;
        if (!is_array($values)) {
            return [];
        }

        return $values;
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = str_replace('_', '-', $code);

        return $code !== '' ? $code : 'en-us';
    }

    private function normalizeFile(string $file): string
    {
        $file = strtolower(trim($file));
        $file = str_replace(['\\', '/'], '', $file);

        return preg_match('/^[a-z0-9_-]+$/', $file) === 1 ? $file : '';
    }

    private function availableFiles(): array
    {
        $files = [];
        foreach ([$this->normalizedFallback, $this->normalizedCode] as $code) {
            $dir = DIR_ROOT . DIRECTORY_SEPARATOR
                . $this->area . DIRECTORY_SEPARATOR
                . 'Language' . DIRECTORY_SEPARATOR
                . $code;

            if (!is_dir($dir)) {
                continue;
            }

            $entries = glob($dir . DIRECTORY_SEPARATOR . '*.php');
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                $name = pathinfo($entry, PATHINFO_FILENAME);
                $name = $this->normalizeFile((string) $name);
                if ($name === '') {
                    continue;
                }
                $files[$name] = true;
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }
}
