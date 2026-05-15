<?php
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
$currentLanguageFlag = $languageFlagMap[$currentLanguageCode] ?? '&#x1F310;';
?>
<section class="auth-card auth-card-split auth-card-admin">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-user-shield"></i> <?= e($t('auth.badge', '{app} Admin', ['app' => ($app_name ?? 'Solis')])) ?></span>
        <h1><i class="fa-solid fa-lock"></i> <?= e($t('auth.heading', 'Acesso administrativo')) ?></h1>
        <p><?= e($t('auth.description', 'Area protegida para governanca do sistema, curadoria de dados e controle de niveis hierarquicos de usuarios.')) ?></p>
    </div>

    <ul class="auth-feature-list">
        <li><i class="fa-solid fa-sitemap"></i> <?= e($t('auth.feature_hierarchy', 'Controle de hierarquia de grupos administrativos')) ?></li>
        <li><i class="fa-solid fa-users-gear"></i> <?= e($t('auth.feature_users_permissions', 'Gestao de usuarios e permissoes por nivel')) ?></li>
        <li><i class="fa-solid fa-shield-halved"></i> <?= e($t('auth.feature_security_audit', 'Acesso monitorado com trilha de seguranca')) ?></li>
    </ul>

    <div class="auth-language-switcher">
        <span class="auth-language-title"><i class="fa-solid fa-language"></i> <?= e($t('auth.language_switcher_label', 'Idioma')) ?></span>
        <details class="language-dropdown auth-language-dropdown">
            <summary class="language-dropdown-toggle" title="<?= e($t('auth.language_switcher_label', 'Idioma')) ?>" aria-label="<?= e($t('auth.language_switcher_label', 'Idioma')) ?>">
                <span class="auth-language-current-flag"><?= $currentLanguageFlag ?></span>
                <span class="auth-language-current-code"><?= e($currentLanguageCode) ?></span>
                <i class="fa-solid fa-angle-down" aria-hidden="true"></i>
            </summary>
            <div class="language-dropdown-menu">
                <div class="language-dropdown-header">
                    <span><i class="fa-solid fa-language"></i> <?= e($t('auth.language_menu_title', 'Traduzir')) ?></span>
                    <span aria-hidden="true">&times;</span>
                </div>
                <div class="language-dropdown-body">
                    <p class="language-dropdown-label"><?= e($t('auth.language_menu_languages', 'Idiomas')) ?></p>
                    <?php foreach ($supportedLanguages as $languageOption): ?>
                        <?php
                        $languageOptionCode = strtolower((string) ($languageOption['code'] ?? ''));
                        if ($languageOptionCode === '') {
                            continue;
                        }

                        $languageOptionFlag = $languageFlagMap[$languageOptionCode] ?? '&#x1F310;';
                        ?>
                        <form method="post" action="<?= e(route_url('language/save')) ?>" class="language-option-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect_route" value="auth/login">
                            <button type="submit" class="language-option<?= $currentLanguageCode === $languageOptionCode ? ' is-active' : '' ?>" name="language_code" value="<?= e($languageOptionCode) ?>">
                                <span class="language-option-flag"><?= $languageOptionFlag ?></span>
                                <span class="language-option-name"><?= e($languageOptionCode) ?></span>
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    </div>

    <form method="post" action="<?= e(route_url('auth/authenticate')) ?>" class="auth-form">
        <?= csrf_field() ?>
        <label><?= e($t('auth.field_login', 'E-mail ou usuario')) ?>
            <input type="text" name="email" autocomplete="username" required>
        </label>

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <p class="auth-inline-link">
            <a href="<?= e((string) ($password_recovery_url ?? '')) ?>"><?= e($t('auth.link_recover_password', 'Esqueci minha senha')) ?></a>
            &nbsp;|&nbsp;
            <a href="<?= e((string) ($email_recovery_url ?? '')) ?>"><?= e($t('auth.link_recover_email', 'Esqueci meu e-mail')) ?></a>
        </p>

        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> <?= e($t('auth.button_login', 'Entrar no admin')) ?></button>
    </form>
</section>