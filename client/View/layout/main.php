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
$subscriptionContext = (array) ($subscription_context ?? []);
$subscriptionPlan = (array) ($subscriptionContext['plan'] ?? []);
$subscriptionFeatures = (array) ($subscriptionContext['features'] ?? []);
$showAds = (bool) ($subscriptionContext['ads_enabled'] ?? false);

$userName = (string) ($current_user['name'] ?? '');
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));

$trackingEnabled =
    (!array_key_exists('tracking.campaign_links', $featureFlags) || !empty($featureFlags['tracking.campaign_links']))
    && (!array_key_exists('allow_tracking_links', $subscriptionFeatures) || !empty($subscriptionFeatures['allow_tracking_links']));

$navItems = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'prefix' => 'dashboard/', 'icon' => 'fa-solid fa-chart-pie'],
    ['label' => $t('layout.nav_calendar', 'Calendário'), 'route' => 'calendar/index', 'prefix' => 'calendar/', 'icon' => 'fa-solid fa-calendar-days'],
    ['label' => $t('layout.nav_plans', 'Planos editoriais'), 'route' => 'plans/index', 'prefix' => 'plans/', 'icon' => 'fa-solid fa-list-check'],
    ['label' => $t('layout.nav_social', 'Central social'), 'route' => 'social/index', 'prefix' => 'social/', 'icon' => 'fa-solid fa-share-nodes'],
    ['label' => $t('layout.nav_billing', 'Planos e faturamento'), 'route' => 'billing/index', 'prefix' => 'billing/', 'icon' => 'fa-solid fa-credit-card'],
];

if ($trackingEnabled) {
    $navItems[] = ['label' => $t('layout.nav_tracking', 'Rastreamento'), 'route' => 'tracking/index', 'prefix' => 'tracking/', 'icon' => 'fa-solid fa-link'];
}

$topbarTools = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'icon' => 'fa-solid fa-gauge-high'],
    ['label' => $t('layout.nav_billing', 'Planos e faturamento'), 'route' => 'billing/index', 'icon' => 'fa-solid fa-credit-card'],
];

if ($trackingEnabled) {
    $topbarTools[] = ['label' => $t('layout.nav_tracking', 'Rastreamento'), 'route' => 'tracking/index', 'icon' => 'fa-solid fa-link'];
}

