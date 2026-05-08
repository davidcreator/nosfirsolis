<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', DIR_ROOT . DIRECTORY_SEPARATOR . 'system');
}

require_once DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Engine' . DIRECTORY_SEPARATOR . 'Startup.php';

use System\Engine\Config;
use System\Library\Database;

final class OperationalSecurityAudit
{
    private int $passes = 0;
    private array $warnings = [];
    private array $failures = [];
    private bool $isProduction = false;
    private bool $runtimeSchemaMutations = false;

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function run(): int
    {
        $this->checkConfiguration();
        $this->checkDatabaseReadiness();
        $this->printSummary();

        return empty($this->failures) ? 0 : 1;
    }

    private function checkConfiguration(): void
    {
        $environment = strtolower(trim((string) $this->config->get('app.environment', 'production')));
        $this->isProduction = in_array($environment, ['production', 'prod', 'live'], true);

        $this->applyProductionGuardrails();
        $this->runtimeSchemaMutations = $this->toBool($this->config->get('security.runtime_schema_mutations', false));

        $tokenKey = trim((string) $this->config->get('security.token_cipher_key', ''));
        if ($tokenKey === '') {
            $this->fail('security.token_cipher_key vazio.');
        } elseif (strlen($tokenKey) < 32) {
            $this->warn('security.token_cipher_key com menos de 32 caracteres.');
        } elseif (str_contains(strtolower($tokenKey), 'change_me')) {
            $this->fail('security.token_cipher_key parece placeholder.');
        } else {
            $this->pass('security.token_cipher_key definido.');
        }

        $allowedHosts = $this->normalizeHostList((array) $this->config->get('security.allowed_hosts', []));
        if ($this->isProduction) {
            if ($allowedHosts === []) {
                $this->fail('security.allowed_hosts vazio em producao.');
            } elseif (in_array('*', $allowedHosts, true)) {
                $this->fail('security.allowed_hosts contem wildcard em producao.');
            } else {
                $localDefaults = ['localhost', '127.0.0.1', '::1'];
                $nonLocal = array_values(array_diff($allowedHosts, $localDefaults));
                if ($nonLocal === []) {
                    $this->fail('security.allowed_hosts em producao contem apenas hosts locais.');
                } else {
                    $this->pass('security.allowed_hosts configurado para producao.');
                }
            }
        } elseif ($allowedHosts === []) {
            $this->warn('security.allowed_hosts vazio fora de producao.');
        } else {
            $this->pass('security.allowed_hosts definido para ambiente atual.');
        }

        $compatibilityMode = $this->toBool($this->config->get('security.host_guard_compatibility_mode', false));
        if ($this->isProduction && $compatibilityMode) {
            $this->fail('security.host_guard_compatibility_mode=true em producao.');
        } else {
            $this->pass('HostGuard compatibility mode coerente com ambiente.');
        }

        $failOpenAuth = $this->toBool($this->config->get('security.auth.fail_open_on_security_error', false));
        if ($this->isProduction && $failOpenAuth) {
            $this->fail('security.auth.fail_open_on_security_error=true em producao.');
        } else {
            $this->pass('Politica de auth fail-open/fail-closed coerente.');
        }

        if ($this->isProduction && $this->runtimeSchemaMutations) {
            $this->fail('security.runtime_schema_mutations=true em producao.');
        } else {
            $this->pass('Politica de runtime schema mutations coerente.');
        }

        $headersEnabled = $this->toBool($this->config->get('security.headers.enabled', true));
        if ($this->isProduction && !$headersEnabled) {
            $this->fail('security.headers.enabled=false em producao.');
        } else {
            $this->pass('Headers de seguranca habilitados para ambiente atual.');
        }

        $csp = trim((string) $this->config->get('security.headers.content_security_policy', ''));
        if ($csp === '') {
            if ($this->isProduction) {
                $this->fail('Content-Security-Policy vazia em producao.');
            } else {
                $this->warn('Content-Security-Policy vazia fora de producao.');
            }
        } else {
            $this->pass('Content-Security-Policy definida.');
            if (str_contains(strtolower($csp), "'unsafe-eval'")) {
                $this->warn("CSP contem 'unsafe-eval'. Revisar necessidade.");
            }
        }

        $hstsEnabled = $this->toBool($this->config->get('security.headers.hsts.enabled', false));
        if ($this->isProduction && !$hstsEnabled) {
            $this->warn('HSTS desabilitado em producao.');
        } else {
            $this->pass('Politica HSTS coerente com ambiente atual.');
        }

        $hstsMaxAge = (int) $this->config->get('security.headers.hsts.max_age', 31536000);
        if ($hstsEnabled && $hstsMaxAge < 15552000) {
            $this->warn('HSTS max-age abaixo de 180 dias.');
        }

        $hstsPreload = $this->toBool($this->config->get('security.headers.hsts.preload', false));
        $hstsIncludeSubdomains = $this->toBool($this->config->get('security.headers.hsts.include_subdomains', true));
        if ($hstsPreload && !$hstsIncludeSubdomains) {
            $this->warn('HSTS preload=true sem includeSubDomains=true.');
        }

        $allowPrivateWebhooks = $this->toBool($this->config->get('security.automation.allow_private_webhook_endpoints', false));
        if ($this->isProduction && $allowPrivateWebhooks) {
            $this->fail('security.automation.allow_private_webhook_endpoints=true em producao.');
        } elseif ($allowPrivateWebhooks) {
            $this->warn('Endpoints privados de webhook liberados fora de producao.');
        } else {
            $this->pass('Bloqueio de endpoints privados de webhook habilitado.');
        }

        $trustedProxies = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) $this->config->get('security.trusted_proxies', [])
        ), static fn (string $value): bool => $value !== ''));
        if ($trustedProxies === []) {
            $this->warn('security.trusted_proxies vazio.');
        } else {
            $this->pass('security.trusted_proxies configurado.');
        }

        $this->checkMailConfiguration();

        $this->inspectRuntimeStorageConfig();
    }

    private function checkMailConfiguration(): void
    {
        $mail = (array) $this->config->get('security.mail', []);
        $driver = strtolower(trim((string) ($mail['driver'] ?? 'php_mail')));
        if (!in_array($driver, ['php_mail', 'smtp'], true)) {
            $this->fail('security.mail.driver invalido. Use php_mail ou smtp.');
            return;
        }

        if ($driver === 'php_mail') {
            if ($this->isProduction) {
                $this->warn('security.mail.driver=php_mail em producao. Considere SMTP autenticado para maior confiabilidade.');
            } else {
                $this->pass('security.mail.driver definido como php_mail para ambiente atual.');
            }
            return;
        }

        $smtp = (array) ($mail['smtp'] ?? []);
        $host = trim((string) ($smtp['host'] ?? ''));
        if ($host === '') {
            $this->fail('security.mail.smtp.host vazio com driver SMTP.');
            return;
        }

        $port = (int) ($smtp['port'] ?? 0);
        if ($port <= 0 || $port > 65535) {
            $this->fail('security.mail.smtp.port invalido com driver SMTP.');
            return;
        }

        $authEnabled = $this->toBool($smtp['auth'] ?? true);
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = trim((string) ($smtp['password'] ?? ''));
        if ($authEnabled && ($username === '' || $password === '')) {
            $this->fail('SMTP autenticado requer username/password.');
            return;
        }

        $encryption = strtolower(trim((string) ($smtp['encryption'] ?? 'tls')));
        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            $this->fail('security.mail.smtp.encryption invalido. Use none|tls|ssl.');
            return;
        }

        $this->pass('Configuracao de mail SMTP valida para ambiente atual.');
    }

    private function checkDatabaseReadiness(): void
    {
        $database = (array) $this->config->get('database', []);
        $requiredDatabaseKeys = ['host', 'database', 'username'];
        foreach ($requiredDatabaseKeys as $key) {
            if (trim((string) ($database[$key] ?? '')) === '') {
                $this->fail('Configuracao de banco incompleta: campo ausente -> ' . $key);
                return;
            }
        }

        $db = new Database($database);
        if (!$db->connected()) {
            $error = trim((string) ($db->connectionError() ?? 'erro desconhecido'));
            $this->fail('Conexao com banco indisponivel para auditoria operacional.', $error !== '' ? $error : null);
            return;
        }

        $this->pass('Conexao com banco estabelecida.');

        $coreTables = [
            'users',
            'user_groups',
            'password_resets',
            'auth_recovery_requests',
            'security_login_attempts',
            'security_audit_logs',
            'settings',
            'languages',
        ];

        $missingCore = $this->missingTables($db, $coreTables);
        if ($missingCore !== []) {
            $this->fail('Tabelas centrais ausentes.', implode(', ', $missingCore));
        } else {
            $this->pass('Tabelas centrais presentes.');
        }

        if (!$this->columnExists($db, 'user_groups', 'hierarchy_level')) {
            $this->warn('Coluna user_groups.hierarchy_level ausente.');
        } else {
            $this->pass('Coluna user_groups.hierarchy_level presente.');
        }

        $runtimeManagedTables = [
            'subscription_plans',
            'plan_limits',
            'user_subscriptions',
            'billing_invoices',
            'payment_transactions',
            'subscription_events',
            'billing_promotions',
            'billing_announcements',
            'user_feature_overrides',
            'social_connections',
            'social_content_drafts',
            'social_format_presets',
            'social_publications',
            'social_publication_logs',
            'campaign_tracking_links',
            'feature_flags',
            'automations_webhooks',
            'automation_dispatch_logs',
            'observability_events',
            'observability_spans',
            'job_monitors',
            'job_checkins',
            'job_alerts',
            'calendar_extra_events',
            'user_calendar_colors',
        ];

        $missingRuntimeManaged = $this->missingTables($db, $runtimeManagedTables);
        if ($missingRuntimeManaged !== []) {
            $details = implode(', ', $missingRuntimeManaged);
            if ($this->runtimeSchemaMutations && !$this->isProduction) {
                $this->warn('Tabelas operacionais ausentes (runtime schema mutacoes ativo em ambiente nao-producao).', $details);
            } elseif ($this->isProduction || !$this->runtimeSchemaMutations) {
                $this->fail('Tabelas operacionais ausentes com politica atual de schema runtime.', $details);
            } else {
                $this->warn('Tabelas operacionais ausentes.', $details);
            }
        } else {
            $this->pass('Tabelas operacionais de servicos presentes.');
        }

        $this->checkDatabaseGrants($db);
    }

    private function checkDatabaseGrants(Database $db): void
    {
        try {
            $rows = $db->fetchAll('SHOW GRANTS FOR CURRENT_USER');
        } catch (\Throwable) {
            $this->warn('Nao foi possivel ler grants da conexao atual.');
            return;
        }

        if ($rows === []) {
            $this->warn('SHOW GRANTS nao retornou linhas.');
            return;
        }

        $lines = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $lines[] = trim($value);
                }
            }
        }

        if ($lines === []) {
            $this->warn('Grants vazios para usuario atual.');
            return;
        }

        $combined = strtolower(implode(' | ', $lines));
        $hasDdlPrivilege = preg_match('/\ball privileges\b|\bcreate\b|\balter\b|\bdrop\b/', $combined) === 1;

        if ($hasDdlPrivilege && !$this->runtimeSchemaMutations) {
            if ($this->isProduction) {
                $this->warn('Conta de aplicacao aparenta ter privilegios DDL mesmo com runtime_schema_mutations=false.');
            } else {
                $this->warn('Privilegios DDL detectados na conta de aplicacao em ambiente nao-producao.');
            }
            return;
        }

        $this->pass('Grants da conta de aplicacao sem indicio de DDL incompativel com politica atual.');
    }

    private function missingTables(Database $db, array $tables): array
    {
        $missing = [];
        foreach ($tables as $table) {
            if (!$this->tableExists($db, $table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    private function tableExists(Database $db, string $table): bool
    {
        $row = $db->fetch(
            'SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1',
            ['table_name' => $table]
        );

        return $row !== null;
    }

    private function columnExists(Database $db, string $table, string $column): bool
    {
        $row = $db->fetch(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1',
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        );

        return $row !== null;
    }

    private function normalizeHostList(array $hosts): array
    {
        $normalized = [];
        foreach ($hosts as $host) {
            $value = strtolower(trim((string) $host));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function pass(string $message): void
    {
        $this->passes++;
        echo '[PASS] ' . $message . PHP_EOL;
    }

    private function warn(string $message, ?string $details = null): void
    {
        $this->warnings[] = [$message, $details];
        echo '[WARN] ' . $message;
        if (is_string($details) && $details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function fail(string $message, ?string $details = null): void
    {
        $this->failures[] = [$message, $details];
        echo '[FAIL] ' . $message;
        if (is_string($details) && $details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo '--- Operational Security Audit Summary ---' . PHP_EOL;
        echo 'Passes: ' . $this->passes . PHP_EOL;
        echo 'Warnings: ' . count($this->warnings) . PHP_EOL;
        echo 'Failures: ' . count($this->failures) . PHP_EOL;

        if (!empty($this->failures)) {
            echo 'Status: FAIL' . PHP_EOL;
            return;
        }

        if (!empty($this->warnings)) {
            echo 'Status: PASS_WITH_WARNINGS' . PHP_EOL;
            return;
        }

        echo 'Status: PASS' . PHP_EOL;
    }

    private function applyProductionGuardrails(): void
    {
        if (!$this->isProduction) {
            return;
        }

        $guardedKeys = [
            'security.host_guard_compatibility_mode',
            'security.automation.allow_private_webhook_endpoints',
            'security.auth.fail_open_on_security_error',
            'security.runtime_schema_mutations',
        ];

        foreach ($guardedKeys as $key) {
            if (!$this->toBool($this->config->get($key, false))) {
                continue;
            }

            $this->warn($key . '=true detectado em producao. Valor efetivo sera forçado para false em runtime.');
            $this->config->set($key, false);
        }
    }

    private function inspectRuntimeStorageConfig(): void
    {
        $candidates = [
            DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php',
            DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config.php',
        ];

        $file = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $file = $candidate;
                break;
            }
        }

        if ($file === null) {
            $this->pass('Nenhum runtime config storage detectado no host atual.');
            return;
        }

        $runtime = require $file;
        if (!is_array($runtime)) {
            $this->warn('Arquivo de runtime config existe, mas nao retornou array valido.', $file);
            return;
        }

        $runtimeEnv = strtolower(trim((string) ($runtime['app']['environment'] ?? '')));
        $runtimeDbPassword = trim((string) ($runtime['database']['password'] ?? ''));
        $runtimeDbUser = trim((string) ($runtime['database']['username'] ?? ''));
        $runtimeDbName = trim((string) ($runtime['database']['database'] ?? ''));

        if ($runtimeDbPassword !== '' && stripos($runtimeDbPassword, 'change_me') === false) {
            $this->warn(
                'Runtime config em arquivo contem credencial de banco em texto claro. Preferir segredo via ambiente/cofre.',
                $file
            );
        } else {
            $this->pass('Runtime config sem senha explicita de banco em arquivo (ou usando placeholder).');
        }

        $hostEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if ($hostEnv === 'development' && $runtimeEnv === 'production') {
            $this->warn('APP_ENV=development mas runtime storage config define ambiente production.', $file);
        }

        if ($runtimeDbUser !== '' && $runtimeDbName !== '') {
            $this->pass('Runtime storage config contem identificadores de banco definidos.');
        }
    }
}

function buildRuntimeConfig(): Config
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

    $storageCandidates = [
        DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php',
        DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config.php',
    ];

    foreach ($storageCandidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }

        $storageConfig = require $candidate;
        if (is_array($storageConfig)) {
            $config->mergeConfig($storageConfig);
            break;
        }
    }

    applyAuditEnvironmentOverrides($config);

    return $config;
}

function applyAuditEnvironmentOverrides(Config $config): void
{
    $environment = auditEnvFirst(['APP_ENV', 'NOSFIRSOLIS_APP_ENV']);
    if ($environment !== null) {
        $normalized = strtolower(trim($environment));
        if (in_array($normalized, ['dev', 'development', 'local', 'localhost'], true)) {
            $config->set('app.environment', 'development');
        } elseif (in_array($normalized, ['prod', 'production', 'live'], true)) {
            $config->set('app.environment', 'production');
        }
    }

    $databaseOverrides = [
        'host' => ['DB_HOST', 'NOSFIRSOLIS_DB_HOST'],
        'port' => ['DB_PORT', 'NOSFIRSOLIS_DB_PORT'],
        'database' => ['DB_DATABASE', 'NOSFIRSOLIS_DB_DATABASE'],
        'username' => ['DB_USERNAME', 'NOSFIRSOLIS_DB_USERNAME'],
        'password' => ['DB_PASSWORD', 'NOSFIRSOLIS_DB_PASSWORD'],
        'charset' => ['DB_CHARSET', 'NOSFIRSOLIS_DB_CHARSET'],
        'collation' => ['DB_COLLATION', 'NOSFIRSOLIS_DB_COLLATION'],
    ];

    foreach ($databaseOverrides as $field => $keys) {
        $value = auditEnvFirst($keys);
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

function auditEnvFirst(array $keys): ?string
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

$audit = new OperationalSecurityAudit(buildRuntimeConfig());
exit($audit->run());
