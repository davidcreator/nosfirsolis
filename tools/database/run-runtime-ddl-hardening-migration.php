<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

require_once DIR_ROOT . '/system/Engine/Startup.php';

use System\Engine\Config;
use System\Library\Database;

final class RuntimeDdlHardeningMigration
{
    private int $applied = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function run(): int
    {
        $db = $this->connectDatabase();
        if (!$db->connected()) {
            $error = $db->connectionError() ?? 'erro desconhecido';
            $this->log('FAIL', 'Conexao com banco indisponivel: ' . $error);
            return 1;
        }

        $this->ensureUsersLanguageCode($db);
        $this->ensureUserGroupsHierarchyLevel($db);
        $this->ensureCalendarExtraEvents($db);
        $this->ensureUserCalendarColors($db);

        $this->printSummary();

        return $this->failed > 0 ? 1 : 0;
    }

    private function connectDatabase(): Database
    {
        $config = new Config();
        $config->load('app', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Config');
        $config->load('database', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Config');

        $rootConfigFile = DIR_ROOT . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($rootConfigFile)) {
            $rootConfig = require $rootConfigFile;
            if (is_array($rootConfig)) {
                $config->mergeConfig($rootConfig);
            }
        }

        $storageConfigFile = $this->resolveStorageConfigFile();
        if ($storageConfigFile !== null && is_file($storageConfigFile)) {
            $storageConfig = require $storageConfigFile;
            if (is_array($storageConfig)) {
                $config->mergeConfig($storageConfig);
            }
        }

        $this->applyDbEnvironmentOverrides($config);

        return new Database((array) $config->get('database', []));
    }

    private function ensureUsersLanguageCode(Database $db): void
    {
        if ($this->columnExists($db, 'users', 'language_code')) {
            $this->log('SKIP', 'users.language_code ja existe.');
            $this->skipped++;
            return;
        }

        $sql = "ALTER TABLE users ADD COLUMN language_code VARCHAR(10) NOT NULL DEFAULT 'en-us' AFTER avatar";
        $this->execute($db, $sql, 'users.language_code adicionado.');
    }

    private function ensureUserGroupsHierarchyLevel(Database $db): void
    {
        if (!$this->columnExists($db, 'user_groups', 'hierarchy_level')) {
            $sql = 'ALTER TABLE user_groups ADD COLUMN hierarchy_level INT UNSIGNED NOT NULL DEFAULT 50 AFTER description';
            $this->execute($db, $sql, 'user_groups.hierarchy_level adicionado.');
        } else {
            $this->log('SKIP', 'user_groups.hierarchy_level ja existe.');
            $this->skipped++;
        }

        $this->execute(
            $db,
            "UPDATE user_groups
             SET hierarchy_level = 10
             WHERE LOWER(name) = 'administradores'
               AND hierarchy_level = 50",
            'Hierarquia do grupo Administradores normalizada.'
        );

        $this->execute(
            $db,
            "UPDATE user_groups
             SET hierarchy_level = 90
             WHERE LOWER(name) = 'clientes'
               AND hierarchy_level = 50",
            'Hierarquia do grupo Clientes normalizada.'
        );
    }

    private function ensureCalendarExtraEvents(Database $db): void
    {
        if (!$this->tableExists($db, 'calendar_extra_events')) {
            $sql = "CREATE TABLE calendar_extra_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                event_date DATE NOT NULL,
                title VARCHAR(190) NOT NULL,
                event_type VARCHAR(80) NOT NULL DEFAULT 'extra',
                description TEXT NULL,
                color_hex VARCHAR(7) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_calendar_extra_user_date (user_id, event_date),
                CONSTRAINT fk_calendar_extra_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->execute($db, $sql, 'Tabela calendar_extra_events criada.');
            return;
        }

        if ($this->columnExists($db, 'calendar_extra_events', 'color_hex')) {
            $this->log('SKIP', 'calendar_extra_events.color_hex ja existe.');
            $this->skipped++;
            return;
        }

        $sql = 'ALTER TABLE calendar_extra_events ADD COLUMN color_hex VARCHAR(7) NULL AFTER description';
        $this->execute($db, $sql, 'calendar_extra_events.color_hex adicionado.');
    }

    private function ensureUserCalendarColors(Database $db): void
    {
        if ($this->tableExists($db, 'user_calendar_colors')) {
            $this->log('SKIP', 'Tabela user_calendar_colors ja existe.');
            $this->skipped++;
            return;
        }

        $sql = "CREATE TABLE user_calendar_colors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            color_key VARCHAR(80) NOT NULL,
            color_hex VARCHAR(7) NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY ux_user_color (user_id, color_key),
            CONSTRAINT fk_user_calendar_colors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->execute($db, $sql, 'Tabela user_calendar_colors criada.');
    }

    private function execute(Database $db, string $sql, string $successMessage): void
    {
        try {
            $db->execute($sql);
            $this->log('APPLY', $successMessage);
            $this->applied++;
        } catch (\Throwable $exception) {
            $this->log('FAIL', $successMessage . ' Erro: ' . $exception->getMessage());
            $this->failed++;
        }
    }

    private function tableExists(Database $db, string $table): bool
    {
        if (!$this->isSafeIdentifier($table)) {
            return false;
        }

        return (bool) $db->fetch("SHOW TABLES LIKE '{$table}'");
    }

    private function columnExists(Database $db, string $table, string $column): bool
    {
        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($column)) {
            return false;
        }

        return (bool) $db->fetch("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    }

    private function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $value) === 1;
    }

    private function resolveStorageConfigFile(): ?string
    {
        $candidates = [
            DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php',
            DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config.php',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function applyDbEnvironmentOverrides(Config $config): void
    {
        $map = [
            'host' => ['DB_HOST', 'NOSFIRSOLIS_DB_HOST'],
            'port' => ['DB_PORT', 'NOSFIRSOLIS_DB_PORT'],
            'database' => ['DB_DATABASE', 'NOSFIRSOLIS_DB_DATABASE'],
            'username' => ['DB_USERNAME', 'NOSFIRSOLIS_DB_USERNAME'],
            'password' => ['DB_PASSWORD', 'NOSFIRSOLIS_DB_PASSWORD'],
            'charset' => ['DB_CHARSET', 'NOSFIRSOLIS_DB_CHARSET'],
            'collation' => ['DB_COLLATION', 'NOSFIRSOLIS_DB_COLLATION'],
        ];

        foreach ($map as $field => $keys) {
            $value = $this->envFirst($keys);
            if ($value === null) {
                continue;
            }

            if ($field === 'port') {
                if (!ctype_digit($value)) {
                    continue;
                }

                $config->set('database.port', (int) $value);
                continue;
            }

            $config->set('database.' . $field, $value);
        }
    }

    private function envFirst(array $keys): ?string
    {
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if (isset($_ENV[$key]) && is_string($_ENV[$key]) && trim($_ENV[$key]) !== '') {
                return trim($_ENV[$key]);
            }

            if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && trim($_SERVER[$key]) !== '') {
                return trim($_SERVER[$key]);
            }

            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function log(string $level, string $message): void
    {
        echo '[' . $level . '] ' . $message . PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo '--- Runtime DDL Hardening Migration Summary ---' . PHP_EOL;
        echo 'Applied: ' . $this->applied . PHP_EOL;
        echo 'Skipped: ' . $this->skipped . PHP_EOL;
        echo 'Failed: ' . $this->failed . PHP_EOL;
        echo 'Status: ' . ($this->failed > 0 ? 'FAIL' : 'PASS') . PHP_EOL;
    }
}

$migration = new RuntimeDdlHardeningMigration();
exit($migration->run());
