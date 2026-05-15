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
$metaDescription = (string) $t('layout.header_subtitle', 'Planejamento anual, mensal e por perÃƒÂ­odo com camadas estratÃƒÂ©gicas.');
$metaRobots = !empty($current_user) ? 'noindex, nofollow, noarchive' : 'index, follow';
$ogImageUrl = $requestScheme . '://' . $requestHost . $ogImagePath;
$clientCssHref = (string) asset_url('css/client.css');
$clientCssPath = dirname(__DIR__, 2) . '/assets/css/client.css';
if (is_file($clientCssPath)) {
    $clientCssVersion = (string) @filemtime($clientCssPath);
    if ($clientCssVersion !== '') {
        $clientCssHref .= (str_contains($clientCssHref, '?') ? '&' : '?') . 'v=' . rawurlencode($clientCssVersion);
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
    <meta property="og:image:alt" content="<?= e('Solis - Planejamento estratÃƒÂ©gico e operaÃƒÂ§ÃƒÂ£o digital') ?>">
    <meta property="og:site_name" content="<?= e($brandName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="twitter:url" content="<?= e($pageUrl) ?>">
    <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e($clientCssHref) ?>">
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
$planDescriptor = strtolower(trim((string) (($subscriptionPlan['slug'] ?? '') !== '' ? $subscriptionPlan['slug'] : ($subscriptionPlan['name'] ?? ''))));
$planTier = 'free';

if ($planDescriptor !== '') {
    if (str_contains($planDescriptor, 'bronze')) {
        $planTier = 'bronze';
    } elseif (str_contains($planDescriptor, 'prata') || str_contains($planDescriptor, 'silver')) {
        $planTier = 'silver';
    } elseif (str_contains($planDescriptor, 'ouro') || str_contains($planDescriptor, 'gold')) {
        $planTier = 'gold';
    } elseif (!(str_contains($planDescriptor, 'gratuito') || str_contains($planDescriptor, 'free') || !empty($subscriptionPlan['is_free']))) {
        $planTier = 'custom';
    }
}

$trackingEnabled =
    (!array_key_exists('tracking.campaign_links', $featureFlags) || !empty($featureFlags['tracking.campaign_links']))
    && (!array_key_exists('allow_tracking_links', $subscriptionFeatures) || !empty($subscriptionFeatures['allow_tracking_links']));

$navItems = [
    ['label' => $t('layout.nav_dashboard', 'Dashboard'), 'route' => 'dashboard/index', 'prefix' => 'dashboard/', 'icon' => 'fa-solid fa-chart-pie'],
    ['label' => $t('layout.nav_calendar', 'CalendÃƒÂ¡rio'), 'route' => 'calendar/index', 'prefix' => 'calendar/', 'icon' => 'fa-solid fa-calendar-days'],
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
$isGuestLoginRoute = $currentRoute === 'auth/login';
?>
<body class="<?= !empty($current_user) ? 'client-auth' : 'client-guest' ?>">
<div class="client-shell<?= empty($current_user) ? ' guest' : '' ?>">
    <?php if (!empty($current_user)): ?>
        <aside class="client-sidebar sidebar" id="clientSidebar">
            <a class="client-brand sidebar-brand" href="<?= e(route_url('dashboard/index')) ?>">
                <img src="<?= e($logoPath) ?>" alt="<?= e($app_name ?? 'Solis') ?>">
                <span>
                    <strong><?= e($app_name ?? 'Solis') ?></strong>
                    <small><?= e($t('layout.workspace_subtitle', 'Workspace do cliente')) ?></small>
                </span>
            </a>

            <div class="sidebar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="clientSidebarSearch" placeholder="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>" aria-label="<?= e($t('layout.search_menu_placeholder', 'Buscar no menu')) ?>">
            </div>
            <p class="sidebar-section-label"><?= e($t('layout.main_navigation', 'NavegaÃƒÂ§ÃƒÂ£o')) ?></p>

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
        <?php if (empty($current_user)): ?>
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
            <header class="app-header topbar">
                <button
                    type="button"
                    class="icon-btn"
                    id="clientSidebarToggle"
                    aria-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>"
                    data-open-label="<?= e($t('layout.open_menu', 'Abrir menu')) ?>"
                    data-close-label="<?= e($t('layout.close_menu', 'Fechar menu')) ?>"
                    aria-controls="clientSidebar"
                    aria-expanded="true"
                >
                    <span></span><span></span><span></span>
                </button>
                <div class="app-title topbar-title">
                    <strong><?= e($title ?? $t('layout.header_title', 'Strategic Content Planner')) ?></strong>
                    <small><?= e($t('layout.header_subtitle', 'Planejamento anual, mensal e por perÃƒÂ­odo com camadas estratÃƒÂ©gicas.')) ?></small>
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
                            <?php if (!empty($subscriptionPlan)): ?>
                                <span class="topbar-plan-badge topbar-plan-<?= e($planTier) ?>" title="<?= e((string) ($subscriptionPlan['name'] ?? $t('layout.active_plan', 'Plano ativo'))) ?>">
                                    <i class="<?= $planTier === 'free' ? 'fa-regular fa-file' : 'fa-solid fa-file' ?>" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                            <details class="language-dropdown">
                                <summary class="language-dropdown-toggle" title="<?= e($t('layout.language_label', 'Idioma')) ?>" aria-label="<?= e($t('layout.language_label', 'Idioma')) ?>">
                                    <i class="fa-solid fa-language"></i>
                                </summary>
                                <div class="language-dropdown-menu">
                                    <div class="language-dropdown-header">
                                        <span><i class="fa-solid fa-language"></i> <?= e($t('layout.language_menu_title', 'Traduzir')) ?></span>
                                        <span aria-hidden="true">Ãƒâ€”</span>
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

        <main class="app-content main-content">
            <?php if (!empty($current_user) && $showTopNotice): ?>
                <section class="topbar-notice">
                    <span><i class="fa-solid fa-bolt"></i> <?= e($t('layout.dashboard_notice_text', 'Novidade: acompanhe seu calendÃƒÂ¡rio e acelere resultados com melhorias no faturamento.')) ?></span>
                    <a href="<?= e(route_url('billing/index')) ?>"><?= e($t('layout.dashboard_notice_cta', 'Abrir faturamento')) ?></a>
                </section>
            <?php endif; ?>

            <?php if (!empty($current_user) && $showAds): ?>
                <div class="alert">
                    <strong><?= e($t('layout.ads_notice_title', 'Publicidade')) ?></strong>: <?= e($t('layout.ads_notice_text', 'Voce esta no plano gratuito com anuncios.')) ?>
                    <a href="<?= e(route_url('billing/index')) ?>"><?= e($t('layout.ads_notice_upgrade', 'Fazer upgrade')) ?></a> <?= e($t('layout.ads_notice_suffix', 'para remover propaganda.')) ?>
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
    var mobileBreakpoint = 860;
    var autoCollapseBreakpoint = 640;
    var mobileLockClass = 'mobile-menu-lock';
    var storageKey = 'solis.client.sidebar.collapsed.v3';
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
        'em anÃƒÂ¡lise': 'status-processing',
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

        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileViewport()) {
                    setMobileSidebarOpen(false);
                }
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

    window.addEventListener('resize', function () {
        syncSidebarState();
    });

    syncSidebarState();
    applyStatusPills(document);
});
</script>
</body>
</html>