$showTopNotice = $currentRoute === 'dashboard/index' || str_starts_with($currentRoute, 'dashboard/');
?>
<body class="<?= !empty($current_user) ? 'client-auth' : 'client-guest' ?>">
<div class="client-shell<?= empty($current_user) ? ' guest' : '' ?>">
    <?php if (!empty($current_user)): ?>
        <aside class="client-sidebar sidebar" id="clientSidebar">
            <a class="client-brand sidebar-brand" href="<?= e(route_url('dashboard/index')) ?>">
                <img src="<?= e(asset_url('img/reamurcms.png')) ?>" alt="Planner">
                <span>
                    <strong><?= e($app_name ?? 'Solis') ?></strong>
                    <small><?= e($t('layout.workspace_subtitle', 'Workspace do cliente')) ?></small>
                </span>
            </a>

            <div class="sidebar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="clientSidebarSearch" placeholder="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>" aria-label="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>">
            </div>
            <p class="sidebar-section-label"><?= e($t('layout.main_navigation', 'Navegação')) ?></p>

            <nav class="app-nav sidebar-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $isActive = $currentRoute === strtolower((string) $item['route']) || str_starts_with($currentRoute, strtolower((string) $item['prefix']));
                    ?>
                    <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(route_url((string) $item['route'])) ?>" title="<?= e((string) ($item['label'] ?? '')) ?>">
                        <span><i class="<?= e((string) ($item['icon'] ?? 'fa-solid fa-circle')) ?>"></i> <?= e((string) ($item['label'] ?? '')) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <p><?= e($t('layout.connected_as', 'Conectado como')) ?></p>
                <strong><?= e($userName) ?></strong>
            </div>
        </aside>
    <?php endif; ?>

    <div class="client-main main-content-wrapper<?= empty($current_user) ? ' auth-only' : '' ?>">
        <?php if (!empty($current_user)): ?>
            <header class="app-header topbar">
                <button type="button" class="icon-btn" id="clientSidebarToggle" aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>" aria-controls="clientSidebar" aria-expanded="true">
                    <span></span><span></span><span></span>
                </button>
                <div class="app-title topbar-title">
                    <strong><?= e($title ?? $t('layout.header_title', 'Strategic Content Planner')) ?></strong>
                    <small><?= e($t('layout.header_subtitle', 'Planejamento anual, mensal e por período com camadas estratégicas.')) ?></small>
                </div>
                <div class="topbar-actions">
                    <nav class="topbar-tools" aria-label="Atalhos">
                        <?php foreach ($topbarTools as $tool): ?>
                            <?php
                            $toolRoute = strtolower((string) ($tool['route'] ?? ''));
                            $toolPrefix = strtok($toolRoute . '/', '/') . '/';
                            $toolActive = $currentRoute === $toolRoute || str_starts_with($currentRoute, $toolPrefix);
                            ?>
                            <a class="<?= $toolActive ? 'is-active' : '' ?>" href="<?= e(route_url((string) ($tool['route'] ?? 'dashboard/index'))) ?>" title="<?= e((string) ($tool['label'] ?? 'Atalho')) ?>">
                                <i class="<?= e((string) ($tool['icon'] ?? 'fa-solid fa-circle')) ?>"></i>
                                <span><?= e((string) ($tool['label'] ?? 'Atalho')) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="topbar-user">
                        <span class="user-avatar"><?= e($userInitial) ?></span>
                        <div class="topbar-user-meta">
                            <strong><?= e($userName) ?></strong>
                            <?php if (!empty($subscriptionPlan)): ?>
                                <small><?= e((string) ($subscriptionPlan['name'] ?? $t('layout.active_plan', 'Plano ativo'))) ?></small>
                            <?php endif; ?>
                            <form method="post" action="<?= e(route_url('language/save')) ?>" class="topbar-language-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                                <label for="clientLanguageCode"><?= e($t('layout.language_label', 'Language')) ?></label>
                                <select id="clientLanguageCode" name="language_code" onchange="this.form.submit()">
                                    <option value="pt-br" <?= strtolower((string) ($language_code ?? 'en-us')) === 'pt-br' ? 'selected' : '' ?>>
                                        <?= e($t('layout.language_option_pt_br', 'Português')) ?>
                                    </option>
                                    <option value="en-us" <?= strtolower((string) ($language_code ?? 'en-us')) === 'en-us' ? 'selected' : '' ?>>
                                        <?= e($t('layout.language_option_en_us', 'English')) ?>
                                    </option>
                                </select>
                            </form>
                            <form method="post" action="<?= e(route_url('auth/logout')) ?>" class="topbar-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="topbar-logout-btn">
                                    <i class="fa-solid fa-right-from-bracket"></i> <?= e($t('layout.logout', 'Sair')) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>
        <?php endif; ?>

        <main class="app-content main-content">
            <?php if (!empty($current_user) && $showTopNotice): ?>
                <section class="topbar-notice">
                    <span><i class="fa-solid fa-bolt"></i> <?= e($t('layout.dashboard_notice_text', 'Novidade: acompanhe seu calendário e acelere resultados com melhorias no faturamento.')) ?></span>
                    <a href="<?= e(route_url('billing/index')) ?>"><?= e($t('layout.dashboard_notice_cta', 'Abrir faturamento')) ?></a>
                </section>
            <?php endif; ?>

            <?php if (!empty($current_user) && $showAds): ?>
                <div class="alert">
                    <strong>Publicidade</strong>: você está no plano gratuito com anúncios.
                    <a href="<?= e(route_url('billing/index')) ?>">Fazer upgrade</a> para remover propaganda.
                </div>
            <?php endif; ?>

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
    var sidebar = document.getElementById('clientSidebar');
    var sidebarSearch = document.getElementById('clientSidebarSearch');
    var navLinks = document.querySelectorAll('.app-nav a');
    var collapseClass = 'client-sidebar-collapsed';
    var overlayClass = 'client-sidebar-open';
    var mobileBreakpoint = 640;
    var autoCollapseBreakpoint = 640;
    var storageKey = 'solis.client.sidebar.collapsed.v3';
    var statusClassMap = {
        'ativo': 'status-active',
        'ativa': 'status-active',
        'inativo': 'status-inactive',
        'inativa': 'status-inactive',
        'pendente': 'status-pending',
        'aprovado': 'status-approved',
        'aprovada': 'status-approved',
        'rejeitado': 'status-rejected',
        'rejeitada': 'status-rejected',
        'pago': 'status-paid',
        'paga': 'status-paid',
        'cancelado': 'status-cancelled',
        'cancelada': 'status-cancelled',
        'falhou': 'status-failed',
        'falha': 'status-failed',
        'processando': 'status-processing',
        'em analise': 'status-processing',
        'em análise': 'status-processing',
        'open': 'status-pending',
        'paid': 'status-paid',
        'failed': 'status-failed',
        'cancelled': 'status-cancelled'
    };

    function isMobileViewport() {
        return window.innerWidth <= mobileBreakpoint;
    }

    function getStoredCollapsePreference() {
        try {
            var stored = window.localStorage.getItem(storageKey);
            if (stored === '1' || stored === '0') {
                return stored;
            }
        } catch (error) {}

        return null;
    }

    function setStoredCollapsePreference(collapsed) {
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (error) {}
    }

    function updateToggleA11y() {
        if (!toggle) {
            return;
        }

        var expanded = isMobileViewport()
            ? document.body.classList.contains(overlayClass)
            : !document.body.classList.contains(collapseClass);

        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function syncSidebarState() {
        if (isMobileViewport()) {
            document.body.classList.remove(collapseClass);
            document.body.classList.remove(overlayClass);
            updateToggleA11y();
            return;
        }

        document.body.classList.remove(overlayClass);

        var stored = getStoredCollapsePreference();
        var shouldCollapse = stored !== null ? stored === '1' : window.innerWidth <= autoCollapseBreakpoint;
        document.body.classList.toggle(collapseClass, shouldCollapse);
        updateToggleA11y();
    }

    function applyStatusPills(scope) {
        var cells = (scope || document).querySelectorAll('table td');
        cells.forEach(function (cell) {
            if (cell.querySelector('.status-pill, .invoice-status')) {
                return;
            }

            if (cell.children.length > 0) {
                return;
            }

            var rawText = (cell.textContent || '').trim();
            if (rawText === '') {
                return;
            }

            var key = rawText.toLowerCase();
            if (!Object.prototype.hasOwnProperty.call(statusClassMap, key)) {
                return;
            }

            var pill = document.createElement('span');
            pill.className = 'status-pill ' + statusClassMap[key];
            pill.textContent = rawText;

            cell.textContent = '';
            cell.appendChild(pill);
        });
    }

    if (sidebarSearch && navLinks.length > 0) {
        sidebarSearch.addEventListener('input', function () {
            var query = sidebarSearch.value.trim().toLowerCase();
            navLinks.forEach(function (link) {
                var text = (link.textContent || '').toLowerCase();
                link.style.display = query === '' || text.indexOf(query) !== -1 ? '' : 'none';
            });
        });
    }

    if (!toggle) {
        syncSidebarState();
        applyStatusPills(document);
        return;
    }

    toggle.addEventListener('click', function () {
        if (isMobileViewport()) {
            document.body.classList.toggle(overlayClass);
            updateToggleA11y();
            return;
        }

        var collapsed = !document.body.classList.contains(collapseClass);
        document.body.classList.toggle(collapseClass, collapsed);
        setStoredCollapsePreference(collapsed);
        updateToggleA11y();
    });

    document.addEventListener('click', function (event) {
        if (!isMobileViewport()) {
            return;
        }

        if (!sidebar) {
            return;
        }

        var clickedInsideSidebar = sidebar.contains(event.target);
        var clickedToggle = toggle.contains(event.target);
        if (!clickedInsideSidebar && !clickedToggle) {
            document.body.classList.remove(overlayClass);
            updateToggleA11y();
        }
    });

    window.addEventListener('resize', function () {
        syncSidebarState();
    });

    syncSidebarState();
    applyStatusPills(document);
});
</script>
</body>
</html>
