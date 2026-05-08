<section class="auth-card auth-card-split auth-card-client">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-envelope-circle-check"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($t('auth.heading_forgot_password', 'Recuperar conta ou senha')) ?></h2>
        <p><?= e($t('auth.description_forgot_password', 'Informe o e-mail cadastrado para receber um link seguro de recuperação.')) ?></p>
    </div>

    <form method="post" action="<?= e(route_url('auth/sendpasswordreset')) ?>" class="auth-form auth-form-recovery">
        <?= csrf_field() ?>

        <label><?= e($t('auth.field_email', 'E-mail')) ?>
            <input type="email" name="email" autocomplete="email" required>
        </label>

        <button type="submit"><i class="fa-solid fa-paper-plane"></i> <?= e($t('auth.button_send_recovery', 'Enviar link de recuperação')) ?></button>
    </form>

    <p class="auth-help-link">
        <a href="<?= e(route_url('auth/login')) ?>"><?= e($t('auth.link_back_to_login', 'Voltar para login')) ?></a>
        &nbsp;|&nbsp;
        <a href="<?= e(route_url('auth/forgotemail')) ?>"><?= e($t('auth.link_recover_email', 'Esqueci meu e-mail')) ?></a>
        &nbsp;|&nbsp;
        <a href="<?= e(route_url('auth/register')) ?>"><?= e($t('auth.link_register', 'Criar conta gratuita')) ?></a>
    </p>
</section>
