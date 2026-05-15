<?php

declare(strict_types=1);

define('DIR_ROOT', __DIR__);
require_once DIR_ROOT . '/system/Library/HostGuard.php';

$rootConfig = require DIR_ROOT . '/config.php';
$storageConfig = [];
$storageConfigCandidates = [
    DIR_ROOT . '/system/Storage/config.php',
    DIR_ROOT . '/system/storage/config.php',
];

foreach ($storageConfigCandidates as $storageConfigFile) {
    if (!is_file($storageConfigFile)) {
        continue;
    }

    $loadedStorageConfig = require $storageConfigFile;
    if (is_array($loadedStorageConfig)) {
        $storageConfig = $loadedStorageConfig;
        break;
    }
}

$installed = (is_array($rootConfig) && !empty($rootConfig['app']['installed']))
    || (!empty($storageConfig['app']['installed']));
$appName = isset($storageConfig['app']['name']) && trim((string) $storageConfig['app']['name']) !== ''
    ? (string) $storageConfig['app']['name']
    : ((is_array($rootConfig) && isset($rootConfig['app']['name']))
        ? (string) $rootConfig['app']['name']
        : 'Solis');

$appConfig = is_array($rootConfig) ? (array) ($rootConfig['app'] ?? []) : [];
$securityConfig = is_array($rootConfig) ? (array) ($rootConfig['security'] ?? []) : [];
if (is_array($storageConfig)) {
    $storageApp = (array) ($storageConfig['app'] ?? []);
    $storageSecurity = (array) ($storageConfig['security'] ?? []);
    if ($storageApp !== []) {
        $appConfig = array_replace_recursive($appConfig, $storageApp);
    }
    if ($storageSecurity !== []) {
        $securityConfig = array_replace_recursive($securityConfig, $storageSecurity);
    }
}

$allowedHosts = (array) ($securityConfig['allowed_hosts'] ?? []);
$baseUrl = (string) ($appConfig['base_url'] ?? '');
$environmentRaw = (string) ($appConfig['environment'] ?? '');
if (function_exists('nosfir_env_first')) {
    $environmentOverride = nosfir_env_first(['APP_ENV', 'NOSFIRSOLIS_APP_ENV'], '');
    if ($environmentOverride !== '') {
        $environmentRaw = $environmentOverride;
    }
}

$environment = function_exists('nosfir_normalize_environment')
    ? nosfir_normalize_environment($environmentRaw)
    : (in_array(strtolower(trim($environmentRaw)), ['dev', 'development', 'local', 'localhost'], true) ? 'development' : 'production');
$isDevelopment = $environment === 'development';
$requestHost = \System\Library\HostGuard::requestHost($_SERVER);
$trustedProxies = (array) ($securityConfig['trusted_proxies'] ?? []);
$isHttpsRequest = function_exists('nosfir_request_is_https')
    ? nosfir_request_is_https($trustedProxies)
    : ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

// Keep setup ergonomics in development and installer flow by auto-accepting current host.
if (($isDevelopment || !$installed) && $requestHost !== '') {
    $normalizedAllowedHosts = \System\Library\HostGuard::normalizedAllowedHosts($allowedHosts, $baseUrl);
    if (!in_array($requestHost, $normalizedAllowedHosts, true)) {
        $allowedHosts[] = $requestHost;
    }
}

