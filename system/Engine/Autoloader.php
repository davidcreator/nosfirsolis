<?php

namespace System\Engine;

class Autoloader
{
    private array $prefixes = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $this->prefixes[$prefix][] = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function loadClass(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDirs) {
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            foreach ($baseDirs as $baseDir) {
                $file = $baseDir . $relativePath;
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }
}
