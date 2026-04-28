<section class="panel">
    <h1><i class="fa-solid fa-lightbulb"></i> <?= e($title) ?></h1>

    <form method="post" action="<?= e(route_url($action)) ?>" class="form-grid">
        <?= csrf_field() ?>

        <label class="full"><?= e($t('suggestions.field_title', 'Título')) ?>
            <input type="text" name="title" value="<?= e($item['title'] ?? '') ?>" required>
        </label>

        <label><?= e($t('suggestions.field_date', 'Data da sugestão')) ?>
            <input type="date" name="suggestion_date" value="<?= e($item['suggestion_date'] ?? date('Y-m-d')) ?>" required>
        </label>

        <label><?= e($t('suggestions.field_format', 'Formato')) ?>
            <input type="text" name="format_type" value="<?= e($item['format_type'] ?? 'imagem') ?>" placeholder="<?= e($t('suggestions.placeholder_format', 'imagem, reel, short...')) ?>" required>
        </label>

        <label><?= e($t('suggestions.field_category', 'Categoria')) ?>
            <select name="content_category_id">
                <option value=""><?= e($t('common.option_select', 'Selecione')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($item['content_category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= e($t('suggestions.field_pillar', 'Pilar')) ?>
            <select name="content_pillar_id">
                <option value=""><?= e($t('common.option_select', 'Selecione')) ?></option>
                <?php foreach ($pillars as $pillar): ?>
                    <option value="<?= (int) $pillar['id'] ?>" <?= (int) ($item['content_pillar_id'] ?? 0) === (int) $pillar['id'] ? 'selected' : '' ?>><?= e($pillar['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= e($t('suggestions.field_objective', 'Objetivo')) ?>
            <select name="content_objective_id">
                <option value=""><?= e($t('common.option_select', 'Selecione')) ?></option>
                <?php foreach ($objectives as $objective): ?>
                    <option value="<?= (int) $objective['id'] ?>" <?= (int) ($item['content_objective_id'] ?? 0) === (int) $objective['id'] ? 'selected' : '' ?>><?= e($objective['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= e($t('suggestions.field_campaign', 'Campanha')) ?>
            <select name="campaign_id">
                <option value=""><?= e($t('suggestions.option_no_campaign', 'Sem campanha')) ?></option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int) $campaign['id'] ?>" <?= (int) ($item['campaign_id'] ?? 0) === (int) $campaign['id'] ? 'selected' : '' ?>><?= e($campaign['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= e($t('suggestions.field_recurrence', 'Recorrência')) ?>
            <?php $recurrence = $item['recurrence_type'] ?? 'yearly'; ?>
            <select name="recurrence_type">
                <option value="yearly" <?= $recurrence === 'yearly' ? 'selected' : '' ?>><?= e($t('common.recurrence_yearly', 'Anual')) ?></option>
                <option value="monthly" <?= $recurrence === 'monthly' ? 'selected' : '' ?>><?= e($t('common.recurrence_monthly', 'Mensal')) ?></option>
                <option value="none" <?= $recurrence === 'none' ? 'selected' : '' ?>><?= e($t('common.recurrence_single', 'Única')) ?></option>
            </select>
        </label>

        <label><?= e($t('suggestions.field_is_recurring', 'Repetir automaticamente')) ?>
            <?php $isRecurring = (int) ($item['is_recurring'] ?? 1); ?>
            <select name="is_recurring">
                <option value="1" <?= $isRecurring === 1 ? 'selected' : '' ?>><?= e($t('common.yes', 'Sim')) ?></option>
                <option value="0" <?= $isRecurring === 0 ? 'selected' : '' ?>><?= e($t('common.no', 'Não')) ?></option>
            </select>
        </label>

        <label><?= e($t('suggestions.field_context', 'Contexto')) ?>
            <?php $context = $item['context_type'] ?? 'editorial'; ?>
            <select name="context_type">
                <option value="commercial" <?= $context === 'commercial' ? 'selected' : '' ?>><?= e($t('common.context_commercial', 'Comercial')) ?></option>
                <option value="institutional" <?= $context === 'institutional' ? 'selected' : '' ?>><?= e($t('common.context_institutional', 'Institucional')) ?></option>
                <option value="seasonal" <?= $context === 'seasonal' ? 'selected' : '' ?>><?= e($t('common.context_seasonal', 'Sazonal')) ?></option>
                <option value="editorial" <?= $context === 'editorial' ? 'selected' : '' ?>><?= e($t('common.context_editorial', 'Editorial')) ?></option>
            </select>
        </label>

        <label><?= e($t('suggestions.field_status', 'Status')) ?>
            <?php $status = (int) ($item['status'] ?? 1); ?>
            <select name="status">
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= e($t('common.status_active', 'Ativo')) ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= e($t('common.status_inactive', 'Inativo')) ?></option>
            </select>
        </label>

        <label><?= e($t('suggestions.field_channel_priority', 'Prioridade de canais')) ?>
            <input type="text" name="channel_priority" value="<?= e($item['channel_priority'] ?? '') ?>" placeholder="<?= e($t('suggestions.placeholder_channel_priority', 'instagram, youtube, blog')) ?>">
        </label>

        <fieldset class="full">
            <legend><?= e($t('suggestions.legend_channels', 'Canais associados')) ?></legend>
            <div class="chips-grid">
                <?php foreach ($platforms as $platform): ?>
                    <label class="chip">
                        <input type="checkbox" name="channels[]" value="<?= (int) $platform['id'] ?>" <?= in_array((int) $platform['id'], $selected_channels, true) ? 'checked' : '' ?>>
                        <?= e($platform['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <label class="full"><?= e($t('suggestions.field_description', 'Descrição')) ?>
            <textarea name="description" rows="5"><?= e($item['description'] ?? '') ?></textarea>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
    </form>
</section>
