<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', DIR_ROOT . DIRECTORY_SEPARATOR . 'system');
}

$storageCandidates = [
    DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php',
    DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config.php',
];

$storageFile = null;
foreach ($storageCandidates as $candidate) {
    if (is_file($candidate)) {
        $storageFile = $candidate;
        break;
    }
}

if ($storageFile === null) {
    echo "[INFO] Runtime storage config nao encontrado.\n";
    exit(0);
}

$runtimeConfig = require $storageFile;
if (!is_array($runtimeConfig)) {
    echo "[ERROR] Runtime storage config invalido: {$storageFile}\n";
    exit(1);
}

$envFile = DIR_ROOT . DIRECTORY_SEPARATOR . '.env';
$envLines = is_file($envFile) ? (file($envFile, FILE_IGNORE_NEW_LINES) ?: []) : [];
$updates = [];

$database = (array) ($runtimeConfig['database'] ?? []);
$dbMap = [
    'DB_HOST' => 'host',
    'DB_PORT' => 'port',
    'DB_DATABASE' => 'database',
    'DB_USERNAME' => 'username',
    'DB_PASSWORD' => 'password',
];

foreach ($dbMap as $envKey => $configKey) {
    $value = trim((string) ($database[$configKey] ?? ''));
    if ($value === '') {
        continue;
    }

    if (readEnvValue($envLines, $envKey) === null) {
        $updates[$envKey] = $value;
    }
}

$runtimePassword = trim((string) ($database['password'] ?? ''));
if ($runtimePassword !== '' && stripos($runtimePassword, 'change_me') === false) {
    $runtimeConfig['database']['password'] = '';
    echo "[PASS] Senha de banco removida de runtime storage config.\n";
} else {
    echo "[PASS] Runtime storage config ja sem senha explicita (ou placeholder).\n";
}

$appEnv = normalizeEnvironment((string) (readEnvValue($envLines, 'APP_ENV') ?? ''));
$runtimeEnv = normalizeEnvironment((string) (($runtimeConfig['app']['environment'] ?? '')));
if ($appEnv !== '' && $runtimeEnv !== '' && $appEnv !== $runtimeEnv) {
    $runtimeConfig['app']['environment'] = $appEnv;
    echo "[PASS] Ambiente runtime sincronizado com APP_ENV ({$appEnv}).\n";
}

foreach ($updates as $key => $value) {
    setEnvValue($envLines, $key, $value);
    if ($key === 'DB_PASSWORD') {
        echo "[PASS] {$key} definido no .env a partir do runtime config.\n";
        continue;
    }

    echo "[PASS] {$key} definido no .env a partir do runtime config.\n";
}

if ($updates === []) {
    echo "[PASS] .env ja contem chaves DB_* necessarias.\n";
}

$runtimeContent = "<?php\n\nreturn " . var_export($runtimeConfig, true) . ";\n";
if (file_put_contents($storageFile, $runtimeContent) === false) {
    echo "[ERROR] Falha ao gravar runtime storage config.\n";
    exit(1);
}

if (writeEnvLines($envFile, $envLines) === false) {
    echo "[ERROR] Falha ao gravar .env.\n";
    exit(1);
}

echo "[DONE] Hardening aplicado.\n";
echo "[INFO] Arquivo runtime: {$storageFile}\n";
echo "[INFO] Arquivo env: {$envFile}\n";
exit(0);

function normalizeEnvironment(string $value): string
{
    $normalized = strtolower(trim($value));
    if (in_array($normalized, ['dev', 'development', 'local', 'localhost'], true)) {
        return 'development';
    }

    if (in_array($normalized, ['prod', 'production', 'live'], true)) {
        return 'production';
    }

    return '';
}

function readEnvValue(array $lines, string $key): ?string
{
    foreach ($lines as $line) {
        if (!is_string($line)) {
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $lineKey = trim(substr($trimmed, 0, $separatorPos));
        if ($lineKey !== $key) {
            continue;
        }

        $value = trim(substr($trimmed, $separatorPos + 1));
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return null;
}

function setEnvValue(array &$lines, string $key, string $value): void
{
    $formatted = formatEnvValue($value);
    $lineValue = $key . '=' . $formatted;

    foreach ($lines as $index => $line) {
        if (!is_string($line)) {
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $lineKey = trim(substr($trimmed, 0, $separatorPos));
        if ($lineKey !== $key) {
            continue;
        }

        $lines[$index] = $lineValue;
        return;
    }

    $lines[] = $lineValue;
}

function formatEnvValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/\s|#|"|\'/', $value) === 1) {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    return $value;
}

function writeEnvLines(string $file, array $lines): bool
{
    $normalizedLines = [];
    foreach ($lines as $line) {
        if (!is_string($line)) {
            continue;
        }

        $normalizedLines[] = rtrim($line, "\r\n");
    }

    $content = implode(PHP_EOL, $normalizedLines);
    if ($content !== '') {
        $content .= PHP_EOL;
    }

    return file_put_contents($file, $content) !== false;
}
