<section class="panel">
    <h1><?= e($title) ?></h1>

    <form method="post" action="<?= e(route_url($action)) ?>" class="form-grid">
        <?= csrf_field() ?>

        <label><?= e($t('commemoratives.field_name', 'Nome')) ?>
            <input type="text" name="name" value="<?= e($item['name'] ?? '') ?>" required>
        </label>

        <label><?= e($t('commemoratives.field_date', 'Data')) ?>
            <input type="date" name="event_date" value="<?= e($item['event_date'] ?? date('Y-m-d')) ?>" required>
        </label>

        <label><?= e($t('commemoratives.field_context', 'Contexto')) ?>
            <?php $context = $item['context_type'] ?? 'editorial'; ?>
            <select name="context_type">
                <option value="commercial" <?= $context === 'commercial' ? 'selected' : '' ?>><?= e($t('common.context_commercial', 'Comercial')) ?></option>
                <option value="institutional" <?= $context === 'institutional' ? 'selected' : '' ?>><?= e($t('common.context_institutional', 'Institucional')) ?></option>
                <option value="seasonal" <?= $context === 'seasonal' ? 'selected' : '' ?>><?= e($t('common.context_seasonal', 'Sazonal')) ?></option>
                <option value="editorial" <?= $context === 'editorial' ? 'selected' : '' ?>><?= e($t('common.context_editorial', 'Editorial')) ?></option>
            </select>
        </label>

        <label><?= e($t('commemoratives.field_recurrence', 'Recorrência')) ?>
            <?php $recurrence = $item['recurrence_type'] ?? 'yearly'; ?>
            <select name="recurrence_type">
                <option value="yearly" <?= $recurrence === 'yearly' ? 'selected' : '' ?>><?= e($t('common.recurrence_yearly', 'Anual')) ?></option>
                <option value="none" <?= $recurrence === 'none' ? 'selected' : '' ?>><?= e($t('common.recurrence_single', 'Única')) ?></option>
            </select>
        </label>

        <label><?= e($t('commemoratives.field_country_code', 'País (ISO2)')) ?>
            <input type="text" name="country_code" value="<?= e($item['country_code'] ?? 'BR') ?>" maxlength="2">
        </label>

        <label><?= e($t('commemoratives.field_status', 'Status')) ?>
            <?php $status = (int) ($item['status'] ?? 1); ?>
            <select name="status">
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= e($t('common.status_active', 'Ativo')) ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= e($t('common.status_inactive', 'Inativo')) ?></option>
            </select>
        </label>

        <label class="full"><?= e($t('commemoratives.field_description', 'Descrição')) ?>
            <textarea name="description" rows="4"><?= e($item['description'] ?? '') ?></textarea>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
    </form>
</section>
