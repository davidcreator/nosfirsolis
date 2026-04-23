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

    <form method="post" action="<?= e(route_url('auth/authenticate')) ?>" class="auth-form">
        <?= csrf_field() ?>
        <label><?= e($t('auth.field_email', 'E-mail')) ?>
            <input type="email" name="email" autocomplete="username" required>
        </label>

        <label><?= e($t('auth.field_password', 'Senha')) ?>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> <?= e($t('auth.button_login', 'Entrar no sistema')) ?></button>
    </form>
</section>
