<section class="panel forbidden">
    <div class="panel-head-inline">
        <h1><i class="fa-solid fa-ban"></i> <?= e($title ?? $t('common.access_denied_title', 'Acesso negado')) ?></h1>
        <a class="btn btn-muted" href="<?= e(route_url('dashboard/index')) ?>"><i class="fa-solid fa-arrow-left"></i> Voltar ao painel</a>
    </div>
    <p><?= e($message ?? $t('common.access_denied_short', 'Sem permissao para este recurso.')) ?></p>
</section>
