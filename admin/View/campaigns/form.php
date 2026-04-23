<section class="panel">
    <h1><?= e($title) ?></h1>

    <form method="post" action="<?= e(route_url($action)) ?>" class="form-grid">
        <?= csrf_field() ?>

        <label><?= e($t('campaigns.field_name', 'Nome')) ?>
            <input type="text" name="name" value="<?= e($item['name'] ?? '') ?>" required>
        </label>

        <label><?= e($t('campaigns.field_objective', 'Objetivo')) ?>
            <input type="text" name="objective" value="<?= e($item['objective'] ?? '') ?>">
        </label>

        <label><?= e($t('campaigns.field_start_date', 'Data inicial')) ?>
            <input type="date" name="start_date" value="<?= e($item['start_date'] ?? '') ?>">
        </label>

        <label><?= e($t('campaigns.field_end_date', 'Data final')) ?>
            <input type="date" name="end_date" value="<?= e($item['end_date'] ?? '') ?>">
        </label>

        <label><?= e($t('campaigns.field_status', 'Status')) ?>
            <?php $status = $item['status'] ?? 'planned'; ?>
            <select name="status">
                <option value="planned" <?= $status === 'planned' ? 'selected' : '' ?>><?= e($t('campaigns.status_planned', 'Planejada')) ?></option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e($t('campaigns.status_active', 'Ativa')) ?></option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>><?= e($t('campaigns.status_completed', 'Concluida')) ?></option>
                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= e($t('campaigns.status_archived', 'Arquivada')) ?></option>
            </select>
        </label>

        <label class="full"><?= e($t('campaigns.field_description', 'Descricao')) ?>
            <textarea name="description" rows="5"><?= e($item['description'] ?? '') ?></textarea>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
    </form>
</section>
