<!doctype html>
<html lang="<?= e($language_code ?? 'en-us') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $app_name ?? $t('layout.title_default', 'Admin')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<?php
$currentRoute = strtolower(trim((string) ($current_route ?? '')));
if ($currentRoute === '') {
    $currentRoute = 'dashboard/index';
}
$userName = (string) ($current_user['name'] ?? '');
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));
$navItems = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'prefix' => 'dashboard/', 'icon' => 'fa-solid fa-chart-line'],
    ['label' => $t('layout.nav_holidays', 'Feriados'), 'route' => 'holidays/index', 'prefix' => 'holidays/', 'icon' => 'fa-solid fa-calendar-day'],
    ['label' => $t('layout.nav_commemoratives', 'Comemorativas'), 'route' => 'commemoratives/index', 'prefix' => 'commemoratives/', 'icon' => 'fa-solid fa-star'],
    ['label' => $t('layout.nav_suggestions', 'Sugestões'), 'route' => 'suggestions/index', 'prefix' => 'suggestions/', 'icon' => 'fa-solid fa-lightbulb'],
    ['label' => $t('layout.nav_channels', 'Canais'), 'route' => 'channels/index', 'prefix' => 'channels/', 'icon' => 'fa-solid fa-share-nodes'],
    ['label' => $t('layout.nav_campaigns', 'Campanhas'), 'route' => 'campaigns/index', 'prefix' => 'campaigns/', 'icon' => 'fa-solid fa-bullhorn'],
    ['label' => $t('layout.nav_users', 'Usuários e Hierarquia'), 'route' => 'users/index', 'prefix' => 'users/', 'icon' => 'fa-solid fa-users'],
    ['label' => $t('layout.nav_operations', 'Operações'), 'route' => 'operations/index', 'prefix' => 'operations/', 'icon' => 'fa-solid fa-gears'],
];
?>
<body class="<?= !empty($current_user) ? 'admin-auth' : 'admin-guest' ?>">
<div class="admin-shell<?= empty($current_user) ? ' guest' : '' ?>">
    <?php if (!empty($current_user)): ?>
        <aside class="sidebar" id="adminSidebar">
            <a class="sidebar-brand" href="<?= e(route_url('dashboard/index')) ?>">
                <img src="<?= e(asset_url('img/reamurcms.png')) ?>" alt="ReamurCMS">
                <span>
                    <strong><?= e($app_name ?? 'Solis') ?></strong>
                    <small><?= e($t('layout.panel_subtitle', 'Painel Administrativo')) ?></small>
                </span>
            </a>

            <nav class="sidebar-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $isActive = $currentRoute === strtolower($item['route']) || str_starts_with($currentRoute, strtolower($item['prefix']));
                    ?>
                    <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(route_url($item['route'])) ?>">
                        <span><i class="<?= e((string) ($item['icon'] ?? 'fa-solid fa-circle')) ?>"></i> <?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <p><?= e($t('layout.connected_as', 'Conectado como')) ?></p>
                <strong><?= e($userName) ?></strong>
            </div>
        </aside>
    <?php endif; ?>

    <div class="main-content-wrapper<?= empty($current_user) ? ' auth-only' : '' ?>">
        <?php if (!empty($current_user)): ?>
            <header class="topbar">
                <button type="button" class="icon-btn" id="sidebarToggle" aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>">
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <strong><?= e($title ?? $t('layout.topbar_title', 'Painel Administrativo')) ?></strong>
                    <small><?= e($t('layout.topbar_subtitle', 'Gestao administrativa e hierarquia de acesso do {app}', ['app' => ($app_name ?? 'Solis')])) ?></small>
                </div>
                <div class="topbar-user">
                    <span class="user-avatar"><?= e($userInitial) ?></span>
                    <div>
                        <strong><?= e($userName) ?></strong>
                        <form method="post" action="<?= e(route_url('language/save')) ?>" style="margin:2px 0 4px 0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                            <label for="adminLanguageCode" style="font-size:12px;opacity:.9">
                                <?= e($t('layout.language_label', 'Idioma')) ?>
                            </label>
                            <select id="adminLanguageCode" name="language_code" onchange="this.form.submit()" style="margin-left:4px">
                                <option value="pt-br" <?= strtolower((string) ($language_code ?? 'en-us')) === 'pt-br' ? 'selected' : '' ?>>
                                    <?= e($t('layout.language_option_pt_br', 'Português')) ?>
                                </option>
                                <option value="en-us" <?= strtolower((string) ($language_code ?? 'en-us')) === 'en-us' ? 'selected' : '' ?>>
                                    <?= e($t('layout.language_option_en_us', 'English')) ?>
                                </option>
                            </select>
                        </form>
                        <form method="post" action="<?= e(route_url('auth/logout')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" style="background:none;border:0;padding:0;color:inherit;font:inherit;cursor:pointer">
                                <i class="fa-solid fa-right-from-bracket"></i> <?= e($t('layout.logout', 'Sair')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </header>
        <?php endif; ?>

        <main class="main-content">
            <?php if ($message_success): ?>
                <div class="alert success"><?= e($message_success) ?></div>
            <?php endif; ?>

            <?php if ($message_error): ?>
                <div class="alert error"><?= e($message_error) ?></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('sidebarToggle');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-open');
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 980) {
            return;
        }

        var sidebar = document.getElementById('adminSidebar');
        if (!sidebar) {
            return;
        }

        var clickedInsideSidebar = sidebar.contains(event.target);
        var clickedToggle = toggle.contains(event.target);
        if (!clickedInsideSidebar && !clickedToggle) {
            document.body.classList.remove('sidebar-open');
        }
    });
});
</script>
</body>
</html>
