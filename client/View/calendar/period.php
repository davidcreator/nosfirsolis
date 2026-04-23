<section class="panel">
    <h2>Calendario por periodo personalizado</h2>

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

    <table class="table">
        <thead>
        <tr>
            <th>Data</th>
            <th>Feriados</th>
            <th>Comemorativas</th>
            <th>Sugestoes</th>
            <th>Campanhas</th>
            <th>Observacoes</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $date => $pack): ?>
            <tr>
                <td><?= e($date) ?></td>
                <td>
                    <?php foreach (($pack['holidays'] ?? []) as $item): ?>
                        <div><?= e($item['name']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach (($pack['commemoratives'] ?? []) as $item): ?>
                        <div><?= e($item['name']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach (($pack['suggestions'] ?? []) as $item): ?>
                        <div><?= e($item['title']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach (($pack['campaigns'] ?? []) as $item): ?>
                        <div><?= e($item['name']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach (($pack['notes'] ?? []) as $item): ?>
                        <div><?= e($item['note_text']) ?></div>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
