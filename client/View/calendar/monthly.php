<?php
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$weekNames = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];
?>
<section class="panel">
    <div class="panel-head-inline">
        <h2>Calendario Mensal: <?= e($monthNames[(int) $month] ?? '') ?> / <?= (int) $year ?></h2>
        <div class="inline-links">
            <a href="<?= e(route_url('calendar/monthly?year=' . $year . '&month=' . max(1, $month - 1))) ?>">Mes anterior</a>
            <a href="<?= e(route_url('calendar/monthly?year=' . $year . '&month=' . min(12, $month + 1))) ?>">Proximo mes</a>
        </div>
    </div>

    <form method="get" class="filters-grid">
        <input type="hidden" name="route" value="calendar/monthly">
        <label>Ano
            <input type="number" name="year" value="<?= (int) $year ?>" min="1970" max="2100">
        </label>
        <label>Mes
            <input type="number" name="month" value="<?= (int) $month ?>" min="1" max="12">
        </label>
        <?php include __DIR__ . '/../partials/filters.php'; ?>
    </form>

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
                    <td class="<?= $day['in_month'] ? 'in' : 'out' ?>">
                        <?php if ($day['in_month']): ?>
                            <span class="day-number"><?= (int) $day['day'] ?></span>
                            <?php $events = $day['events'] ?? []; ?>
                            <?php if (!empty($events['holidays'])): ?>
                                <?php foreach ($events['holidays'] as $holiday): ?>
                                    <div class="tag holiday">Feriado: <?= e($holiday['name']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($events['commemoratives'])): ?>
                                <?php foreach ($events['commemoratives'] as $item): ?>
                                    <div class="tag commemorative">Comemorativa: <?= e($item['name']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($events['campaigns'])): ?>
                                <?php foreach ($events['campaigns'] as $campaign): ?>
                                    <div class="tag campaign">Campanha: <?= e($campaign['name']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($events['suggestions'])): ?>
                                <?php foreach ($events['suggestions'] as $suggestion): ?>
                                    <div class="tag suggestion">Sugestao: <?= e($suggestion['title']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($events['notes'])): ?>
                                <?php foreach ($events['notes'] as $note): ?>
                                    <div class="tag note">Nota: <?= e($note['note_text']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Registrar observacao manual por dia</h3>
    <form method="post" action="<?= e(route_url('plans/saveNote')) ?>" class="filters-grid">
        <?= csrf_field() ?>
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