if (!\System\Library\HostGuard::isAllowedRequestHost($_SERVER, $allowedHosts, $baseUrl)) {
    $normalizedAllowedHosts = \System\Library\HostGuard::normalizedAllowedHosts($allowedHosts, $baseUrl);
    $localDefaultHosts = ['localhost', '127.0.0.1', '::1'];
    $onlyLocalDefaults = $normalizedAllowedHosts !== []
        && array_diff($normalizedAllowedHosts, $localDefaultHosts) === [];
    $requestHostIsBlockedByDefault = $requestHost !== ''
        && !in_array($requestHost, $normalizedAllowedHosts, true);
    $compatibilityModeEnabled = (bool) ($securityConfig['host_guard_compatibility_mode'] ?? false);

    if (
        !$isDevelopment
        && $installed
        && $compatibilityModeEnabled
        && $onlyLocalDefaults
        && $requestHostIsBlockedByDefault
    ) {
        error_log(
            '[Solis] HostGuard compatibility mode (landing): allowing host because allowed_hosts still has only local defaults.'
            . ' host=' . $requestHost
            . ' allowed_hosts=' . json_encode($allowedHosts, JSON_UNESCAPED_UNICODE)
        );
    } else {
        error_log(
            '[Solis] Landing blocked by HostGuard. '
            . 'env=' . $environment
            . ' host=' . ($requestHost !== '' ? $requestHost : '(empty)')
            . ' allowed_hosts=' . json_encode($allowedHosts, JSON_UNESCAPED_UNICODE)
            . ' base_url=' . $baseUrl
        );
        if (!headers_sent()) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo 'Bad Request: host nao permitido. Configure security.allowed_hosts (ou ALLOWED_HOSTS no ambiente) com o dominio atual.';
        exit;
    }
}

$scheme = $isHttpsRequest ? 'https' : 'http';
$host = \System\Library\HostGuard::effectiveHost($_SERVER, $allowedHosts, $baseUrl);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$base = rtrim($scheme . '://' . $host . $scriptDir, '/');
$basePath = $scriptDir === '/' ? '' : $scriptDir;

// Apply security headers in landing flow (outside Application/Response lifecycle).
$securityHeadersConfig = (array) ($securityConfig['headers'] ?? []);
$securityHeadersEnabled = (bool) ($securityHeadersConfig['enabled'] ?? true);
if ($securityHeadersEnabled && !headers_sent()) {
    $headerMap = [
        'X-Content-Type-Options' => (string) ($securityHeadersConfig['x_content_type_options'] ?? 'nosniff'),
        'X-Frame-Options' => (string) ($securityHeadersConfig['x_frame_options'] ?? 'SAMEORIGIN'),
        'Referrer-Policy' => (string) ($securityHeadersConfig['referrer_policy'] ?? 'strict-origin-when-cross-origin'),
        'Permissions-Policy' => (string) ($securityHeadersConfig['permissions_policy'] ?? 'geolocation=(), camera=(), microphone=()'),
        'X-Permitted-Cross-Domain-Policies' => (string) ($securityHeadersConfig['x_permitted_cross_domain_policies'] ?? 'none'),
    ];

    foreach ($headerMap as $name => $value) {
        $value = trim($value);
        if ($value === '') {
            continue;
        }

        header($name . ': ' . $value, true);
    }

    $contentSecurityPolicy = trim((string) ($securityHeadersConfig['content_security_policy'] ?? ''));
    if ($contentSecurityPolicy !== '') {
        header('Content-Security-Policy: ' . $contentSecurityPolicy, true);
    }

    $hsts = (array) ($securityHeadersConfig['hsts'] ?? []);
    $hstsEnabled = (bool) ($hsts['enabled'] ?? false);
    if ($hstsEnabled && $isHttpsRequest) {
        $maxAge = max(0, (int) ($hsts['max_age'] ?? 31536000));
        if ($maxAge > 0) {
            $parts = ['max-age=' . $maxAge];
            if ((bool) ($hsts['include_subdomains'] ?? true)) {
                $parts[] = 'includeSubDomains';
            }
            if ((bool) ($hsts['preload'] ?? false)) {
                $parts[] = 'preload';
            }

            header('Strict-Transport-Security: ' . implode('; ', $parts), true);
        }
    }
}

if (!$installed) {
    header('Location: ' . $base . '/install');
    exit;
}

require_once DIR_ROOT . '/system/Engine/Startup.php';

$sessionName = isset($storageConfig['app']['session_name']) && trim((string) $storageConfig['app']['session_name']) !== ''
    ? (string) $storageConfig['app']['session_name']
    : ((is_array($rootConfig) && isset($rootConfig['app']['session_name']))
        ? (string) $rootConfig['app']['session_name']
        : 'nsplanner_session');
$storageDir = defined('DIR_STORAGE') && is_string(DIR_STORAGE) && trim((string) DIR_STORAGE) !== ''
    ? (string) DIR_STORAGE
    : '';
