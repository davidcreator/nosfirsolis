<?php
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$weekNames = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];
$currentYear = (int) $year;
$currentMonth = max(1, min(12, (int) $month));
$monthStart = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));
$filterParams = [
    'channel_id' => (string) ($filters['channel_id'] ?? ''),
    'objective_id' => (string) ($filters['objective_id'] ?? ''),
    'campaign_id' => (string) ($filters['campaign_id'] ?? ''),
    'show_holiday_national' => (string) ($filters['show_holiday_national'] ?? 1),
    'show_holiday_regional' => (string) ($filters['show_holiday_regional'] ?? 1),
    'show_holiday_international' => (string) ($filters['show_holiday_international'] ?? 1),
    'show_commemoratives' => (string) ($filters['show_commemoratives'] ?? 1),
    'show_suggestions' => (string) ($filters['show_suggestions'] ?? 1),
    'show_base_events' => (string) ($filters['show_base_events'] ?? 1),
];
$previousYear = $currentMonth === 1 ? $currentYear - 1 : $currentYear;
$previousMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
$nextYear = $currentMonth === 12 ? $currentYear + 1 : $currentYear;
$nextMonth = $currentMonth === 12 ? 1 : $currentMonth + 1;

if ($previousYear < 1970) {
    $previousYear = $currentYear;
    $previousMonth = $currentMonth;
}

if ($nextYear > 2100) {
    $nextYear = $currentYear;
    $nextMonth = $currentMonth;
}

$previousQuery = http_build_query(array_merge(['year' => $previousYear, 'month' => $previousMonth], $filterParams));
$nextQuery = http_build_query(array_merge(['year' => $nextYear, 'month' => $nextMonth], $filterParams));
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-calendar-week"></i> Calendar Hub</span>
        <h2><i class="fa-solid fa-calendar-week"></i> Calendario mensal</h2>
        <p>Detalhe diario do periodo com contexto de eventos estrategicos, campanhas e observacoes.</p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#calendar-month-grid"><i class="fa-solid fa-table"></i> Ver grade mensal</a>
        <a class="btn" href="<?= e(route_url('calendar/index?mode=monthly&year=' . $currentYear . '&month=' . $currentMonth)) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir visao principal</a>
    </div>
</section>

