<?php
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$weekNames = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];

$returnFields = [
    'return_mode' => $mode,
    'return_year' => (string) $year,
    'return_month' => (string) $month,
    'return_start_date' => $start_date,
    'return_end_date' => $end_date,
    'return_channel_id' => (string) ($filters['channel_id'] ?? ''),
    'return_objective_id' => (string) ($filters['objective_id'] ?? ''),
    'return_campaign_id' => (string) ($filters['campaign_id'] ?? ''),
    'return_show_holiday_national' => (string) ($filters['show_holiday_national'] ?? 1),
    'return_show_holiday_regional' => (string) ($filters['show_holiday_regional'] ?? 1),
    'return_show_holiday_international' => (string) ($filters['show_holiday_international'] ?? 1),
    'return_show_commemoratives' => (string) ($filters['show_commemoratives'] ?? 1),
    'return_show_suggestions' => (string) ($filters['show_suggestions'] ?? 1),
    'return_show_base_events' => (string) ($filters['show_base_events'] ?? 1),
];

$modeAnnualQuery = http_build_query(array_merge(['mode' => 'annual', 'year' => $year], $filters));
$modeMonthlyQuery = http_build_query(array_merge(['mode' => 'monthly', 'year' => $year, 'month' => $month], $filters));
$modePeriodQuery = http_build_query(array_merge(['mode' => 'period', 'start_date' => $start_date, 'end_date' => $end_date], $filters));

$colors = $calendar_colors ?? [];
?>

<style>
    :root {
        --holiday-national: <?= e($colors['holiday_national'] ?? '#F43F5E') ?>;
        --holiday-international: <?= e($colors['holiday_international'] ?? '#2563EB') ?>;
        --holiday-regional: <?= e($colors['holiday_regional'] ?? '#EAB308') ?>;
        --commemorative-custom: <?= e($colors['commemorative'] ?? '#F59E0B') ?>;
        --suggestion-custom: <?= e($colors['suggestion'] ?? '#0E9F6E') ?>;
        --campaign-custom: <?= e($colors['campaign'] ?? '#1D4ED8') ?>;
        --base-event-custom: <?= e($colors['base_event'] ?? '#9333EA') ?>;
        --extra-event-custom: <?= e($colors['extra_event'] ?? '#9F3A03') ?>;
        --note-custom: <?= e($colors['note'] ?? '#6D28D9') ?>;
    }
</style>

