<?php
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$requestHost = preg_replace('/[^a-z0-9\.\-:\[\]]/i', '', $requestHost) ?? 'localhost';
$requestHost = trim($requestHost) !== '' ? $requestHost : 'localhost';
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
$pageUrl = $requestScheme . '://' . $requestHost . $requestUri;
$canonicalUrl = $requestScheme . '://' . $requestHost . $requestPath;
$metaTitle = (string) ($title ?? $t('layout.title_default', 'Instalador Solis'));
$metaDescription = (string) $t('layout.meta_description', 'Instalador do Solis para configuração segura do ambiente.');
?>
<!doctype html>
<html lang="<?= e($language_code ?? 'en-us') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/install.css')) ?>">
</head>
<body>
    <main class="install-shell">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
