<?php
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$requestHost = preg_replace('/[^a-z0-9\.\-:\[\]]/i', '', $requestHost) ?? 'localhost';
$requestHost = trim($requestHost) !== '' ? $requestHost : 'localhost';
$rootPath = rtrim(dirname(base_path_url()), '/');
if ($rootPath === '.' || $rootPath === '/') {
    $rootPath = '';
}
$faviconPath = $rootPath . '/image/solis.png';
$logoPath = $rootPath . '/image/solis_logo_wt.png';
$ogImagePath = $rootPath . '/image/solis_og_1200x630.png';
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? (base_path_url() . '/'));
$requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? (base_path_url() . '/'));
$pageUrl = $requestScheme . '://' . $requestHost . $requestUri;
$canonicalUrl = $requestScheme . '://' . $requestHost . $requestPath;
$brandName = (string) ($app_name ?? $t('layout.title_default', 'Solis'));
$pageTitle = trim((string) ($title ?? ''));
$metaTitle = $brandName;
if ($pageTitle !== '') {
    $metaTitle = stripos($pageTitle, $brandName) !== false
        ? $pageTitle
        : ($pageTitle . ' | ' . $brandName);
}
$metaDescription = (string) $t('layout.topbar_subtitle', 'GestÃƒÆ’Ã‚Â£o administrativa e hierarquia de acesso do {app}', ['app' => $brandName]);
$metaRobots = 'noindex, nofollow, noarchive';
$ogImageUrl = $requestScheme . '://' . $requestHost . $ogImagePath;
$adminCssHref = (string) asset_url('css/admin.css');
$adminCssPath = dirname(__DIR__, 2) . '/assets/css/admin.css';
if (is_file($adminCssPath)) {
    $adminCssVersion = (string) @filemtime($adminCssPath);
    if ($adminCssVersion !== '') {
        $adminCssHref .= (str_contains($adminCssHref, '?') ? '&' : '?') . 'v=' . rawurlencode($adminCssVersion);
    }
}
$languageCode = strtolower((string) ($language_code ?? 'en-us'));
$ogLocale = str_replace('-', '_', $languageCode);
if ($ogLocale === 'pt_br') {
    $ogLocale = 'pt_BR';
} elseif ($ogLocale === 'en_us') {
    $ogLocale = 'en_US';
}
?>
<!doctype html>
<html lang="<?= e($language_code ?? 'en-us') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="<?= e($metaRobots) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <link rel="icon" type="image/png" href="<?= e($faviconPath) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= e($ogLocale) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <meta property="og:image" content="<?= e($ogImageUrl) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e('Solis - Painel administrativo') ?>">
    <meta property="og:site_name" content="<?= e($brandName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="twitter:url" content="<?= e($pageUrl) ?>">
    <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e($adminCssHref) ?>">
</head>
<?php
$currentRoute = strtolower(trim((string) ($current_route ?? '')));
if ($currentRoute === '') {
    $currentRoute = 'dashboard/index';
}

$userName = (string) ($current_user['name'] ?? '');
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));
$currentLanguageCode = strtolower((string) ($language_code ?? 'en-us'));
$supportedLanguages = (array) ($supported_languages ?? []);
if ($supportedLanguages === []) {
    $supportedLanguages = [
        ['code' => 'en-us'],
        ['code' => 'pt-br'],
    ];
}
$languageFlagMap = [
    'en-us' => '&#x1F1FA;&#x1F1F8;',
    'pt-br' => '&#x1F1E7;&#x1F1F7;',
];

$navItems = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'prefix' => 'dashboard/', 'icon' => 'fa-solid fa-chart-line'],
    ['label' => $t('layout.nav_holidays', 'Feriados'), 'route' => 'holidays/index', 'prefix' => 'holidays/', 'icon' => 'fa-solid fa-calendar-day'],
    ['label' => $t('layout.nav_commemoratives', 'Comemorativas'), 'route' => 'commemoratives/index', 'prefix' => 'commemoratives/', 'icon' => 'fa-solid fa-star'],
    ['label' => $t('layout.nav_suggestions', 'SugestÃƒÆ’Ã‚Âµes'), 'route' => 'suggestions/index', 'prefix' => 'suggestions/', 'icon' => 'fa-solid fa-lightbulb'],
    ['label' => $t('layout.nav_channels', 'Canais'), 'route' => 'channels/index', 'prefix' => 'channels/', 'icon' => 'fa-solid fa-share-nodes'],
    ['label' => $t('layout.nav_campaigns', 'Campanhas'), 'route' => 'campaigns/index', 'prefix' => 'campaigns/', 'icon' => 'fa-solid fa-bullhorn'],
    ['label' => $t('layout.nav_plans_campaigns', 'Planos e Campanhas IA'), 'route' => 'plans_campaigns/index', 'prefix' => 'plans_campaigns/', 'icon' => 'fa-solid fa-diagram-project'],
    ['label' => $t('layout.nav_billing', 'Planos e Pagamentos'), 'route' => 'billing/index', 'prefix' => 'billing/', 'icon' => 'fa-solid fa-credit-card'],
    ['label' => $t('layout.nav_users', 'UsuÃƒÆ’Ã‚Â¡rios e Hierarquia'), 'route' => 'users/index', 'prefix' => 'users/', 'icon' => 'fa-solid fa-users'],
    ['label' => $t('layout.nav_operations', 'OperaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Âµes'), 'route' => 'operations/index', 'prefix' => 'operations/', 'icon' => 'fa-solid fa-gears'],
];

