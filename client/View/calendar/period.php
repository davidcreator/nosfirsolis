<?php
$periodQuery = http_build_query([
    'mode' => 'period',
    'start_date' => (string) $start_date,
    'end_date' => (string) $end_date,
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
        <span class="hero-badge"><i class="fa-solid fa-timeline"></i> Calendar Hub</span>
        <h2><i class="fa-solid fa-timeline"></i> Calendario por periodo</h2>
        <p>Acompanhe janelas personalizadas para validar agenda, campanhas, sugestoes e observacoes do time.</p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#calendar-period-table"><i class="fa-solid fa-table"></i> Ver eventos</a>
        <a class="btn" href="<?= e(route_url('calendar/index?' . $periodQuery)) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir visao principal</a>
    </div>
</section>

<section class="panel" id="calendar-period-table">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-calendar-check"></i> Periodo personalizado</h2>
        <span class="meta-text"><?= e((string) $start_date) ?> ate <?= e((string) $end_date) ?></span>
    </div>

    <form method="get" class="filters-grid">
        <input type="hidden" name="route" value="calendar/period">
        <label>Data inicial
            <input type="date" name="start_date" value="<?= e($start_date) ?>" required>
        </label>
        <label>Data final
            <input type="date" name="end_date" value="<?= e($end_date) ?>" required>
        </label>
        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Data</th>
                <th>Feriados</th>
                <th>Comemorativas</th>
                <th>Sugestoes</th>
                <th>Eventos base</th>
                <th>Campanhas</th>
                <th>Eventos extras</th>
                <th>Observacoes</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr>
                    <td colspan="8">Nenhum evento encontrado para o periodo e filtros selecionados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($events as $date => $pack): ?>
                    <tr>
                        <td><?= e($date) ?></td>
                        <td>
                            <?php foreach (($pack['holidays'] ?? []) as $item): ?>
                                <?php $holidayType = strtolower((string) ($item['holiday_type'] ?? 'national')); ?>
                                <div class="tag holiday-<?= e($holidayType) ?>"><?= e($item['name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['commemoratives'] ?? []) as $item): ?>
                                <div class="tag commemorative"><?= e($item['name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['suggestions'] ?? []) as $item): ?>
                                <div class="tag suggestion"><?= e($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['base_events'] ?? []) as $item): ?>
                                <div class="tag base-event" title="<?= e((string) ($item['description'] ?? '')) ?>"><?= e($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['campaigns'] ?? []) as $item): ?>
                                <div class="tag campaign"><?= e($item['name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['extra_events'] ?? []) as $item): ?>
                                <div class="tag extra-event" style="<?= !empty($item['color_hex']) ? 'background:' . e($item['color_hex']) . ';' : '' ?>"><?= e($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach (($pack['notes'] ?? []) as $item): ?>
                                <div class="tag note"><?= e($item['note_text']) ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