<section class="panel" id="calendar-month-grid">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-calendar-day"></i> <?= e($monthNames[$currentMonth] ?? '') ?> / <?= $currentYear ?></h2>
        <div class="inline-links">
            <a href="<?= e(route_url('calendar/monthly?' . $previousQuery)) ?>">Mes anterior</a>
            <a href="<?= e(route_url('calendar/monthly?' . $nextQuery)) ?>">Proximo mes</a>
        </div>
    </div>

    <form method="get" class="filters-grid">
        <input type="hidden" name="route" value="calendar/monthly">
        <label>Ano
            <input type="number" name="year" value="<?= $currentYear ?>" min="1970" max="2100">
        </label>
        <label>Mes
            <input type="number" name="month" value="<?= $currentMonth ?>" min="1" max="12">
        </label>
        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

    <div class="table-wrap">
        <table class="full-calendar">
            <thead>
            <tr>
                <?php foreach ($weekNames as $weekName): ?>
                    <th><?= e($weekName) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($calendar['weeks'] as $week): ?>
                <tr>
                    <?php foreach ($week as $day): ?>
                        <?php $dayClass = !empty($day['in_month']) ? 'in' : 'out'; ?>
                        <td class="<?= e($dayClass) ?>">
                            <?php if ($day['in_month']): ?>
                                <span class="day-number"><?= (int) $day['day'] ?></span>
                                <?php $events = $day['events'] ?? []; ?>

                                <?php foreach (($events['holidays'] ?? []) as $holiday): ?>
                                    <?php $holidayType = strtolower((string) ($holiday['holiday_type'] ?? 'national')); ?>
                                    <div class="tag holiday-<?= e($holidayType) ?>">
                                        <?= $holidayType === 'international' ? 'Feriado internacional:' : ($holidayType === 'regional' ? 'Feriado regional:' : 'Feriado nacional:') ?>
                                        <?= e($holiday['name']) ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach (($events['commemoratives'] ?? []) as $item): ?>
                                    <div class="tag commemorative">Comemorativa: <?= e($item['name']) ?></div>
                                <?php endforeach; ?>

                                <?php foreach (($events['campaigns'] ?? []) as $campaign): ?>
                                    <div class="tag campaign">Campanha: <?= e($campaign['name']) ?></div>
                                <?php endforeach; ?>

                                <?php foreach (($events['suggestions'] ?? []) as $suggestion): ?>
                                    <div class="tag suggestion">Sugestao: <?= e($suggestion['title']) ?></div>
                                <?php endforeach; ?>

                                <?php foreach (($events['base_events'] ?? []) as $event): ?>
                                    <div class="tag base-event" title="<?= e((string) ($event['description'] ?? '')) ?>">Base: <?= e($event['title']) ?></div>
                                <?php endforeach; ?>

                                <?php foreach (($events['extra_events'] ?? []) as $event): ?>
                                    <div class="tag extra-event" style="<?= !empty($event['color_hex']) ? 'background:' . e($event['color_hex']) . ';' : '' ?>">
                                        Extra: <?= e($event['title']) ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach (($events['notes'] ?? []) as $note): ?>
                                    <div class="tag note">Nota: <?= e($note['note_text']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" id="calendar-notes-monthly">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-note-sticky"></i> Registrar observacao manual por dia</h3>
        <span class="meta-text">Mes ativo: <?= e($monthNames[$currentMonth] ?? '') ?> / <?= $currentYear ?></span>
    </div>

    <form method="post" action="<?= e(route_url('calendar/saveNote')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="return_mode" value="monthly">
        <input type="hidden" name="return_year" value="<?= $currentYear ?>">
        <input type="hidden" name="return_month" value="<?= $currentMonth ?>">
        <input type="hidden" name="return_start_date" value="<?= e($monthStart) ?>">
        <input type="hidden" name="return_end_date" value="<?= e($monthEnd) ?>">
        <input type="hidden" name="return_channel_id" value="<?= e((string) ($filters['channel_id'] ?? '')) ?>">
        <input type="hidden" name="return_objective_id" value="<?= e((string) ($filters['objective_id'] ?? '')) ?>">
        <input type="hidden" name="return_campaign_id" value="<?= e((string) ($filters['campaign_id'] ?? '')) ?>">
        <input type="hidden" name="return_show_holiday_national" value="<?= e((string) ($filters['show_holiday_national'] ?? 1)) ?>">
        <input type="hidden" name="return_show_holiday_regional" value="<?= e((string) ($filters['show_holiday_regional'] ?? 1)) ?>">
        <input type="hidden" name="return_show_holiday_international" value="<?= e((string) ($filters['show_holiday_international'] ?? 1)) ?>">
        <input type="hidden" name="return_show_commemoratives" value="<?= e((string) ($filters['show_commemoratives'] ?? 1)) ?>">
        <input type="hidden" name="return_show_suggestions" value="<?= e((string) ($filters['show_suggestions'] ?? 1)) ?>">
        <input type="hidden" name="return_show_base_events" value="<?= e((string) ($filters['show_base_events'] ?? 1)) ?>">

        <label>Data
            <input type="date" name="note_date" required>
        </label>
        <label>Contexto
            <select name="context_type">
                <option value="commercial">Comercial</option>
                <option value="institutional">Institucional</option>
                <option value="seasonal">Sazonal</option>
                <option value="editorial">Editorial</option>
            </select>
        </label>
        <label class="wide">Observacao
            <input type="text" name="note_text" placeholder="Ex.: alinhar pauta com campanha de leads" required>
        </label>
        <button type="submit"><i class="fa-solid fa-note-sticky"></i> Salvar observacao</button>
    </form>
</section>

