<label>Canal
    <select name="channel_id">
        <option value="">Todos</option>
        <?php foreach (($filter_data['channels'] ?? []) as $channel): ?>
            <option value="<?= (int) $channel['id'] ?>" <?= (string) ($filters['channel_id'] ?? '') === (string) $channel['id'] ? 'selected' : '' ?>><?= e($channel['name']) ?></option>
        <?php endforeach; ?>
    </select>
</label>

<label>Objetivo
    <select name="objective_id">
        <option value="">Todos</option>
        <?php foreach (($filter_data['objectives'] ?? []) as $objective): ?>
            <option value="<?= (int) $objective['id'] ?>" <?= (string) ($filters['objective_id'] ?? '') === (string) $objective['id'] ? 'selected' : '' ?>><?= e($objective['name']) ?></option>
        <?php endforeach; ?>
    </select>
</label>

<label>Campanha
    <select name="campaign_id">
        <option value="">Todas</option>
        <?php foreach (($filter_data['campaigns'] ?? []) as $campaign): ?>
            <option value="<?= (int) $campaign['id'] ?>" <?= (string) ($filters['campaign_id'] ?? '') === (string) $campaign['id'] ? 'selected' : '' ?>><?= e($campaign['name']) ?></option>
        <?php endforeach; ?>
    </select>
</label>

<input type="hidden" name="show_holiday_national" value="0">
<label class="check"><input type="checkbox" name="show_holiday_national" value="1" <?= (int) ($filters['show_holiday_national'] ?? 1) === 1 ? 'checked' : '' ?>> Feriados nacionais</label>
<input type="hidden" name="show_holiday_regional" value="0">
<label class="check"><input type="checkbox" name="show_holiday_regional" value="1" <?= (int) ($filters['show_holiday_regional'] ?? 1) === 1 ? 'checked' : '' ?>> Feriados regionais</label>
<input type="hidden" name="show_holiday_international" value="0">
<label class="check"><input type="checkbox" name="show_holiday_international" value="1" <?= (int) ($filters['show_holiday_international'] ?? 1) === 1 ? 'checked' : '' ?>> Feriados internacionais</label>
<input type="hidden" name="show_commemoratives" value="0">
<label class="check"><input type="checkbox" name="show_commemoratives" value="1" <?= (int) ($filters['show_commemoratives'] ?? 1) === 1 ? 'checked' : '' ?>> Datas comemorativas</label>
<input type="hidden" name="show_suggestions" value="0">
<label class="check"><input type="checkbox" name="show_suggestions" value="1" <?= (int) ($filters['show_suggestions'] ?? 1) === 1 ? 'checked' : '' ?>> Sugestoes estrategicas</label>
<input type="hidden" name="show_base_events" value="0">
<label class="check"><input type="checkbox" name="show_base_events" value="1" <?= (int) ($filters['show_base_events'] ?? 1) === 1 ? 'checked' : '' ?>> Eventos base (Excel)</label>

<button type="submit"><i class="fa-solid fa-filter"></i> Aplicar filtros</button>
