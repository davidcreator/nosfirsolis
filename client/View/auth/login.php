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
<section class="auth-card auth-card-split auth-card-client">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-sun"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($t('auth.heading', 'Acesso do usuario')) ?></h2>
        <p><?= e($t('auth.description', 'Entre para gerenciar planos editoriais, calendario estrategico e execucao de conteudo em um unico ambiente.')) ?></p>
    </div>

    <ul class="auth-feature-list">
        <li><i class="fa-solid fa-calendar-days"></i> <?= e($t('auth.feature_calendar', 'Calendario anual, mensal e por periodo')) ?></li>
        <li><i class="fa-solid fa-list-check"></i> <?= e($t('auth.feature_plans', 'Planos com status e atualizacao em lote')) ?></li>
        <li><i class="fa-solid fa-share-nodes"></i> <?= e($t('auth.feature_social', 'Central social com padroes de formatos')) ?></li>
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
        <label><?= e($t('auth.field_email', 'E-mail')) ?>
            <input type="email" name="email" autocomplete="username" required>
        </label>

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <p class="auth-inline-link">
            <a href="<?= e(route_url('auth/forgotpassword')) ?>"><?= e($t('auth.link_recover_password', 'Esqueci minha senha')) ?></a>
            &nbsp;|&nbsp;
            <a href="<?= e(route_url('auth/forgotemail')) ?>"><?= e($t('auth.link_recover_email', 'Esqueci meu e-mail')) ?></a>
        </p>

        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> <?= e($t('auth.button_login', 'Entrar no sistema')) ?></button>
    </form>

    <p class="auth-help-link">
        <?= e($t('auth.no_account_yet', 'Novo por aqui?')) ?>
        <a href="<?= e(route_url('auth/register')) ?>"><?= e($t('auth.link_register', 'Criar conta gratuita')) ?></a>
    </p>
</section>