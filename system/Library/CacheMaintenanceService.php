<?php

namespace System\Library;

use System\Engine\Registry;

class CacheMaintenanceService
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function clearStorageCache(): array
    {
        $storageDir = defined('DIR_STORAGE')
            ? (string) DIR_STORAGE
            : (DIR_ROOT . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'Storage');
        $cacheDir = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($cacheDir)) {
            return [
                'files' => 0,
                'directories' => 0,
                'opcache_reset' => $this->resetOpcacheIfAvailable(),
            ];
        }

        $removedFiles = 0;
        $removedDirectories = 0;

        $fileIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($fileIterator as $entry) {
            if (!$entry->isFile() && !$entry->isLink()) {
                continue;
            }

            $basename = strtolower($entry->getBasename());
            if (in_array($basename, ['.htaccess', '.gitignore', 'index.html'], true)) {
                continue;
            }

            if (@unlink($entry->getPathname())) {
                $removedFiles++;
            }
        }

        $directoryIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($directoryIterator as $entry) {
            if (!$entry->isDir()) {
                continue;
            }

            $path = $entry->getPathname();
            if ($path === $cacheDir) {
                continue;
            }

            if (@rmdir($path)) {
                $removedDirectories++;
            }
        }

        return [
            'files' => $removedFiles,
            'directories' => $removedDirectories,
            'opcache_reset' => $this->resetOpcacheIfAvailable(),
        ];
    }

    private function resetOpcacheIfAvailable(): bool
    {
        if (!function_exists('opcache_reset')) {
            return false;
        }

        return (bool) @opcache_reset();
    }
}

