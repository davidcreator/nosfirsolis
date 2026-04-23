<?php

namespace Install\Model;

use PDO;
use PDOException;
use RuntimeException;
use System\Engine\Model;
use System\Library\HostGuard;

class InstallerModel extends Model
{
    public function environmentReport(): array
    {
        $storagePath = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage';

        return [
            'php_version' => [
                'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
                'current' => PHP_VERSION,
                'required' => '8.1+',
            ],
            'pdo_mysql' => [
                'ok' => extension_loaded('pdo_mysql'),
                'current' => extension_loaded('pdo_mysql') ? 'enabled' : 'disabled',
                'required' => 'enabled',
            ],
            'mbstring' => [
                'ok' => extension_loaded('mbstring'),
                'current' => extension_loaded('mbstring') ? 'enabled' : 'disabled',
                'required' => 'enabled',
            ],
            'json' => [
                'ok' => extension_loaded('json'),
                'current' => extension_loaded('json') ? 'enabled' : 'disabled',
                'required' => 'enabled',
            ],
            'openssl' => [
                'ok' => extension_loaded('openssl'),
                'current' => extension_loaded('openssl') ? 'enabled' : 'disabled',
                'required' => 'enabled',
            ],
            'storage_writable' => [
                'ok' => is_dir($storagePath) && is_writable($storagePath),
                'current' => is_dir($storagePath) && is_writable($storagePath) ? 'writable' : 'not writable',
                'required' => 'writable',
            ],
        ];
    }

    public function install(array $data): array
    {
        try {
            $this->guardAgainstUnauthorizedReinstall($data);
            $this->validateInput($data);
            $report = $this->environmentReport();
            foreach ($report as $item) {
                if (!$item['ok']) {
                    return [
                        'success' => false,
                        'message' => $this->t('install.env_check_failed', 'Falha na verificacao de ambiente.'),
                    ];
                }
            }

            $pdo = $this->connect($data);
            $this->runSqlFile($pdo, DIR_ROOT . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'schema.sql');
            $this->runSqlFile($pdo, DIR_ROOT . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'seed.sql');
            $this->createAdminUser($pdo, $data);
            $this->writeRuntimeConfig($data);

            return [
                'success' => true,
                'message' => $this->t('install.completed_success', 'Instalacao concluida com sucesso.'),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $this->t(
                    'install.error_during_install',
                    'Erro durante instalacao: {error}',
                    ['error' => $exception->getMessage()]
                ),
            ];
        }
    }

