<section class="auth-card auth-card-split auth-card-client">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-key"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($t('auth.heading_reset_password', 'Definir nova senha')) ?></h2>
        <p>
            <?= e($t('auth.description_reset_password', 'Crie uma nova senha para continuar acessando sua conta.')) ?>
            <?php if (!empty($reset_email_masked)): ?>
                <strong><?= e((string) $reset_email_masked) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <form method="post" action="<?= e(route_url('auth/updatepassword')) ?>" class="auth-form auth-form-recovery">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e((string) ($reset_token ?? '')) ?>">

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="new-password" required minlength="8">
        </label>

        <label><?= e($t('auth.field_password_confirmation', 'Confirmar senha')) ?>
            <input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">
        </label>

        <button type="submit"><i class="fa-solid fa-unlock-keyhole"></i> <?= e($t('auth.button_reset_password', 'Redefinir senha')) ?></button>
    </form>

    <p class="auth-help-link">
        <a href="<?= e(route_url('auth/login')) ?>"><?= e($t('auth.link_back_to_login', 'Voltar para login')) ?></a>
    </p>
</section>
