<?php
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$weekNames = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];
$annualQuery = http_build_query([
    'mode' => 'annual',
    'year' => (int) $year,
    'channel_id' => (string) ($filters['channel_id'] ?? ''),
    'objective_id' => (string) ($filters['objective_id'] ?? ''),
    'campaign_id' => (string) ($filters['campaign_id'] ?? ''),
    'show_holiday_national' => (string) ($filters['show_holiday_national'] ?? 1),
    'show_holiday_regional' => (string) ($filters['show_holiday_regional'] ?? 1),
    'show_holiday_international' => (string) ($filters['show_holiday_international'] ?? 1),
    'show_commemoratives' => (string) ($filters['show_commemoratives'] ?? 1),
    'show_suggestions' => (string) ($filters['show_suggestions'] ?? 1),
    'show_base_events' => (string) ($filters['show_base_events'] ?? 1),
]);
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-table-cells-large"></i> Calendar Hub</span>
        <h2><i class="fa-solid fa-calendar-days"></i> Calendário anual</h2>
        <p>Visão consolidada de janeiro a dezembro com marcadores de feriados, campanhas e observações.</p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#calendar-annual-grid"><i class="fa-solid fa-table-cells-large"></i> Ver meses</a>
        <a class="btn" href="<?= e(route_url('calendar/index?' . $annualQuery)) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir visão principal</a>
    </div>
</section>

<section class="panel" id="calendar-annual-grid">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-calendar"></i> Ano <?= (int) $year ?></h3>
        <span class="meta-text">12 meses consolidados</span>
    </div>

    <form method="get" class="filters-grid">
        <input type="hidden" name="route" value="calendar/annual">
        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

    <div class="annual-grid">
        <?php foreach ($months as $month): ?>
            <article class="month-card">
                <h3><?= e($monthNames[(int) $month['month']] ?? '') ?></h3>
                <div class="table-wrap">
                    <table class="mini-calendar">
                        <thead>
                        <tr>
                            <?php foreach ($weekNames as $weekName): ?>
                                <th><?= e($weekName) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($month['weeks'] as $week): ?>
                            <tr>
                                <?php foreach ($week as $day): ?>
                                    <?php $dayCellClass = !empty($day['in_month']) ? 'day in' : 'day out'; ?>
                                    <td class="<?= e($dayCellClass) ?>">
                                        <?php if ($day['in_month']): ?>
                                            <span class="day-number"><?= (int) $day['day'] ?></span>
                                            <div class="day-markers">
                                                <?php $events = $day['events'] ?? []; ?>
                                                <?php foreach (($events['holidays'] ?? []) as $holiday): ?>
                                                    <?php $holidayType = strtolower((string) ($holiday['holiday_type'] ?? 'national')); ?>
                                                    <?php if ($holidayType === 'international'): ?><span class="mk holiday-international" title="Feriado internacional"></span><?php endif; ?>
                                                    <?php if ($holidayType === 'national'): ?><span class="mk holiday-national" title="Feriado nacional"></span><?php endif; ?>
                                                    <?php if ($holidayType === 'regional'): ?><span class="mk holiday-regional" title="Feriado regional"></span><?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php if (!empty($events['commemoratives'])): ?><span class="mk commemorative" title="Comemorativa"></span><?php endif; ?>
                                                <?php if (!empty($events['campaigns'])): ?><span class="mk campaign" title="Campanha"></span><?php endif; ?>
                                                <?php if (!empty($events['suggestions'])): ?><span class="mk suggestion" title="Sugestao"></span><?php endif; ?>
                                                <?php if (!empty($events['base_events'])): ?><span class="mk base-event" title="Evento base"></span><?php endif; ?>
                                                <?php if (!empty($events['extra_events'])): ?><span class="mk extra-event" title="Evento extra"></span><?php endif; ?>
                                                <?php if (!empty($events['notes'])): ?><span class="mk note" title="Observacao"></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

