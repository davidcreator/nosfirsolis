<?php

if (!function_exists('nosfir_env_load')) {
    function nosfir_env_load(string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

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

            $existingServer = isset($_SERVER[$key]) && is_string($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : '';
            $existingEnv = isset($_ENV[$key]) && is_string($_ENV[$key]) ? trim((string) $_ENV[$key]) : '';
            $existingGetenvRaw = getenv($key);
            $existingGetenv = is_string($existingGetenvRaw) ? trim($existingGetenvRaw) : '';

            // Production-safe precedence:
            // 1) host/process environment
            // 2) .env file
            $alreadyDefined = ($existingServer !== '' || $existingEnv !== '' || $existingGetenv !== '');
            if ($alreadyDefined) {
                continue;
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }

            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }

            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('nosfir_env_first')) {
    function nosfir_env_first(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $key = trim($key);

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

        return $default;
    }
}

if (!function_exists('nosfir_env_list')) {
    function nosfir_env_list(string $key, array $default = []): array
    {
        $value = nosfir_env_first([$key], '');
        if ($value === '') {
            return $default;
        }

        $parts = explode(',', $value);
        $result = [];

        foreach ($parts as $part) {
            $entry = trim($part);
            if ($entry !== '') {
                $result[] = $entry;
            }
        }

        return empty($result) ? $default : $result;
    }
}

if (!function_exists('nosfir_env_list_first')) {
    function nosfir_env_list_first(array $keys, array $default = []): array
    {
        $value = nosfir_env_first($keys, '');
        if ($value === '') {
            return $default;
        }

        $parts = explode(',', $value);
        $result = [];

        foreach ($parts as $part) {
            $entry = trim($part);
            if ($entry !== '') {
                $result[] = $entry;
            }
        }

        return empty($result) ? $default : $result;
    }
}

if (!function_exists('nosfir_runtime_host')) {
    function nosfir_runtime_host(): string
    {
        $raw = '';
        if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) {
            $raw = $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME']) && is_string($_SERVER['SERVER_NAME'])) {
            $raw = $_SERVER['SERVER_NAME'];
        }

        $host = strtolower(trim($raw));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $parsedHost = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsedHost) ? strtolower(trim($parsedHost)) : '';
        }

        if ($host === '') {
            return '';
        }

        $host = preg_replace('/[\/\?#].*$/', '', $host) ?? $host;
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            $endPos = strpos($host, ']');
            if ($endPos !== false) {
                return substr($host, 1, $endPos - 1);
            }
        }

        if (substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = explode(':', $host, 2);
            if (ctype_digit($candidatePort)) {
                $host = $candidateHost;
            }
        }

        return trim($host);
    }
}

if (!function_exists('nosfir_normalize_environment')) {
    function nosfir_normalize_environment(string $value): string
    {
        $value = strtolower(trim($value));
        $developmentAliases = ['dev', 'development', 'local', 'localhost'];
        $productionAliases = ['prod', 'production', 'live'];

        if (in_array($value, $developmentAliases, true)) {
            return 'development';
        }

        if (in_array($value, $productionAliases, true)) {
            return 'production';
        }

        $runtimeHost = nosfir_runtime_host();
        if ($runtimeHost === '' && PHP_SAPI === 'cli') {
            return 'development';
        }

        $localHosts = ['localhost', '127.0.0.1', '::1'];
        if (
            in_array($runtimeHost, $localHosts, true)
            || str_ends_with($runtimeHost, '.local')
            || str_ends_with($runtimeHost, '.test')
        ) {
            return 'development';
        }

        return 'production';
    }
}

nosfir_env_load(__DIR__ . DIRECTORY_SEPARATOR . '.env');

$appEnvironment = nosfir_normalize_environment(nosfir_env_first([
    'APP_ENV',
    'NOSFIRSOLIS_APP_ENV',
], ''));

$tokenCipherKey = nosfir_env_first([
    'TOKEN_CIPHER_KEY',
    'NOSFIRSOLIS_TOKEN_CIPHER_KEY',
], '');

$tokenCipherKeyPrevious = nosfir_env_list_first([
    'TOKEN_CIPHER_KEY_PREVIOUS',
    'NOSFIRSOLIS_TOKEN_CIPHER_KEY_PREVIOUS',
], []);

$trustedProxies = nosfir_env_list('TRUSTED_PROXIES', ['127.0.0.1', '::1']);
$defaultAllowedHosts = $appEnvironment === 'development'
    ? ['localhost', '127.0.0.1', '::1']
    : [];
$allowedHosts = nosfir_env_list_first([
    'ALLOWED_HOSTS',
    'NOSFIRSOLIS_ALLOWED_HOSTS',
], $defaultAllowedHosts);

$allowPrivateWebhookEndpointsRaw = strtolower(nosfir_env_first([
    'AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS',
    'NOSFIRSOLIS_AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS',
], '0'));

$allowPrivateWebhookEndpoints = in_array($allowPrivateWebhookEndpointsRaw, ['1', 'true', 'yes', 'on'], true);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', __DIR__);
}

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', DIR_ROOT . DIRECTORY_SEPARATOR . 'system');
}

if (!defined('DIR_STORAGE')) {
    $storageUpper = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage';
    $storageLower = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage';
    $storageDir = is_dir($storageUpper) ? $storageUpper : (is_dir($storageLower) ? $storageLower : $storageUpper);

    define('DIR_STORAGE', $storageDir);
}

if (!defined('DIR_ADMIN')) {
    define('DIR_ADMIN', DIR_ROOT . DIRECTORY_SEPARATOR . 'admin');
}

if (!defined('DIR_CLIENT')) {
    define('DIR_CLIENT', DIR_ROOT . DIRECTORY_SEPARATOR . 'client');
}

if (!defined('DIR_INSTALL')) {
    define('DIR_INSTALL', DIR_ROOT . DIRECTORY_SEPARATOR . 'install');
}

return [
    'app' => [
        'name' => 'Solis',
        'environment' => $appEnvironment,
        'base_url' => '',
        'installed' => false,
        'timezone' => 'America/Sao_Paulo',
        'default_language' => 'en-us',
        'session_name' => 'nsplanner_session',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'security' => [
        'allow_reinstall' => false,
        'reinstall_key' => '',
        'reinstall_permission' => 'admin.install.reinstall',
        'token_cipher_key' => $tokenCipherKey,
        'token_cipher_key_previous' => $tokenCipherKeyPrevious,
        'trusted_proxies' => $trustedProxies,
        'allowed_hosts' => $allowedHosts,
        'automation' => [
            'allow_private_webhook_endpoints' => $allowPrivateWebhookEndpoints,
        ],
    ],
];
