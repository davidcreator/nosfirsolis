<section class="panel">
    <div class="panel-header">
        <h1><?= e($t('channels.heading_index', 'Canais e plataformas')) ?></h1>
        <a class="btn" href="<?= e(route_url('channels/create')) ?>"><i class="fa-solid fa-plus"></i> <?= e($t('channels.button_new', 'Novo canal')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e($t('channels.col_name', 'Nome')) ?></th>
                <th><?= e($t('channels.col_slug', 'Slug')) ?></th>
                <th><?= e($t('channels.col_type', 'Tipo')) ?></th>
                <th><?= e($t('channels.col_source', 'Origem')) ?></th>
                <th><?= e($t('channels.col_status', 'Status')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['slug']) ?></td>
                    <td><?= e($item['platform_type']) ?></td>
                    <td><?= e($item['source']) ?></td>
                    <td><?= (int) $item['status'] === 1 ? e($t('common.status_active', 'Ativo')) : e($t('common.status_inactive', 'Inativo')) ?></td>
                    <td class="actions">
                        <a href="<?= e(route_url('channels/edit/' . $item['id'])) ?>"><i class="fa-solid fa-pen-to-square"></i> <?= e($t('common.button_edit', 'Editar')) ?></a>
                        <form method="post" action="<?= e(route_url('channels/delete/' . $item['id'])) ?>" style="display:inline" onsubmit="return confirm('<?= e($t('channels.confirm_delete', 'Excluir canal?')) ?>')">
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