    private function validateInput(array $data): void
    {
        $required = [
            'db_host',
            'db_port',
            'db_name',
            'db_user',
            'admin_name',
            'admin_email',
            'admin_password',
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new RuntimeException($this->t(
                    'install.required_field_missing',
                    'Campo obrigatorio ausente: {field}',
                    ['field' => $field]
                ));
            }
        }

        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException($this->t('install.invalid_admin_email', 'Email administrativo invalido.'));
        }

        if (strlen((string) $data['admin_password']) < 8) {
            throw new RuntimeException($this->t('install.admin_password_min_length', 'A senha do administrador deve ter no minimo 8 caracteres.'));
        }
    }

    private function connect(array $data): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $data['db_host'],
            (int) $data['db_port'],
            $data['db_name']
        );

        try {
            return new PDO($dsn, (string) $data['db_user'], (string) ($data['db_pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException($this->t(
                'install.db_connect_error',
                'Nao foi possivel conectar ao banco: {error}',
                ['error' => $exception->getMessage()]
            ));
        }
    }

    private function runSqlFile(PDO $pdo, string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException($this->t(
                'install.sql_file_not_found',
                'Arquivo SQL nao encontrado: {file}',
                ['file' => $file]
            ));
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException($this->t(
                'install.sql_file_read_error',
                'Falha ao ler arquivo SQL: {file}',
                ['file' => $file]
            ));
        }

        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $lines = explode("\n", (string) $sql);
        $clean = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $clean[] = $line;
        }

        $statements = preg_split('/;\s*\n/', implode("\n", $clean));
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    private function createAdminUser(PDO $pdo, array $data): void
    {
        $groupId = $this->resolveAdminGroup($pdo);

        $sql = 'INSERT INTO users (user_group_id, name, email, password_hash, language_code, status, created_at, updated_at)
                VALUES (:user_group_id, :name, :email, :password_hash, :language_code, 1, NOW(), NOW())';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_group_id' => $groupId,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password_hash' => password_hash((string) $data['admin_password'], PASSWORD_DEFAULT),
            'language_code' => $this->normalizeLanguageCode((string) ($data['language_code'] ?? 'en-us')),
        ]);
    }

    private function resolveAdminGroup(PDO $pdo): int
    {
        $row = $pdo->query("SELECT id FROM user_groups WHERE name = 'Administradores' LIMIT 1")->fetch();
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }

        $stmt = $pdo->prepare('INSERT INTO user_groups (name, hierarchy_level, permissions_json, created_at, updated_at) VALUES (:name, :hierarchy_level, :permissions_json, NOW(), NOW())');
        $stmt->execute([
            'name' => 'Administradores',
            'hierarchy_level' => 10,
            'permissions_json' => json_encode(['*']),
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function writeRuntimeConfig(array $data): void
    {
        $baseUrl = $this->buildBaseUrl();
        $reinstallKey = bin2hex(random_bytes(32));
        $runtimeConfig = [
            'app' => [
                'name' => 'Solis',
                'base_url' => $baseUrl,
                'installed' => true,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                'default_language' => $data['language_code'] ?? 'en-us',
            ],
            'database' => [
                'driver' => 'mysql',
                'host' => $data['db_host'],
                'port' => (int) $data['db_port'],
                'database' => $data['db_name'],
                'username' => $data['db_user'],
                'password' => $data['db_pass'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'security' => [
                'allow_reinstall' => false,
                'reinstall_key' => $reinstallKey,
                'reinstall_permission' => 'admin.install.reinstall',
            ],
        ];

        $this->writeRootConfig($runtimeConfig);
        $this->writeAdminConfig($baseUrl);
        $this->writeStorageConfig($runtimeConfig);
    }

    private function writeRootConfig(array $config): void
    {
        $target = DIR_ROOT . DIRECTORY_SEPARATOR . 'config.php';
        $content = "<?php\n\n"
            . "if (!defined('DIR_ROOT')) {\n    define('DIR_ROOT', __DIR__);\n}\n\n"
            . "if (!defined('DIR_SYSTEM')) {\n    define('DIR_SYSTEM', DIR_ROOT . DIRECTORY_SEPARATOR . 'system');\n}\n\n"
            . "if (!defined('DIR_STORAGE')) {\n    define('DIR_STORAGE', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage');\n}\n\n"
            . "if (!defined('DIR_ADMIN')) {\n    define('DIR_ADMIN', DIR_ROOT . DIRECTORY_SEPARATOR . 'admin');\n}\n\n"
            . "if (!defined('DIR_CLIENT')) {\n    define('DIR_CLIENT', DIR_ROOT . DIRECTORY_SEPARATOR . 'client');\n}\n\n"
            . "if (!defined('DIR_INSTALL')) {\n    define('DIR_INSTALL', DIR_ROOT . DIRECTORY_SEPARATOR . 'install');\n}\n\n"
            . 'return ' . var_export($config, true) . ";\n";

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException($this->t('install.write_root_config_error', 'Falha ao gravar configuracao em config.php'));
        }
    }

    private function writeAdminConfig(string $baseUrl): void
    {
        $target = DIR_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'config.php';
        $adminConfig = [
            'admin' => [
                'name' => 'Painel Administrativo',
                'base_url' => rtrim($baseUrl, '/') . '/admin/',
                'reinstall_permission' => 'admin.install.reinstall',
            ],
            'routes' => [
                'public_routes' => [
                    'auth/login',
                    'auth/authenticate',
                ],
                'login_redirect' => 'auth/login',
            ],
        ];

        $content = "<?php\n\n"
            . "if (!defined('DIR_ADMIN')) {\n    define('DIR_ADMIN', __DIR__);\n}\n\n"
            . "if (!defined('DIR_ROOT')) {\n    define('DIR_ROOT', dirname(__DIR__));\n}\n\n"
            . 'return ' . var_export($adminConfig, true) . ";\n";

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException($this->t('install.write_admin_config_error', 'Falha ao gravar configuracao em admin/config.php'));
        }
    }

    private function writeStorageConfig(array $config): void
    {
        $target = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php';
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException($this->t('install.write_storage_config_error', 'Falha ao gravar configuracao em system/Storage/config.php'));
        }
    }

    private function buildBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = HostGuard::effectiveHost(
            $_SERVER,
            (array) $this->config->get('security.allowed_hosts', []),
            (string) $this->config->get('app.base_url', '')
        );
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/install$#', '', rtrim($scriptDir, '/'));

        return rtrim($scheme . '://' . $host . $rootDir, '/') . '/';
    }

    private function guardAgainstUnauthorizedReinstall(array $data): void
    {
        $installed = (bool) $this->config->get('app.installed', false);
        $allowed = !empty($data['allow_reinstall']) && (bool) $data['allow_reinstall'];

        if ($installed && !$allowed) {
            throw new RuntimeException($this->t('install.reinstall_not_allowed', 'Sistema ja instalado. Reinstalacao bloqueada sem permissao.'));
        }
    }

    private function t(string $key, string $default, array $replacements = []): string
    {
        $language = $this->language ?? null;
        $text = is_object($language) && method_exists($language, 'get')
            ? (string) $language->get($key, $default)
            : $default;

        if ($replacements === []) {
            return $text;
        }

        $tokens = [];
        foreach ($replacements as $name => $value) {
            $tokens['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $tokens);
    }

    private function normalizeLanguageCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = str_replace('_', '-', $code);

        return in_array($code, ['en-us', 'pt-br'], true) ? $code : 'en-us';
    }
}
