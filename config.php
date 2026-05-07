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

if (!function_exists('nosfir_ip_in_cidr')) {
    function nosfir_ip_in_cidr(string $ip, string $cidr): bool
    {
        [$network, $prefixRaw] = array_pad(explode('/', $cidr, 2), 2, '');
        $network = trim($network);
        $prefixRaw = trim($prefixRaw);
        if ($network === '' || $prefixRaw === '' || !ctype_digit($prefixRaw)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($network);
        if ($ipBin === false || $networkBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $prefix = (int) $prefixRaw;
        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($networkBin, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBin[$fullBytes]);
        $networkByte = ord($networkBin[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}

if (!function_exists('nosfir_ip_matches_rule')) {
    function nosfir_ip_matches_rule(string $ip, string $rule): bool
    {
        $ip = strtolower(trim($ip));
        $rule = strtolower(trim($rule));
        if ($ip === '' || $rule === '') {
            return false;
        }

        if (!str_contains($rule, '/')) {
            return hash_equals($rule, $ip);
        }

        return nosfir_ip_in_cidr($ip, $rule);
    }
}

if (!function_exists('nosfir_should_trust_forwarded_headers')) {
    function nosfir_should_trust_forwarded_headers(string $remoteAddr, array $trustedProxies): bool
    {
        $remoteAddr = trim($remoteAddr);
        if ($remoteAddr === '' || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach ($trustedProxies as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            if (nosfir_ip_matches_rule($remoteAddr, $rule)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('nosfir_request_is_https')) {
    function nosfir_request_is_https(array $trustedProxies = []): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
        if ($requestScheme === 'https') {
            return true;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!nosfir_should_trust_forwarded_headers($remoteAddr, $trustedProxies)) {
            return false;
        }

        $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $parts = explode(',', $forwardedProto);
            $proto = strtolower(trim((string) ($parts[0] ?? '')));
            if ($proto === 'https') {
                return true;
            }
        }

        $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
        if (in_array($forwardedSsl, ['1', 'on', 'true', 'yes'], true)) {
            return true;
        }

        $forwarded = trim((string) ($_SERVER['HTTP_FORWARDED'] ?? ''));
        if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)proto=(\"?)(https)\1/i', $forwarded) === 1) {
            return true;
        }

        return false;
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

$hostGuardCompatibilityModeRaw = strtolower(nosfir_env_first([
    'HOST_GUARD_COMPATIBILITY_MODE',
    'NOSFIRSOLIS_HOST_GUARD_COMPATIBILITY_MODE',
], '0'));

$hostGuardCompatibilityMode = in_array($hostGuardCompatibilityModeRaw, ['1', 'true', 'yes', 'on'], true);

$authFailOpenOnSecurityErrorRaw = strtolower(nosfir_env_first([
    'AUTH_FAIL_OPEN_ON_SECURITY_ERROR',
    'NOSFIRSOLIS_AUTH_FAIL_OPEN_ON_SECURITY_ERROR',
], '0'));

$authFailOpenOnSecurityError = in_array($authFailOpenOnSecurityErrorRaw, ['1', 'true', 'yes', 'on'], true);

$securityHeadersEnabledRaw = strtolower(nosfir_env_first([
    'SECURITY_HEADERS_ENABLED',
    'NOSFIRSOLIS_SECURITY_HEADERS_ENABLED',
], '1'));

$securityHeadersEnabled = in_array($securityHeadersEnabledRaw, ['1', 'true', 'yes', 'on'], true);

$cspAllowUnsafeEvalRaw = strtolower(nosfir_env_first([
    'CSP_ALLOW_UNSAFE_EVAL',
    'NOSFIRSOLIS_CSP_ALLOW_UNSAFE_EVAL',
], '0'));

$cspAllowUnsafeEval = in_array($cspAllowUnsafeEvalRaw, ['1', 'true', 'yes', 'on'], true);
$defaultScriptSrc = $cspAllowUnsafeEval
    ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:;"
    : "script-src 'self' 'unsafe-inline' https:;";

$defaultContentSecurityPolicy = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; {$defaultScriptSrc} connect-src 'self' https:; font-src 'self' data: https:; frame-src 'self' https:;";
$contentSecurityPolicy = nosfir_env_first([
    'CONTENT_SECURITY_POLICY',
    'NOSFIRSOLIS_CONTENT_SECURITY_POLICY',
], $defaultContentSecurityPolicy);

$hstsEnabledRaw = strtolower(nosfir_env_first([
    'HSTS_ENABLED',
    'NOSFIRSOLIS_HSTS_ENABLED',
], $appEnvironment === 'production' ? '1' : '0'));

$hstsEnabled = in_array($hstsEnabledRaw, ['1', 'true', 'yes', 'on'], true);

$hstsMaxAgeRaw = nosfir_env_first([
    'HSTS_MAX_AGE',
    'NOSFIRSOLIS_HSTS_MAX_AGE',
], '31536000');

$hstsMaxAge = ctype_digit($hstsMaxAgeRaw) ? (int) $hstsMaxAgeRaw : 31536000;

$hstsIncludeSubdomainsRaw = strtolower(nosfir_env_first([
    'HSTS_INCLUDE_SUBDOMAINS',
    'NOSFIRSOLIS_HSTS_INCLUDE_SUBDOMAINS',
], '1'));

$hstsIncludeSubdomains = in_array($hstsIncludeSubdomainsRaw, ['1', 'true', 'yes', 'on'], true);

$hstsPreloadRaw = strtolower(nosfir_env_first([
    'HSTS_PRELOAD',
    'NOSFIRSOLIS_HSTS_PRELOAD',
], '0'));

$hstsPreload = in_array($hstsPreloadRaw, ['1', 'true', 'yes', 'on'], true);

$securityRuntimeSchemaMutationsRaw = strtolower(nosfir_env_first([
    'SECURITY_RUNTIME_SCHEMA_MUTATIONS',
    'NOSFIRSOLIS_SECURITY_RUNTIME_SCHEMA_MUTATIONS',
], '0'));

$securityRuntimeSchemaMutations = in_array($securityRuntimeSchemaMutationsRaw, ['1', 'true', 'yes', 'on'], true);

if ($appEnvironment === 'production') {
    if ($cspAllowUnsafeEval) {
        error_log('[Solis] CSP_ALLOW_UNSAFE_EVAL=1 em producao. Revisar necessidade e reduzir superficie de execucao dinamica.');
    }

    if ($hostGuardCompatibilityMode) {
        error_log('[Solis] HOST_GUARD_COMPATIBILITY_MODE forcado para false em producao por politica de seguranca.');
        $hostGuardCompatibilityMode = false;
    }

    if ($allowPrivateWebhookEndpoints) {
        error_log('[Solis] AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS forcado para false em producao por politica de seguranca.');
        $allowPrivateWebhookEndpoints = false;
    }

    if ($authFailOpenOnSecurityError) {
        error_log('[Solis] AUTH_FAIL_OPEN_ON_SECURITY_ERROR forcado para false em producao por politica de seguranca.');
        $authFailOpenOnSecurityError = false;
    }

    if ($securityRuntimeSchemaMutations) {
        error_log('[Solis] SECURITY_RUNTIME_SCHEMA_MUTATIONS forcado para false em producao por politica de seguranca.');
        $securityRuntimeSchemaMutations = false;
    }
}

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
        'host_guard_compatibility_mode' => $hostGuardCompatibilityMode,
        'runtime_schema_mutations' => $securityRuntimeSchemaMutations,
        'auth' => [
            'fail_open_on_security_error' => $authFailOpenOnSecurityError,
        ],
        'headers' => [
            'enabled' => $securityHeadersEnabled,
            'x_content_type_options' => 'nosniff',
            'x_frame_options' => 'SAMEORIGIN',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'geolocation=(), camera=(), microphone=()',
            'x_permitted_cross_domain_policies' => 'none',
            'content_security_policy' => $contentSecurityPolicy,
            'hsts' => [
                'enabled' => $hstsEnabled,
                'max_age' => max(0, $hstsMaxAge),
                'include_subdomains' => $hstsIncludeSubdomains,
                'preload' => $hstsPreload,
            ],
        ],
        'automation' => [
            'allow_private_webhook_endpoints' => $allowPrivateWebhookEndpoints,
        ],
    ],
];
