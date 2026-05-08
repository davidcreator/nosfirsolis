<section class="auth-card auth-card-split auth-card-client">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-id-card"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($t('auth.heading_forgot_email', 'Lembrar e-mail de acesso')) ?></h2>
        <p><?= e($t('auth.description_forgot_email', 'Informe o e-mail de recuperacao cadastrado para receber um lembrete com os e-mails de acesso vinculados.')) ?></p>
    </div>

    <form method="post" action="<?= e(route_url('auth/sendemailrecovery')) ?>" class="auth-form auth-form-recovery">
        <?= csrf_field() ?>

        <label><?= e($t('auth.field_recovery_email', 'E-mail de recuperacao')) ?>
            <input type="email" name="recovery_email" autocomplete="email" required>
        </label>

        <button type="submit"><i class="fa-solid fa-envelope-open-text"></i> <?= e($t('auth.button_send_email_reminder', 'Enviar lembrete de acesso')) ?></button>
    </form>

    <p class="auth-help-link">
        <a href="<?= e(route_url('auth/login')) ?>"><?= e($t('auth.link_back_to_login', 'Voltar para login')) ?></a>
        &nbsp;|&nbsp;
        <a href="<?= e(route_url('auth/forgotpassword')) ?>"><?= e($t('auth.link_recover_password', 'Esqueci minha senha')) ?></a>
        &nbsp;|&nbsp;
        <a href="<?= e(route_url('auth/register')) ?>"><?= e($t('auth.link_register', 'Criar conta gratuita')) ?></a>
    </p>
</section>