<section class="panel">
    <div class="panel-head-inline">
        <h2>Calendario</h2>
        <div class="mode-switch">
            <a class="<?= $mode === 'annual' ? 'active' : '' ?>" href="<?= e(route_url('calendar/index?' . $modeAnnualQuery)) ?>">Anual</a>
            <a class="<?= $mode === 'monthly' ? 'active' : '' ?>" href="<?= e(route_url('calendar/index?' . $modeMonthlyQuery)) ?>">Mensal</a>
            <a class="<?= $mode === 'period' ? 'active' : '' ?>" href="<?= e(route_url('calendar/index?' . $modePeriodQuery)) ?>">Por periodo</a>
        </div>
    </div>

    <p class="calendar-subtitle">
        Base estratégica carregada de <?= e($excel_reference['workbook'] ?? '') ?> (aba <?= e($excel_reference['sheet'] ?? '') ?>, <?= (int) ($excel_reference['rows'] ?? 0) ?> registros).
    </p>

    <div class="legend-grid">
        <span class="legend-item"><span class="dot holiday-national"></span> Feriado nacional</span>
        <span class="legend-item"><span class="dot holiday-international"></span> Feriado internacional</span>
        <span class="legend-item"><span class="dot holiday-regional"></span> Feriado regional</span>
        <span class="legend-item"><span class="dot commemorative"></span> Comemorativa</span>
        <span class="legend-item"><span class="dot suggestion"></span> Sugestão</span>
        <span class="legend-item"><span class="dot campaign"></span> Campanha</span>
        <span class="legend-item"><span class="dot base-event"></span> Evento base</span>
        <span class="legend-item"><span class="dot extra-event"></span> Evento extra</span>
        <span class="legend-item"><span class="dot note"></span> Observação</span>
    </div>

    <form method="get" action="<?= e(route_url('calendar/index')) ?>" class="filters-grid">
        <input type="hidden" name="mode" value="<?= e($mode) ?>">

        <?php if ($mode === 'annual' || $mode === 'monthly'): ?>
            <label>Ano
                <input type="number" name="year" value="<?= (int) $year ?>" min="1970" max="2100">
            </label>
        <?php endif; ?>

        <?php if ($mode === 'monthly'): ?>
            <label>Mes
                <input type="number" name="month" value="<?= (int) $month ?>" min="1" max="12">
            </label>
        <?php endif; ?>

        <?php if ($mode === 'period'): ?>
            <label>Data inicial
                <input type="date" name="start_date" value="<?= e($start_date) ?>" required>
            </label>
            <label>Data final
                <input type="date" name="end_date" value="<?= e($end_date) ?>" required>
            </label>
        <?php endif; ?>

        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

    <?php if ($mode === 'annual'): ?>
        <div class="annual-grid">
            <?php foreach ($annual_months as $monthData): ?>
                <article class="month-card">
                    <h3><?= e($monthNames[(int) $monthData['month']] ?? '') ?></h3>
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
                            <?php foreach ($monthData['weeks'] as $week): ?>
                                <tr>
                                    <?php foreach ($week as $day): ?>
                                        <td class="<?= $day['in_month'] ? 'day in' : 'day out' ?>">
                                            <?php if ($day['in_month']): ?>
                                                <span class="day-number"><?= (int) $day['day'] ?></span>
                                                <div class="day-markers">
                                                    <?php $events = $day['events'] ?? []; ?>
                                                    <?php foreach (($events['holidays'] ?? []) as $holiday): ?>
                                                        <?php $ht = strtolower((string) ($holiday['holiday_type'] ?? 'national')); ?>
                                                        <?php if ($ht === 'international'): ?><span class="mk holiday-international" title="Feriado internacional"></span><?php endif; ?>
                                                        <?php if ($ht === 'national'): ?><span class="mk holiday-national" title="Feriado nacional"></span><?php endif; ?>
                                                        <?php if ($ht === 'regional'): ?><span class="mk holiday-regional" title="Feriado regional"></span><?php endif; ?>
                                                    <?php endforeach; ?>
                                                    <?php if (!empty($events['commemoratives'])): ?><span class="mk commemorative" title="Comemorativa"></span><?php endif; ?>
                                                    <?php if (!empty($events['campaigns'])): ?><span class="mk campaign" title="Campanha"></span><?php endif; ?>
                                                    <?php if (!empty($events['suggestions'])): ?><span class="mk suggestion" title="Sugestao"></span><?php endif; ?>
                                                    <?php if (!empty($events['base_events'])): ?><span class="mk base-event" title="Evento base"></span><?php endif; ?>
                                                    <?php if (!empty($events['notes'])): ?><span class="mk note" title="Observacao"></span><?php endif; ?>
                                                    <?php if (!empty($events['extra_events'])): ?><span class="mk extra-event" title="Evento extra"></span><?php endif; ?>
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
    <?php endif; ?>

    <?php if ($mode === 'monthly' && is_array($monthly_calendar)): ?>
        <div class="panel-head-inline section-head">
            <h3><?= e($monthNames[(int) $month] ?? '') ?> / <?= (int) $year ?></h3>
            <div class="inline-links">
                <a href="<?= e(route_url('calendar/index?' . http_build_query(array_merge(['mode' => 'monthly', 'year' => $year, 'month' => max(1, $month - 1)], $filters)))) ?>">Mes anterior</a>
                <a href="<?= e(route_url('calendar/index?' . http_build_query(array_merge(['mode' => 'monthly', 'year' => $year, 'month' => min(12, $month + 1)], $filters)))) ?>">Proximo mes</a>
            </div>
        </div>

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
                <?php foreach ($monthly_calendar['weeks'] as $week): ?>
                    <tr>
                        <?php foreach ($week as $day): ?>
                            <td class="<?= $day['in_month'] ? 'in' : 'out' ?>">
                                <?php if ($day['in_month']): ?>
                                    <span class="day-number"><?= (int) $day['day'] ?></span>
                                    <?php $events = $day['events'] ?? []; ?>

                                    <?php foreach (($events['holidays'] ?? []) as $holiday): ?>
                                        <?php $ht = strtolower((string) ($holiday['holiday_type'] ?? 'national')); ?>
                                        <div class="tag holiday-<?= e($ht) ?>">
                                            <?= $ht === 'international' ? 'Feriado internacional:' : ($ht === 'regional' ? 'Feriado regional:' : 'Feriado nacional:') ?>
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
                                        <div class="tag base-event" title="<?= e($event['description']) ?>">Base: <?= e($event['title']) ?></div>
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
    <?php endif; ?>

    <?php if ($mode === 'period'): ?>
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
                <?php foreach ($period_events as $date => $pack): ?>
                    <tr>
                        <td><?= e($date) ?></td>
                        <td>
                            <?php foreach (($pack['holidays'] ?? []) as $item): ?>
                                <?php $ht = strtolower((string) ($item['holiday_type'] ?? 'national')); ?>
                                <div class="tag holiday-<?= e($ht) ?>"><?= e($item['name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><?php foreach (($pack['commemoratives'] ?? []) as $item): ?><div class="tag commemorative"><?= e($item['name']) ?></div><?php endforeach; ?></td>
                        <td><?php foreach (($pack['suggestions'] ?? []) as $item): ?><div class="tag suggestion"><?= e($item['title']) ?></div><?php endforeach; ?></td>
                        <td><?php foreach (($pack['base_events'] ?? []) as $item): ?><div class="tag base-event"><?= e($item['title']) ?></div><?php endforeach; ?></td>
                        <td><?php foreach (($pack['campaigns'] ?? []) as $item): ?><div class="tag campaign"><?= e($item['name']) ?></div><?php endforeach; ?></td>
                        <td>
                            <?php foreach (($pack['extra_events'] ?? []) as $item): ?>
                                <div class="tag extra-event" style="<?= !empty($item['color_hex']) ? 'background:' . e($item['color_hex']) . ';' : '' ?>"><?= e($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><?php foreach (($pack['notes'] ?? []) as $item): ?><div class="tag note"><?= e($item['note_text']) ?></div><?php endforeach; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3>Personalizar cores do calendário</h3>
    <form method="post" action="<?= e(route_url('calendar/saveColors')) ?>" class="filters-grid colors-grid">
        <?= csrf_field() ?>
        <?php foreach ($returnFields as $field => $value): ?>
            <input type="hidden" name="<?= e($field) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>

        <label>Feriado nacional <input type="color" name="holiday_national" value="<?= e($colors['holiday_national'] ?? '#F43F5E') ?>"></label>
        <label>Feriado internacional <input type="color" name="holiday_international" value="<?= e($colors['holiday_international'] ?? '#2563EB') ?>"></label>
        <label>Feriado regional <input type="color" name="holiday_regional" value="<?= e($colors['holiday_regional'] ?? '#EAB308') ?>"></label>
        <label>Comemorativa <input type="color" name="commemorative" value="<?= e($colors['commemorative'] ?? '#F59E0B') ?>"></label>
        <label>Sugestão <input type="color" name="suggestion" value="<?= e($colors['suggestion'] ?? '#0E9F6E') ?>"></label>
        <label>Campanha <input type="color" name="campaign" value="<?= e($colors['campaign'] ?? '#1D4ED8') ?>"></label>
        <label>Evento base (Excel) <input type="color" name="base_event" value="<?= e($colors['base_event'] ?? '#9333EA') ?>"></label>
        <label>Evento extra <input type="color" name="extra_event" value="<?= e($colors['extra_event'] ?? '#9F3A03') ?>"></label>
        <label>Observação <input type="color" name="note" value="<?= e($colors['note'] ?? '#6D28D9') ?>"></label>
        <button type="submit"><i class="fa-solid fa-palette"></i> Salvar paleta</button>
    </form>
</section>

<section class="panel">
    <h3>Inserir evento extra</h3>
    <form method="post" action="<?= e(route_url('calendar/saveExtraEvent')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <?php foreach ($returnFields as $field => $value): ?>
            <input type="hidden" name="<?= e($field) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>

        <label>Data do evento
            <input type="date" name="event_date" required>
        </label>
        <label>Titulo
            <input type="text" name="title" placeholder="Ex.: Acao local da equipe" required>
        </label>
        <label>Tipo
            <select name="event_type">
                <option value="extra">Extra</option>
                <option value="comercial">Comercial</option>
                <option value="institucional">Institucional</option>
                <option value="sazonal">Sazonal</option>
                <option value="operacional">Operacional</option>
            </select>
        </label>
        <label>Cor personalizada
            <input type="color" name="color_hex" value="<?= e($colors['extra_event'] ?? '#9F3A03') ?>">
        </label>
        <label class="wide">Descricao
            <input type="text" name="description" placeholder="Detalhes do evento adicional para este dia">
        </label>
        <button type="submit"><i class="fa-solid fa-calendar-plus"></i> Salvar evento extra</button>
    </form>

    <?php if (!empty($extra_events)): ?>
        <div class="table-wrap">
            <table class="table extra-events-table">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Titulo</th>
                    <th>Tipo</th>
                    <th>Cor</th>
                    <th>Descricao</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($extra_events as $event): ?>
                    <tr>
                        <td><?= e($event['event_date']) ?></td>
                        <td><?= e($event['title']) ?></td>
                        <td><?= e($event['event_type']) ?></td>
                        <td><span class="color-pill" style="background: <?= e($event['color_hex'] ?: ($colors['extra_event'] ?? '#9F3A03')) ?>;"></span></td>
                        <td><?= e($event['description'] ?? '-') ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('calendar/deleteExtraEvent/' . (int) $event['id'])) ?>" style="display:inline" onsubmit="return confirm('Remover evento extra?')">
                                <?= csrf_field() ?>
                                <?php foreach ($returnFields as $field => $value): ?>
                                    <input type="hidden" name="<?= e($field) ?>" value="<?= e($value) ?>">
                                <?php endforeach; ?>
                                <button type="submit" style="background:none;border:0;padding:0;color:inherit;font:inherit;cursor:pointer">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3>Registrar observacao manual por dia</h3>
    <form method="post" action="<?= e(route_url('calendar/saveNote')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <?php foreach ($returnFields as $field => $value): ?>
            <input type="hidden" name="<?= e($field) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>

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

<section class="panel">
    <h3>Lista de feriados e biblioteca base</h3>
    <div class="catalog-grid">
        <article>
            <h4>Feriados nacionais e internacionais</h4>
            <div class="table-wrap short">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($holiday_catalog as $holiday): ?>
                        <tr>
                            <td><?= e($holiday['holiday_date']) ?></td>
                            <td><?= e($holiday['name']) ?></td>
                            <td>
                                <span class="type-chip holiday-<?= e(strtolower((string) $holiday['holiday_type'])) ?>">
                                    <?= e($holiday['holiday_type']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article>
            <h4>Eventos estratégicos base (Excel)</h4>
            <div class="table-wrap short">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Dia do ano</th>
                        <th>Data ref.</th>
                        <th>Titulo</th>
                        <th>Detalhe</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($base_events_catalog as $event): ?>
                        <tr>
                            <td><?= (int) $event['day_of_year'] ?></td>
                            <td><?= e($event['date_label']) ?></td>
                            <td>
                                <span class="type-chip base-event">Base</span>
                                <?= e($event['title']) ?>
                            </td>
                            <td><?= e($event['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
