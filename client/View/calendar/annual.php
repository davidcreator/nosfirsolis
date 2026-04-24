<?php
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$weekNames = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
?>
<section class="panel">
    <h2>Calendário Anual <?= (int) $year ?></h2>

    <form method="get" class="filters-grid">
        <input type="hidden" name="route" value="calendar/annual">
        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

    <div class="annual-grid">
        <?php foreach ($months as $month): ?>
            <article class="month-card">
                <h3><?= e($monthNames[(int) $month['month']] ?? '') ?></h3>
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
                                <td class="<?= $day['in_month'] ? 'day in' : 'day out' ?>">
                                    <?php if ($day['in_month']): ?>
                                        <span class="day-number"><?= (int) $day['day'] ?></span>
                                        <div class="day-markers">
                                            <?php $events = $day['events'] ?? []; ?>
                                            <?php if (!empty($events['holidays'])): ?><span class="mk holiday" title="Feriado"></span><?php endif; ?>
                                            <?php if (!empty($events['commemoratives'])): ?><span class="mk commemorative" title="Comemorativa"></span><?php endif; ?>
                                            <?php if (!empty($events['campaigns'])): ?><span class="mk campaign" title="Campanha"></span><?php endif; ?>
                                            <?php if (!empty($events['suggestions'])): ?><span class="mk suggestion" title="Sugestão"></span><?php endif; ?>
                                            <?php if (!empty($events['notes'])): ?><span class="mk note" title="Observação"></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </article>
        <?php endforeach; ?>
    </div>
</section>
