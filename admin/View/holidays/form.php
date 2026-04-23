<section class="panel">
    <h1><?= e($title) ?></h1>

    <form method="post" action="<?= e(route_url($action)) ?>" class="form-grid">
        <?= csrf_field() ?>

        <label><?= e($t('holidays.field_name', 'Nome')) ?>
            <input type="text" name="name" value="<?= e($holiday['name'] ?? '') ?>" required>
        </label>

        <label><?= e($t('holidays.field_date', 'Data')) ?>
            <input type="date" name="holiday_date" value="<?= e($holiday['holiday_date'] ?? date('Y-m-d')) ?>" required>
        </label>

        <label><?= e($t('holidays.field_type', 'Tipo')) ?>
            <select name="holiday_type">
                <?php $holidayType = $holiday['holiday_type'] ?? 'national'; ?>
                <option value="national" <?= $holidayType === 'national' ? 'selected' : '' ?>><?= e($t('holidays.type_national', 'Nacional')) ?></option>
                <option value="regional" <?= $holidayType === 'regional' ? 'selected' : '' ?>><?= e($t('holidays.type_regional', 'Regional')) ?></option>
                <option value="international" <?= $holidayType === 'international' ? 'selected' : '' ?>><?= e($t('holidays.type_international', 'Internacional')) ?></option>
            </select>
        </label>

        <label><?= e($t('holidays.field_region', 'Regiao')) ?>
            <select name="holiday_region_id">
                <option value=""><?= e($t('holidays.option_no_region', 'Sem regiao')) ?></option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= (int) $region['id'] ?>" <?= (int) ($holiday['holiday_region_id'] ?? 0) === (int) $region['id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= e($t('holidays.field_country_code', 'Pais (ISO2)')) ?>
            <input type="text" name="country_code" value="<?= e($holiday['country_code'] ?? 'BR') ?>" maxlength="2">
        </label>

        <label><?= e($t('holidays.field_state_code', 'Estado')) ?>
            <input type="text" name="state_code" value="<?= e($holiday['state_code'] ?? '') ?>" maxlength="12">
        </label>

        <label><?= e($t('holidays.field_is_fixed', 'Data fixa')) ?>
            <select name="is_fixed">
                <?php $isFixed = (int) ($holiday['is_fixed'] ?? 1); ?>
                <option value="1" <?= $isFixed === 1 ? 'selected' : '' ?>><?= e($t('common.yes', 'Sim')) ?></option>
                <option value="0" <?= $isFixed === 0 ? 'selected' : '' ?>><?= e($t('common.no', 'Nao')) ?></option>
            </select>
        </label>

        <label><?= e($t('holidays.field_is_movable', 'Data movel')) ?>
            <select name="is_movable">
                <?php $isMovable = (int) ($holiday['is_movable'] ?? 0); ?>
                <option value="0" <?= $isMovable === 0 ? 'selected' : '' ?>><?= e($t('common.no', 'Nao')) ?></option>
                <option value="1" <?= $isMovable === 1 ? 'selected' : '' ?>><?= e($t('common.yes', 'Sim')) ?></option>
            </select>
        </label>

        <label><?= e($t('holidays.field_movable_rule', 'Regra movel')) ?>
            <input type="text" name="movable_rule" value="<?= e($holiday['movable_rule'] ?? '') ?>" placeholder="<?= e($t('holidays.placeholder_movable_rule', 'Ex.: easter+60')) ?>">
        </label>

        <label><?= e($t('holidays.field_status', 'Status')) ?>
            <?php $status = (int) ($holiday['status'] ?? 1); ?>
            <select name="status">
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= e($t('common.status_active', 'Ativo')) ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= e($t('common.status_inactive', 'Inativo')) ?></option>
            </select>
        </label>

        <label class="full"><?= e($t('holidays.field_description', 'Descricao')) ?>
            <textarea name="description" rows="4"><?= e($holiday['description'] ?? '') ?></textarea>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
    </form>
</section>