$topbarTools = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'icon' => 'fa-solid fa-gauge-high'],
    ['label' => $t('layout.nav_plans_campaigns', 'Planos e Campanhas IA'), 'route' => 'plans_campaigns/index', 'icon' => 'fa-solid fa-diagram-project'],
    ['label' => $t('layout.nav_billing', 'Planos e Pagamentos'), 'route' => 'billing/index', 'icon' => 'fa-solid fa-credit-card'],
    ['label' => $t('layout.nav_operations', 'OperaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Âµes'), 'route' => 'operations/index', 'icon' => 'fa-solid fa-gears'],
];

$showTopNotice = $currentRoute === 'dashboard/index' || str_starts_with($currentRoute, 'dashboard/');
$isGuestLoginRoute = $currentRoute === 'auth/login';
?>
<body class="<?= !empty($current_user) ? 'admin-auth' : 'admin-guest' ?>">
<div class="admin-shell<?= empty($current_user) ? ' guest' : '' ?>">
    <?php if (!empty($current_user)): ?>
        <aside class="sidebar" id="adminSidebar">
            <a class="sidebar-brand" href="<?= e(route_url('dashboard/index')) ?>">
                <img src="<?= e($logoPath) ?>" alt="<?= e($app_name ?? 'Solis') ?>">
                <span>
                    <strong><?= e($app_name ?? 'Solis') ?></strong>
                    <small><?= e($t('layout.panel_subtitle', 'Painel Administrativo')) ?></small>
                </span>
            </a>

            <div class="sidebar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="adminSidebarSearch" placeholder="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>" aria-label="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>">
            </div>
            <p class="sidebar-section-label"><?= e($t('layout.main_navigation', 'NavegaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o')) ?></p>

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
        <?php if (empty($current_user) && !$isGuestLoginRoute): ?>
            <div class="guest-language-switcher">
                <details class="language-dropdown language-dropdown-guest">
                    <summary class="language-dropdown-toggle" title="<?= e($t('layout.language_label', 'Idioma')) ?>" aria-label="<?= e($t('layout.language_label', 'Idioma')) ?>">
                        <i class="fa-solid fa-language"></i>
                    </summary>
                    <div class="language-dropdown-menu">
                        <div class="language-dropdown-header">
                            <span><i class="fa-solid fa-language"></i> <?= e($t('layout.language_menu_title', 'Traduzir')) ?></span>
                            <span aria-hidden="true">&times;</span>
                        </div>
                        <div class="language-dropdown-body">
                            <p class="language-dropdown-label"><?= e($t('layout.language_menu_languages', 'Idiomas')) ?></p>
                            <?php foreach ($supportedLanguages as $languageOption): ?>
                                <?php
                                $languageOptionCode = strtolower((string) ($languageOption['code'] ?? ''));
                                if ($languageOptionCode === '') {
                                    continue;
                                }

                                $languageOptionName = $languageOptionCode;
                                $languageOptionFlag = $languageFlagMap[$languageOptionCode] ?? '&#x1F310;';
                                ?>
                                <form method="post" action="<?= e(route_url('language/save')) ?>" class="language-option-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                                    <button type="submit" class="language-option<?= $currentLanguageCode === $languageOptionCode ? ' is-active' : '' ?>" name="language_code" value="<?= e($languageOptionCode) ?>">
                                        <span class="language-option-flag"><?= $languageOptionFlag ?></span>
                                        <span class="language-option-name"><?= e($languageOptionName) ?></span>
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            </div>
        <?php endif; ?>

        <?php if (!empty($current_user)): ?>
            <header class="topbar">
                <button
                    type="button"
                    class="icon-btn"
                    id="sidebarToggle"
                    aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>"
                    data-open-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>"
                    data-close-label="<?= e($t('layout.close_menu', 'Fechar menu')) ?>"
                    aria-controls="adminSidebar"
                    aria-expanded="true"
                >
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <strong><?= e($title ?? $t('layout.topbar_title', 'Painel Administrativo')) ?></strong>
                    <small><?= e($t('layout.topbar_subtitle', 'GestÃƒÆ’Ã‚Â£o administrativa e hierarquia de acesso do {app}', ['app' => ($app_name ?? 'Solis')])) ?></small>
                </div>
                <div class="topbar-actions">
                    <nav class="topbar-tools" aria-label="<?= e($t('layout.shortcuts_label', 'Atalhos')) ?>">
                        <?php foreach ($topbarTools as $tool): ?>
                            <?php
                            $toolRoute = strtolower((string) ($tool['route'] ?? ''));
                            $toolPrefix = strtok($toolRoute . '/', '/') . '/';
                            $toolActive = $currentRoute === $toolRoute || str_starts_with($currentRoute, $toolPrefix);
                            ?>
                            <a class="<?= $toolActive ? 'is-active' : '' ?>" href="<?= e(route_url((string) ($tool['route'] ?? 'dashboard/index'))) ?>" title="<?= e((string) ($tool['label'] ?? $t('layout.shortcut_label', 'Atalho'))) ?>">
                                <i class="<?= e((string) ($tool['icon'] ?? 'fa-solid fa-circle')) ?>"></i>
                                <span><?= e((string) ($tool['label'] ?? $t('layout.shortcut_label', 'Atalho'))) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="topbar-user">
                        <span class="user-avatar"><?= e($userInitial) ?></span>
                        <div class="topbar-user-meta">
                            <strong><?= e($userName) ?></strong>
                            <details class="language-dropdown">
                                <summary class="language-dropdown-toggle" title="<?= e($t('layout.language_label', 'Idioma')) ?>" aria-label="<?= e($t('layout.language_label', 'Idioma')) ?>">
                                    <i class="fa-solid fa-language"></i>
                                </summary>
                                <div class="language-dropdown-menu">
                                    <div class="language-dropdown-header">
                                        <span><i class="fa-solid fa-language"></i> <?= e($t('layout.language_menu_title', 'Traduzir')) ?></span>
                                        <span aria-hidden="true">ÃƒÆ’Ã¢â‚¬â€</span>
                                    </div>
                                    <div class="language-dropdown-body">
                                        <p class="language-dropdown-label"><?= e($t('layout.language_menu_languages', 'Idiomas')) ?></p>
                                        <?php foreach ($supportedLanguages as $languageOption): ?>
                                            <?php
                                            $languageOptionCode = strtolower((string) ($languageOption['code'] ?? ''));
                                            if ($languageOptionCode === '') {
                                                continue;
                                            }

                                            $languageOptionName = $languageOptionCode;
                                            $languageOptionFlag = $languageFlagMap[$languageOptionCode] ?? '&#x1F310;';
                                            ?>
                                            <form method="post" action="<?= e(route_url('language/save')) ?>" class="language-option-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_route" value="<?= e($currentRoute) ?>">
                                                <button type="submit" class="language-option<?= $currentLanguageCode === $languageOptionCode ? ' is-active' : '' ?>" name="language_code" value="<?= e($languageOptionCode) ?>">
                                                    <span class="language-option-flag"><?= $languageOptionFlag ?></span>
                                                    <span class="language-option-name"><?= e($languageOptionName) ?></span>
                                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </details>
                            <form method="post" action="<?= e(route_url('auth/logout')) ?>" class="topbar-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="topbar-logout-btn" title="<?= e($t('layout.logout', 'Sair')) ?>" aria-label="<?= e($t('layout.logout', 'Sair')) ?>">
                                    <i class="fa-solid fa-right-from-bracket"></i>
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
                    <span><i class="fa-solid fa-bolt"></i> <?= e($t('layout.dashboard_notice_text', 'Novidade: revise planos, promoÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Âµes e validaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Âµes de pagamento no mÃƒÆ’Ã‚Â³dulo de billing.')) ?></span>
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
    var mobileBreakpoint = 900;
    var autoCollapseBreakpoint = 1366;
    var mobileLockClass = 'mobile-menu-lock';
    var storageKey = 'solis.admin.sidebar.collapsed';
    var openMenuLabel = toggle ? (toggle.getAttribute('data-open-label') || 'Abrir menu') : 'Abrir menu';
    var closeMenuLabel = toggle ? (toggle.getAttribute('data-close-label') || 'Fechar menu') : 'Fechar menu';
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
        'em anÃƒÆ’Ã‚Â¡lise': 'status-processing',
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

    function setScrollLock(locked) {
        document.documentElement.classList.toggle(mobileLockClass, locked);
        document.body.classList.toggle(mobileLockClass, locked);
    }

    function setMobileSidebarOpen(open) {
        if (!isMobileViewport()) {
            return;
        }

        document.body.classList.toggle(overlayClass, open);
        setScrollLock(open);
        updateToggleA11y();
    }

    function updateToggleA11y() {
        if (!toggle) {
            return;
        }

        var expanded = isMobileViewport()
            ? document.body.classList.contains(overlayClass)
            : !document.body.classList.contains(collapseClass);

        var mobileOpen = isMobileViewport() && document.body.classList.contains(overlayClass);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.setAttribute('aria-label', mobileOpen ? closeMenuLabel : openMenuLabel);
        toggle.classList.toggle('is-active', mobileOpen);
    }

    function syncSidebarState() {
        if (isMobileViewport()) {
            document.body.classList.remove(collapseClass);
            document.body.classList.remove(overlayClass);
            setScrollLock(false);
            updateToggleA11y();
            return;
        }

        document.body.classList.remove(overlayClass);
        setScrollLock(false);

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
                setMobileSidebarOpen(!document.body.classList.contains(overlayClass));
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
                setMobileSidebarOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !isMobileViewport()) {
                return;
            }

            if (document.body.classList.contains(overlayClass)) {
                setMobileSidebarOpen(false);
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

        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileViewport()) {
                    setMobileSidebarOpen(false);
                }
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






