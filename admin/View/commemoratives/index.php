<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-star"></i> <?= e($t('commemoratives.heading_index', 'Datas comemorativas')) ?></h1>
        <a class="btn" href="<?= e(route_url('commemoratives/create')) ?>"><i class="fa-solid fa-plus"></i> <?= e($t('commemoratives.button_new', 'Nova data')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e($t('commemoratives.col_date', 'Data')) ?></th>
                    <th><?= e($t('commemoratives.col_name', 'Nome')) ?></th>
                    <th><?= e($t('commemoratives.col_context', 'Contexto')) ?></th>
                    <th><?= e($t('commemoratives.col_recurrence', 'Recorrencia')) ?></th>
                    <th><?= e($t('commemoratives.col_status', 'Status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['event_date']) ?></td>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['context_type']) ?></td>
                        <td><?= e($item['recurrence_type']) ?></td>
                        <td><?= (int) $item['status'] === 1 ? e($t('common.status_active', 'Ativo')) : e($t('common.status_inactive', 'Inativo')) ?></td>
                        <td class="actions">
                            <a href="<?= e(route_url('commemoratives/edit/' . $item['id'])) ?>"><i class="fa-solid fa-pen-to-square"></i> <?= e($t('common.button_edit', 'Editar')) ?></a>
                            <form method="post" action="<?= e(route_url('commemoratives/delete/' . $item['id'])) ?>" class="table-action-form" onsubmit="return confirm('<?= e($t('commemoratives.confirm_delete', 'Excluir esta data?')) ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-link danger table-action-btn">
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
