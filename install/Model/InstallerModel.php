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
        $storageUpper = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage';
        $storageLower = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage';
        $storagePath = is_dir($storageUpper) ? $storageUpper : (is_dir($storageLower) ? $storageLower : $storageUpper);

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
                    'Erro durante a instalacao: {error}',
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
            throw new RuntimeException($this->t('install.invalid_admin_email', 'E-mail administrativo invalido.'));
        }

        if (strlen((string) $data['admin_password']) < 8) {
            throw new RuntimeException($this->t('install.admin_password_min_length', 'A senha do administrador deve ter no minimo 8 caracteres.'));
        }

        $environment = $this->normalizeEnvironment((string) ($data['app_env'] ?? ''));
        if ($environment === '') {
            throw new RuntimeException($this->t('install.invalid_environment', 'Ambiente de execucao invalido.'));
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
        $environment = $this->normalizeEnvironment((string) ($data['app_env'] ?? ''));
        if ($environment === '') {
            $environment = 'development';
        }

        $allowedHosts = $this->resolveAllowedHosts((string) ($data['allowed_hosts'] ?? ''), $baseUrl, $environment);
        $reinstallKey = bin2hex(random_bytes(32));

        $runtimeConfig = [
            'app' => [
                'name' => 'Solis',
                'environment' => $environment,
                'base_url' => $baseUrl,
                'installed' => true,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                'default_language' => $this->normalizeLanguageCode((string) ($data['language_code'] ?? 'en-us')),
                'session_name' => 'nsplanner_session',
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
                'allowed_hosts' => $allowedHosts,
            ],
        ];

        $this->writeAdminConfig($baseUrl);
        $this->writeStorageConfig($runtimeConfig);
        $this->writeEnvConfig($environment, $allowedHosts);
    }

    private function writeEnvConfig(string $environment, array $allowedHosts): void
    {
        $target = DIR_ROOT . DIRECTORY_SEPARATOR . '.env';
        $env = $this->parseEnvFile($target);

        $tokenCipherKey = trim((string) ($env['TOKEN_CIPHER_KEY'] ?? ''));
        if ($tokenCipherKey === '') {
            $tokenCipherKey = bin2hex(random_bytes(48));
        }

        $trustedProxies = trim((string) ($env['TRUSTED_PROXIES'] ?? ''));
        if ($trustedProxies === '') {
            $trustedProxies = '127.0.0.1,::1';
        }

        $allowPrivateWebhookEndpoints = trim((string) ($env['AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS'] ?? ''));
        if ($allowPrivateWebhookEndpoints === '') {
            $allowPrivateWebhookEndpoints = '0';
        }

        $env['APP_ENV'] = $environment;
        $env['TOKEN_CIPHER_KEY'] = $tokenCipherKey;
        if (!array_key_exists('TOKEN_CIPHER_KEY_PREVIOUS', $env)) {
            $env['TOKEN_CIPHER_KEY_PREVIOUS'] = '';
        }
        $env['TRUSTED_PROXIES'] = $trustedProxies;
        $env['ALLOWED_HOSTS'] = implode(',', $allowedHosts);
        $env['AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS'] = $allowPrivateWebhookEndpoints;

        $preferredOrder = [
            'APP_ENV',
            'TOKEN_CIPHER_KEY',
            'TOKEN_CIPHER_KEY_PREVIOUS',
            'TRUSTED_PROXIES',
            'ALLOWED_HOSTS',
            'AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS',
        ];

        $lines = [
            '# Managed by Solis installer',
            '# Update credentials/secrets as needed for your environment.',
        ];

        $written = [];
        foreach ($preferredOrder as $key) {
            if (!array_key_exists($key, $env)) {
                continue;
            }

            $lines[] = $key . '=' . $this->formatEnvValue((string) $env[$key]);
            $written[$key] = true;
        }

        foreach ($env as $key => $value) {
            if (isset($written[$key])) {
                continue;
            }

            $lines[] = $key . '=' . $this->formatEnvValue((string) $value);
        }

        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException($this->t('install.write_env_config_error', 'Falha ao gravar configuracao em .env'));
        }
    }

    private function parseEnvFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separatorPos = strpos($line, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPos));
            if ($key === '') {
                continue;
            }

            $value = trim(substr($line, $separatorPos + 1));
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $value) === 1) {
            return '"' . addcslashes($value, "\\\"") . '"';
        }

        return $value;
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
        $storageUpper = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage';
        $storageLower = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage';
        $storageDir = is_dir($storageUpper) ? $storageUpper : (is_dir($storageLower) ? $storageLower : $storageUpper);
        $target = $storageDir . DIRECTORY_SEPARATOR . 'config.php';
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException($this->t('install.write_storage_config_error', 'Falha ao gravar configuracao em system/Storage/config.php'));
        }
    }

    private function buildBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $requestHost = HostGuard::requestHost($_SERVER);
        $host = $requestHost !== ''
            ? $requestHost
            : HostGuard::effectiveHost(
                $_SERVER,
                (array) $this->config->get('security.allowed_hosts', []),
                (string) $this->config->get('app.base_url', '')
            );
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/install$#', '', rtrim($scriptDir, '/'));

        return rtrim($scheme . '://' . $host . $rootDir, '/') . '/';
    }

    private function resolveAllowedHosts(string $rawAllowedHosts, string $baseUrl, string $environment): array
    {
        $allowedHosts = $this->parseAllowedHosts($rawAllowedHosts);
        $baseHost = HostGuard::baseUrlHost($baseUrl);
        $requestHost = HostGuard::requestHost($_SERVER);

        if ($baseHost !== '') {
            $allowedHosts[] = $baseHost;
        }

        if ($environment === 'development') {
            $allowedHosts[] = 'localhost';
            $allowedHosts[] = '127.0.0.1';
            $allowedHosts[] = '::1';
        }

        if ($requestHost !== '') {
            if ($environment === 'development' || $allowedHosts === []) {
                $allowedHosts[] = $requestHost;
            }
        }

        $normalized = [];
        foreach ($allowedHosts as $allowedHost) {
            $host = HostGuard::normalizeHost($allowedHost);
            if ($host !== '') {
                $normalized[$host] = true;
            }
        }

        return array_keys($normalized);
    }

    private function parseAllowedHosts(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,\n\r;]+/', $value);
        if (!is_array($parts)) {
            return [];
        }

        $result = [];
        foreach ($parts as $part) {
            $host = trim($part);
            if ($host !== '') {
                $result[] = $host;
            }
        }

        return $result;
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

    private function normalizeEnvironment(string $value): string
    {
        $value = strtolower(trim($value));

        if (in_array($value, ['dev', 'development', 'local', 'localhost'], true)) {
            return 'development';
        }

        if (in_array($value, ['prod', 'production', 'live'], true)) {
            return 'production';
        }

        return '';
    }
}


