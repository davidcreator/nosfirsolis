<section class="panel">
    <h1><i class="fa-solid fa-share-nodes"></i> <?= e($title) ?></h1>

    <form method="post" action="<?= e(route_url($action)) ?>" class="form-grid">
        <?= csrf_field() ?>

        <label><?= e($t('channels.field_name', 'Nome')) ?>
            <input type="text" name="name" value="<?= e($item['name'] ?? '') ?>" required>
        </label>

        <label><?= e($t('channels.field_type', 'Tipo')) ?>
            <?php $type = $item['platform_type'] ?? 'social'; ?>
            <select name="platform_type">
                <option value="social" <?= $type === 'social' ? 'selected' : '' ?>><?= e($t('channels.type_social', 'Social')) ?></option>
                <option value="video" <?= $type === 'video' ? 'selected' : '' ?>><?= e($t('channels.type_video', 'Vídeo')) ?></option>
                <option value="blog" <?= $type === 'blog' ? 'selected' : '' ?>><?= e($t('channels.type_blog', 'Blog')) ?></option>
                <option value="podcast" <?= $type === 'podcast' ? 'selected' : '' ?>><?= e($t('channels.type_podcast', 'Podcast')) ?></option>
                <option value="email" <?= $type === 'email' ? 'selected' : '' ?>><?= e($t('channels.type_email', 'E-mail')) ?></option>
                <option value="other" <?= $type === 'other' ? 'selected' : '' ?>><?= e($t('channels.type_other', 'Outro')) ?></option>
            </select>
        </label>

        <label><?= e($t('channels.field_source', 'Origem')) ?>
            <input type="text" name="source" value="<?= e($item['source'] ?? 'manual') ?>">
        </label>

        <label><?= e($t('channels.field_status', 'Status')) ?>
            <?php $status = (int) ($item['status'] ?? 1); ?>
            <select name="status">
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= e($t('common.status_active', 'Ativo')) ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= e($t('common.status_inactive', 'Inativo')) ?></option>
            </select>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
    </form>
</section>
