<!doctype html>
<html lang="<?= e($language_code ?? 'en-us') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $app_name ?? $t('layout.title_default', 'Planner')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/client.css')) ?>">
</head>
<?php
$currentRoute = strtolower(trim((string) ($current_route ?? '')));
if ($currentRoute === '') {
    $currentRoute = 'dashboard/index';
}
$featureFlags = (array) ($feature_flags ?? []);
$userName = (string) ($current_user['name'] ?? '');
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));
$navItems = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'prefix' => 'dashboard/', 'icon' => 'fa-solid fa-chart-pie'],
    ['label' => $t('layout.nav_calendar', 'Calendário'), 'route' => 'calendar/index', 'prefix' => 'calendar/', 'icon' => 'fa-solid fa-calendar-days'],
    ['label' => $t('layout.nav_plans', 'Planos editoriais'), 'route' => 'plans/index', 'prefix' => 'plans/', 'icon' => 'fa-solid fa-list-check'],
    ['label' => $t('layout.nav_social', 'Central social'), 'route' => 'social/index', 'prefix' => 'social/', 'icon' => 'fa-solid fa-share-nodes'],
];
if (!array_key_exists('tracking.campaign_links', $featureFlags) || !empty($featureFlags['tracking.campaign_links'])) {
    $navItems[] = ['label' => $t('layout.nav_tracking', 'Rastreamento'), 'route' => 'tracking/index', 'prefix' => 'tracking/', 'icon' => 'fa-solid fa-link'];
}
?>
<body class="<?= !empty($current_user) ? 'client-auth' : 'client-guest' ?>">
<div class="client-shell<?= empty($current_user) ? ' guest' : '' ?>">
    <?php if (!empty($current_user)): ?>
        <aside class="client-sidebar" id="clientSidebar">
            <a class="client-brand" href="<?= e(route_url('dashboard/index')) ?>">
                <img src="<?= e(asset_url('img/reamurcms.png')) ?>" alt="Planner">
                <span>
                    <strong><?= e($app_name ?? 'Solis') ?></strong>
                    <small><?= e($t('layout.workspace_subtitle', 'Workspace do cliente')) ?></small>
                </span>
            </a>

            <nav class="app-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $isActive = $currentRoute === strtolower($item['route']) || str_starts_with($currentRoute, strtolower($item['prefix']));
                    ?>
                    <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(route_url($item['route'])) ?>">
                        <span><i class="<?= e((string) ($item['icon'] ?? 'fa-solid fa-circle')) ?>"></i> <?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
    <?php endif; ?>

    <div class="client-main<?= empty($current_user) ? ' auth-only' : '' ?>">
        <?php if (!empty($current_user)): ?>
            <header class="app-header">
                <button type="button" class="icon-btn" id="clientSidebarToggle" aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>">
                    <span></span><span></span><span></span>
                </button>
                <div class="app-title">
                    <h1><?= e($title ?? $t('layout.header_title', 'Strategic Content Planner')) ?></h1>
                    <p><?= e($t('layout.header_subtitle', 'Planejamento anual, mensal e por período com camadas estratégicas.')) ?></p>
                </div>
                <div class="header-actions">
                    <span class="user-avatar"><?= e($userInitial) ?></span>
                    <div>
                        <strong><?= e($userName) ?></strong>
                        <form method="post" action="<?= e(route_url('language/save')) ?>" style="margin:2px 0 4px 0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                            <label for="clientLanguageCode" style="font-size:12px;opacity:.9">
                                <?= e($t('layout.language_label', 'Language')) ?>
                            </label>
                            <select id="clientLanguageCode" name="language_code" onchange="this.form.submit()" style="margin-left:4px">
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

        <main class="app-content">
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
    var toggle = document.getElementById('clientSidebarToggle');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        document.body.classList.toggle('client-sidebar-open');
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 980) {
            return;
        }

        var sidebar = document.getElementById('clientSidebar');
        if (!sidebar) {
            return;
        }

        var clickedInsideSidebar = sidebar.contains(event.target);
        var clickedToggle = toggle.contains(event.target);
        if (!clickedInsideSidebar && !clickedToggle) {
            document.body.classList.remove('client-sidebar-open');
        }
    });
});
</script>
</body>
</html>
