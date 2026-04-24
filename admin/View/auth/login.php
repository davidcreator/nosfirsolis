<section class="auth-card auth-card-split auth-card-admin">
    <div class="auth-head">
        <span class="auth-badge"><i class="fa-solid fa-user-shield"></i> <?= e($t('auth.badge', '{app} Admin', ['app' => ($app_name ?? 'Solis')])) ?></span>
        <h1><?= e($t('auth.heading', 'Acesso administrativo')) ?></h1>
        <p><?= e($t('auth.description', 'Área protegida para governança do sistema, curadoria de dados e controle de níveis hierárquicos de usuários.')) ?></p>
    </div>

    <ul class="auth-feature-list">
        <li><i class="fa-solid fa-sitemap"></i> <?= e($t('auth.feature_hierarchy', 'Controle de hierarquia de grupos administrativos')) ?></li>
        <li><i class="fa-solid fa-users-gear"></i> <?= e($t('auth.feature_users_permissions', 'Gestão de usuários e permissões por nível')) ?></li>
        <li><i class="fa-solid fa-shield-halved"></i> <?= e($t('auth.feature_security_audit', 'Acesso monitorado com trilha de segurança')) ?></li>
    </ul>

    <form method="post" action="<?= e(route_url('auth/authenticate')) ?>" class="auth-form">
        <?= csrf_field() ?>
        <label><?= e($t('auth.field_email', 'E-mail')) ?>
            <input type="email" name="email" autocomplete="username" required>
        </label>

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> <?= e($t('auth.button_login', 'Entrar no admin')) ?></button>
    </form>
</section>