if ($storageDir === '' || !is_dir($storageDir)) {
    $storageUpper = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage';
    $storageLower = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'storage';
    $storageDir = is_dir($storageUpper) ? $storageUpper : (is_dir($storageLower) ? $storageLower : $storageUpper);
}
$sessionPath = $storageDir . DIRECTORY_SEPARATOR . 'sessions';
$session = new \System\Engine\Session($sessionName, $sessionPath);

$normalizeLanguageCode = static function (string $code): ?string {
    $code = strtolower(trim($code));
    $code = str_replace('_', '-', $code);

    return preg_match('/^[a-z]{2}-[a-z]{2}$/', $code) === 1 ? $code : null;
};

$supportedLanguagesConfig = (array) (($appConfig['languages']['supported'] ?? []));
$supportedLanguageCodes = [];
foreach ($supportedLanguagesConfig as $code => $_metadata) {
    $normalizedCode = $normalizeLanguageCode((string) $code);
    if ($normalizedCode === null) {
        continue;
    }

    $supportedLanguageCodes[$normalizedCode] = true;
}

if ($supportedLanguageCodes === []) {
    $supportedLanguageCodes = [
        'en-us' => true,
        'pt-br' => true,
    ];
}

$fallbackLanguageCode = $normalizeLanguageCode((string) ($appConfig['languages']['fallback'] ?? 'en-us'));
if ($fallbackLanguageCode === null || !isset($supportedLanguageCodes[$fallbackLanguageCode])) {
    $fallbackLanguageCode = array_key_first($supportedLanguageCodes) ?: 'en-us';
}

$currentLanguageCode = $normalizeLanguageCode((string) $session->get('language_code', ''));
if ($currentLanguageCode === null || !isset($supportedLanguageCodes[$currentLanguageCode])) {
    $currentLanguageCode = $fallbackLanguageCode;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && array_key_exists('landing_language_code', $_POST)
) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Requisicao invalida.');
    } else {
        $selectedLanguage = $normalizeLanguageCode((string) ($_POST['landing_language_code'] ?? ''));
        if ($selectedLanguage !== null && isset($supportedLanguageCodes[$selectedLanguage])) {
            $session->set('language_code', $selectedLanguage);
            flash('success', $selectedLanguage === 'pt-br' ? 'Idioma atualizado com sucesso.' : 'Language updated successfully.');
        } else {
            flash('error', $currentLanguageCode === 'pt-br' ? 'Idioma invalido selecionado.' : 'Invalid language selected.');
        }
    }

    header('Location: ' . $base . '/');
    exit;
}

