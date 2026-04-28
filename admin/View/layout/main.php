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
    ['label' => $t('layout.nav_billing', 'Planos e Pagamentos'), 'route' => 'billing/index', 'prefix' => 'billing/', 'icon' => 'fa-solid fa-credit-card'],
    ['label' => $t('layout.nav_users', 'Usuários e Hierarquia'), 'route' => 'users/index', 'prefix' => 'users/', 'icon' => 'fa-solid fa-users'],
    ['label' => $t('layout.nav_operations', 'Operações'), 'route' => 'operations/index', 'prefix' => 'operations/', 'icon' => 'fa-solid fa-gears'],
];

$topbarTools = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'icon' => 'fa-solid fa-gauge-high'],
    ['label' => $t('layout.nav_billing', 'Planos e Pagamentos'), 'route' => 'billing/index', 'icon' => 'fa-solid fa-credit-card'],
    ['label' => $t('layout.nav_operations', 'Operações'), 'route' => 'operations/index', 'icon' => 'fa-solid fa-gears'],
];

$showTopNotice = $currentRoute === 'dashboard/index' || str_starts_with($currentRoute, 'dashboard/');
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

            <div class="sidebar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="adminSidebarSearch" placeholder="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>" aria-label="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>">
            </div>
            <p class="sidebar-section-label"><?= e($t('layout.main_navigation', 'Navegação')) ?></p>

            <nav class="sidebar-nav">
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

    <div class="main-content-wrapper<?= empty($current_user) ? ' auth-only' : '' ?>">
        <?php if (!empty($current_user)): ?>
            <header class="topbar">
                <button type="button" class="icon-btn" id="sidebarToggle" aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>" aria-controls="adminSidebar" aria-expanded="true">
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <strong><?= e($title ?? $t('layout.topbar_title', 'Painel Administrativo')) ?></strong>
                    <small><?= e($t('layout.topbar_subtitle', 'Gestão administrativa e hierarquia de acesso do {app}', ['app' => ($app_name ?? 'Solis')])) ?></small>
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
                            <form method="post" action="<?= e(route_url('language/save')) ?>" class="topbar-language-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                                <label for="adminLanguageCode"><?= e($t('layout.language_label', 'Idioma')) ?></label>
                                <select id="adminLanguageCode" name="language_code" onchange="this.form.submit()">
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

        <main class="main-content">
            <?php if (!empty($current_user) && $showTopNotice): ?>
                <section class="topbar-notice">
                    <span><i class="fa-solid fa-bolt"></i> <?= e($t('layout.dashboard_notice_text', 'Novidade: revise planos, promoções e validações de pagamento no módulo de billing.')) ?></span>
                    <a href="<?= e(route_url('billing/index')) ?>"><?= e($t('layout.dashboard_notice_cta', 'Abrir Billing')) ?></a>
                </section>
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
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    var sidebarSearch = document.getElementById('adminSidebarSearch');
    var navLinks = document.querySelectorAll('.sidebar-nav a');
    var collapseClass = 'sidebar-collapsed';
    var overlayClass = 'sidebar-open';
    var mobileBreakpoint = 980;
    var autoCollapseBreakpoint = 1366;
    var storageKey = 'solis.admin.sidebar.collapsed';
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
            if (cell.querySelector('.status-pill, .billing-plan-status, .invoice-status')) {
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

    if (toggle) {
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

    window.addEventListener('resize', function () {
        syncSidebarState();
    });

    syncSidebarState();
    applyStatusPills(document);
});
</script>
</body>
</html>
