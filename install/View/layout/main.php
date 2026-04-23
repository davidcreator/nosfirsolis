<!doctype html>
<html lang="<?= e($language_code ?? 'en-us') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $t('layout.title_default', 'Instalador')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/install.css')) ?>">
</head>
<body>
    <main class="install-shell">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
