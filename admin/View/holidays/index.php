<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-calendar-day"></i> <?= e($t('holidays.heading_index', 'Feriados')) ?></h1>
        <a class="btn" href="<?= e(route_url('holidays/create')) ?>"><i class="fa-solid fa-calendar-plus"></i> <?= e($t('holidays.button_new', 'Novo feriado')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e($t('holidays.col_date', 'Data')) ?></th>
                <th><?= e($t('holidays.col_name', 'Nome')) ?></th>
                <th><?= e($t('holidays.col_type', 'Tipo')) ?></th>
                <th><?= e($t('holidays.col_country', 'País')) ?></th>
                <th><?= e($t('holidays.col_status', 'Status')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($holidays as $item): ?>
                <tr>
                    <td><?= e($item['holiday_date']) ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['holiday_type']) ?></td>
                    <td><?= e($item['country_code']) ?></td>
                    <td><?= (int) $item['status'] === 1 ? e($t('common.status_active', 'Ativo')) : e($t('common.status_inactive', 'Inativo')) ?></td>
                    <td class="actions">
                        <a href="<?= e(route_url('holidays/edit/' . $item['id'])) ?>"><i class="fa-solid fa-pen-to-square"></i> <?= e($t('common.button_edit', 'Editar')) ?></a>
                        <form method="post" action="<?= e(route_url('holidays/delete/' . $item['id'])) ?>" style="display:inline" onsubmit="return confirm('<?= e($t('holidays.confirm_delete', 'Excluir este feriado?')) ?>')">
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
