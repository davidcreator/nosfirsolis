<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-bullhorn"></i> <?= e($t('campaigns.heading_index', 'Campanhas')) ?></h1>
        <a class="btn" href="<?= e(route_url('campaigns/create')) ?>"><i class="fa-solid fa-plus"></i> <?= e($t('campaigns.button_new', 'Nova campanha')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e($t('campaigns.col_name', 'Nome')) ?></th>
                <th><?= e($t('campaigns.col_objective', 'Objetivo')) ?></th>
                <th><?= e($t('campaigns.col_period', 'Período')) ?></th>
                <th><?= e($t('campaigns.col_status', 'Status')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['objective'] ?? '-') ?></td>
                    <td><?= e(($item['start_date'] ?? '-') . ' ' . $t('campaigns.period_to', 'a') . ' ' . ($item['end_date'] ?? '-')) ?></td>
                    <td><?= e($item['status']) ?></td>
                    <td class="actions">
                        <a href="<?= e(route_url('campaigns/edit/' . $item['id'])) ?>"><i class="fa-solid fa-pen-to-square"></i> <?= e($t('common.button_edit', 'Editar')) ?></a>
                        <form method="post" action="<?= e(route_url('campaigns/delete/' . $item['id'])) ?>" style="display:inline" onsubmit="return confirm('<?= e($t('campaigns.confirm_delete', 'Excluir campanha?')) ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" style="background:none;border:0;padding:0;color:inherit;font:inherit;cursor:pointer">
                                <i class="fa-regular fa-trash-can"></i> <?= e($t('common.button_delete', 'Excluir')) ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