$loginAction = $basePath . '/client/auth/authenticate';
$registerUrl = $basePath . '/client/auth/register';
$messageSuccess = flash('success');
$messageError = flash('error');
$faviconPath = ($basePath !== '' ? $basePath : '') . '/image/solis.png';
$logoPath = ($basePath !== '' ? $basePath : '') . '/image/solis_logo.png';
$pageUrl = $scheme . '://' . $host . (string) ($_SERVER['REQUEST_URI'] ?? ($basePath !== '' ? $basePath : '/'));
$metaTitle = $appName . ' | Plataforma de planejamento de conteudo';
$metaDescription = 'Solis centraliza planejamento, execucao e acompanhamento de conteudo em um unico fluxo.';
$ogImageUrl = $scheme . '://' . $host . $logoPath;
$languageSaveAction = $base . '/';
$languageFlagMap = [
    'en-us' => '&#x1F1FA;&#x1F1F8;',
    'pt-br' => '&#x1F1E7;&#x1F1F7;',
];
$landingTranslations = [
    'pt-br' => [
        'platform' => 'Platform',
        'hero_title' => 'Planejamento estrategico, execucao diaria e visao clara da sua operacao de conteudo.',
        'hero_description' => 'O Solis organiza campanhas, calendarios e distribuicao em um unico fluxo. Sua equipe acompanha o que precisa ser feito, quando publicar e como medir resultado.',
        'feature_1_title' => 'Calendario inteligente',
        'feature_1_text' => 'Planeje ano, mes e periodo com contexto de campanhas e datas importantes.',
        'feature_2_title' => 'Operacao em equipe',
        'feature_2_text' => 'Centralize status, revisoes e prioridades para reduzir retrabalho e atrasos.',
        'feature_3_title' => 'Publicacao social',
        'feature_3_text' => 'Prepare drafts e formatos por plataforma com consistencia de marca.',
        'feature_4_title' => 'Tracking de campanha',
        'feature_4_text' => 'Use links rastreaveis para entender cliques e ajustar sua estrategia.',
        'login_title' => 'Acesso do cliente',
        'login_description' => 'Entre com seu usuario para abrir o painel de trabalho.',
        'field_email' => 'E-mail',
        'field_password' => 'Senha',
        'button_login' => 'Entrar no Solis',
        'button_register' => 'Criar conta gratuita',
        'language_label' => 'Idioma',
    ],
    'en-us' => [
        'platform' => 'Platform',
        'hero_title' => 'Strategic planning, daily execution, and clear visibility into your content operation.',
        'hero_description' => 'Solis unifies campaigns, calendars, and distribution in one workflow. Your team tracks what to do, when to publish, and how to measure results.',
        'feature_1_title' => 'Smart calendar',
        'feature_1_text' => 'Plan year, month, and periods with campaign context and important dates.',
        'feature_2_title' => 'Team operation',
        'feature_2_text' => 'Centralize status, reviews, and priorities to reduce rework and delays.',
        'feature_3_title' => 'Social publishing',
        'feature_3_text' => 'Prepare drafts and formats by platform with brand consistency.',
        'feature_4_title' => 'Campaign tracking',
        'feature_4_text' => 'Use trackable links to understand clicks and adjust your strategy.',
        'login_title' => 'Client access',
        'login_description' => 'Sign in to open your workspace dashboard.',
        'field_email' => 'E-mail',
        'field_password' => 'Password',
        'button_login' => 'Sign in to Solis',
        'button_register' => 'Create free account',
        'language_label' => 'Language',
    ],
];
$landingTextSet = $landingTranslations[$currentLanguageCode] ?? $landingTranslations[$fallbackLanguageCode] ?? $landingTranslations['pt-br'];
$landingText = static function (string $key, string $default = '') use ($landingTextSet): string {
    return (string) ($landingTextSet[$key] ?? $default);
};
$metaTitle = $appName . ' | ' . ($currentLanguageCode === 'en-us' ? 'Content Planning Platform' : 'Plataforma de planejamento de conteudo');
$metaDescription = $landingText(
    'hero_description',
    'Solis centraliza planejamento, execucao e acompanhamento de conteudo em um unico fluxo.'
);
$htmlLanguageCode = $currentLanguageCode === 'pt-br' ? 'pt-BR' : 'en-US';

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="<?= e($htmlLanguageCode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($metaTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e($faviconPath) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <meta property="og:image" content="<?= e($ogImageUrl) ?>">
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
    <style>
        :root {
            --bg-cream: #f5efe4;
            --bg-ink: #101820;
            --bg-deep: #1a2d3a;
            --bg-card: #fdfaf3;
            --line: #dbcdb3;
            --accent: #d87c34;
            --accent-strong: #b85f1f;
            --text-primary: #14212c;
            --text-muted: #51616f;
            --ok-bg: #e3f6e9;
            --ok-text: #1f6a35;
            --err-bg: #fde8e5;
            --err-text: #8f2f22;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI Variable", "Trebuchet MS", "Candara", sans-serif;
            background:
                radial-gradient(circle at 12% 12%, rgba(216, 124, 52, 0.25), transparent 38%),
                radial-gradient(circle at 88% 18%, rgba(16, 24, 32, 0.2), transparent 34%),
                linear-gradient(140deg, var(--bg-cream), #efe2cb 58%, #ead8bc 100%);
            color: var(--text-primary);
        }

        .shell {
            width: min(1120px, 92vw);
            margin: 34px auto 40px;
            display: grid;
            grid-template-columns: 1.25fr 0.95fr;
            gap: 26px;
        }

        .panel {
            background: rgba(253, 250, 243, 0.92);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(16, 24, 32, 0.12);
            padding: 30px;
            backdrop-filter: blur(2px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid #cab899;
            color: #50361d;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 12px;
        }

        .brand img {
            display: block;
            height: 22px;
            width: auto;
        }

        h1 {
            margin: 18px 0 12px;
            font-size: clamp(28px, 4.3vw, 46px);
            line-height: 1.08;
            letter-spacing: -0.02em;
        }

        .lead {
            margin: 0 0 22px;
            color: var(--text-muted);
            font-size: clamp(15px, 2.2vw, 19px);
            line-height: 1.6;
            max-width: 62ch;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .feature {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 15px;
            background: #fffdf8;
        }

        .feature strong {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .feature p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .login-card {
            background: linear-gradient(165deg, var(--bg-ink), var(--bg-deep));
            border-radius: 24px;
            padding: 28px;
            color: #f8f1e6;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .login-card h2 {
            margin: 0 0 8px;
            font-size: 27px;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }

        .login-card p {
            margin: 0 0 20px;
            color: #cad4df;
            font-size: 14px;
            line-height: 1.5;
        }

        .language-switcher {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .language-form {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .language-label {
            color: #cad4df;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .language-select-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 225, 190, 0.35);
            border-radius: 999px;
            background: rgba(255, 225, 190, 0.1);
            padding: 5px 10px;
        }

        .language-flag {
            font-size: 16px;
            line-height: 1;
        }

        .language-select {
            border: 0;
            background: transparent;
            color: #ffe7c8;
            font-size: 12px;
            font-weight: 700;
            text-transform: lowercase;
            outline: none;
            cursor: pointer;
        }

        .language-select option {
            color: #111;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-size: 14px;
            line-height: 1.4;
            border: 1px solid transparent;
        }

        .alert-ok {
            background: var(--ok-bg);
            color: var(--ok-text);
            border-color: #c8ead3;
        }

        .alert-error {
            background: var(--err-bg);
            color: var(--err-text);
            border-color: #f4c7c0;
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
            color: #f7e8d5;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .field input {
            width: 100%;
            border: 1px solid #385165;
            background: #112535;
            color: #f8f4ed;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .field input:focus {
            border-color: #e6a56d;
            box-shadow: 0 0 0 3px rgba(216, 124, 52, 0.18);
        }

        .cta {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 13px 16px;
            margin-top: 8px;
            background: linear-gradient(90deg, var(--accent), #e39a58);
            color: #22150a;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.12s ease, background 0.2s ease;
        }

        .cta:hover {
            transform: translateY(-1px);
            background: linear-gradient(90deg, var(--accent-strong), var(--accent));
        }

        .alt-link {
            display: inline-flex;
            width: 100%;
            margin-top: 12px;
            justify-content: center;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 225, 190, 0.45);
            background: rgba(255, 225, 190, 0.08);
            color: #ffe7c8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.12s ease;
        }

        .alt-link:hover {
            border-color: #ffe1be;
            background: rgba(255, 225, 190, 0.16);
            transform: translateY(-1px);
        }

        @media (max-width: 980px) {
            .shell {
                width: min(720px, 94vw);
                margin: 24px auto 28px;
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }

        @media (max-width: 640px) {
            .shell {
                width: min(560px, 94vw);
                margin: 14px auto 18px;
                gap: 12px;
            }

            .panel {
                padding: 22px;
                border-radius: 18px;
            }

            .login-card {
                border-radius: 18px;
                padding: 22px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .brand {
                width: 100%;
                justify-content: center;
            }

            h1 {
                font-size: clamp(24px, 8.3vw, 34px);
                line-height: 1.14;
            }

            .lead {
                font-size: 15px;
                line-height: 1.52;
            }
        }

        @media (max-width: 480px) {
            .shell {
                width: 95vw;
                margin: 10px auto 14px;
            }

            .panel {
                padding: 16px;
                border-radius: 14px;
            }

            .login-card {
                padding: 16px;
                border-radius: 14px;
            }

            .brand {
                font-size: 11px;
                gap: 8px;
                padding: 6px 9px;
            }

            .brand img {
                height: 18px;
            }

            .lead {
                margin-bottom: 16px;
            }

            .feature {
                border-radius: 12px;
                padding: 12px;
            }

            .feature strong {
                font-size: 14px;
            }

            .feature p,
            .alert,
            .alt-link,
            .field input,
            .cta {
                font-size: 13px;
            }

            .field input,
            .cta,
            .alt-link {
                border-radius: 10px;
                padding-top: 11px;
                padding-bottom: 11px;
            }

            .language-switcher {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="panel">
            <span class="brand">
                <img src="<?= e($logoPath) ?>" alt="<?= e($appName) ?>">
                <span><?= e($appName) ?> <?= e($landingText('platform', 'Platform')) ?></span>
            </span>
            <h1><?= e($landingText('hero_title', 'Planejamento estrategico, execucao diaria e visao clara da sua operacao de conteudo.')) ?></h1>
            <p class="lead"><?= e($landingText('hero_description', 'O Solis organiza campanhas, calendarios e distribuicao em um unico fluxo. Sua equipe acompanha o que precisa ser feito, quando publicar e como medir resultado.')) ?></p>

            <div class="grid">
                <article class="feature">
                    <strong><?= e($landingText('feature_1_title', 'Calendario inteligente')) ?></strong>
                    <p><?= e($landingText('feature_1_text', 'Planeje ano, mes e periodo com contexto de campanhas e datas importantes.')) ?></p>
                </article>
                <article class="feature">
                    <strong><?= e($landingText('feature_2_title', 'Operacao em equipe')) ?></strong>
                    <p><?= e($landingText('feature_2_text', 'Centralize status, revisoes e prioridades para reduzir retrabalho e atrasos.')) ?></p>
                </article>
                <article class="feature">
                    <strong><?= e($landingText('feature_3_title', 'Publicacao social')) ?></strong>
                    <p><?= e($landingText('feature_3_text', 'Prepare drafts e formatos por plataforma com consistencia de marca.')) ?></p>
                </article>
                <article class="feature">
                    <strong><?= e($landingText('feature_4_title', 'Tracking de campanha')) ?></strong>
                    <p><?= e($landingText('feature_4_text', 'Use links rastreaveis para entender cliques e ajustar sua estrategia.')) ?></p>
                </article>
            </div>
        </section>

        <aside class="login-card">
            <div class="language-switcher">
                <form method="post" action="<?= e($languageSaveAction) ?>" class="language-form">
                    <?= csrf_field() ?>
                    <label for="landing-language" class="language-label"><?= e($landingText('language_label', 'Idioma')) ?></label>
                    <div class="language-select-wrap">
                        <span class="language-flag"><?= $languageFlagMap[$currentLanguageCode] ?? '&#x1F310;' ?></span>
                        <select id="landing-language" name="landing_language_code" class="language-select" onchange="this.form.submit()">
                            <?php foreach (array_keys($supportedLanguageCodes) as $languageCode): ?>
                                <option value="<?= e($languageCode) ?>"<?= $currentLanguageCode === $languageCode ? ' selected' : '' ?>>
                                    <?= e($languageCode) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <h2><?= e($landingText('login_title', 'Acesso do cliente')) ?></h2>
            <p><?= e($landingText('login_description', 'Entre com seu usuario para abrir o painel de trabalho.')) ?></p>

            <?php if (is_string($messageSuccess) && $messageSuccess !== ''): ?>
                <div class="alert alert-ok"><?= e($messageSuccess) ?></div>
            <?php endif; ?>

            <?php if (is_string($messageError) && $messageError !== ''): ?>
                <div class="alert alert-error"><?= e($messageError) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e($loginAction) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email"><?= e($landingText('field_email', 'E-mail')) ?></label>
                    <input id="email" type="email" name="email" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password"><?= e($landingText('field_password', 'Senha')) ?></label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="cta"><?= e($landingText('button_login', 'Entrar no Solis')) ?></button>
            </form>
            <a class="alt-link" href="<?= e($registerUrl) ?>"><?= e($landingText('button_register', 'Criar conta gratuita')) ?></a>
        </aside>
    </main>
</body>
</html>
