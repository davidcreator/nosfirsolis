<section class="auth-card auth-card-split auth-card-client">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-rocket"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($t('auth.heading_register', 'Criar conta gratuita')) ?></h2>
        <p><?= e($t('auth.description_register', 'Cadastre-se para iniciar com o plano Basico Gratuito e evoluir para Bronze, Prata ou Ouro quando precisar de mais escala.')) ?></p>
    </div>

    <ul class="auth-feature-list">
        <li><i class="fa-solid fa-gift"></i> <?= e($t('auth.feature_register_free', 'Comece no plano gratuito sem cartao')) ?></li>
        <li><i class="fa-solid fa-shield-halved"></i> <?= e($t('auth.feature_register_secure', 'Conta protegida com autenticacao e controles de acesso')) ?></li>
        <li><i class="fa-solid fa-chart-line"></i> <?= e($t('auth.feature_register_upgrade', 'Upgrade de plano direto no painel de faturamento')) ?></li>
    </ul>

    <form method="post" action="<?= e(route_url('auth/createAccount')) ?>" class="auth-form">
        <?= csrf_field() ?>

        <label><?= e($t('auth.field_name', 'Nome')) ?>
            <input type="text" name="name" autocomplete="name" required>
        </label>

        <label><?= e($t('auth.field_email', 'E-mail')) ?>
            <input type="email" name="email" autocomplete="email" required>
        </label>

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="new-password" required>
        </label>

        <label><?= e($t('auth.field_password_confirmation', 'Confirmar senha')) ?>
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>

        <button type="submit"><i class="fa-solid fa-user-plus"></i> <?= e($t('auth.button_register', 'Criar conta e iniciar')) ?></button>
    </form>

    <p class="auth-help-link">
        <?= e($t('auth.already_have_account', 'Ja possui conta?')) ?>
        <a href="<?= e(route_url('auth/login')) ?>"><?= e($t('auth.link_login', 'Entrar')) ?></a>
    </p>
</section>
