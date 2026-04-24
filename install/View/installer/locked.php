<section class="card">
    <?php $root = rtrim(dirname(base_path_url()), '/'); ?>
    <h1><?= e($title ?? $t('install.locked_title', 'Instalador protegido')) ?></h1>
    <p class="subtitle"><?= e($message ?? $t('install.locked_short_message', 'Reinstalação bloqueada.')) ?></p>
    <p class="hint">
        <?= e($t('install.locked_hint_prefix', 'Acesse o sistema normalmente em')) ?>
        <a href="<?= e($root . '/client') ?>"><?= e($t('install.locked_hint_client', 'cliente')) ?></a>
        <?= e($t('install.locked_hint_or', 'ou')) ?>
        <a href="<?= e($root . '/admin') ?>"><?= e($t('install.locked_hint_admin', 'admin')) ?></a>.
    </p>
</section>
