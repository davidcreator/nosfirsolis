<section class="panel forbidden">
    <h1><?= e($title ?? $t('common.access_denied_title', 'Acesso negado')) ?></h1>
    <p><?= e($message ?? $t('common.access_denied_short', 'Sem permissão para este recurso.')) ?></p>
</section>
