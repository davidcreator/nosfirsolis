<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-lightbulb"></i> <?= e($t('suggestions.heading_index', 'Sugestões estratégicas')) ?></h1>
        <a class="btn" href="<?= e(route_url('suggestions/create')) ?>"><i class="fa-solid fa-plus"></i> <?= e($t('suggestions.button_new', 'Nova sugestão')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e($t('suggestions.col_date', 'Data')) ?></th>
                <th><?= e($t('suggestions.col_title', 'Título')) ?></th>
                <th><?= e($t('suggestions.col_format', 'Formato')) ?></th>
                <th><?= e($t('suggestions.col_category', 'Categoria')) ?></th>
                <th><?= e($t('suggestions.col_pillar', 'Pilar')) ?></th>
                <th><?= e($t('suggestions.col_objective', 'Objetivo')) ?></th>
                <th><?= e($t('suggestions.col_campaign', 'Campanha')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['suggestion_date']) ?></td>
                    <td><?= e($item['title']) ?></td>
                    <td><?= e($item['format_type']) ?></td>
                    <td><?= e($item['category_name'] ?? '-') ?></td>
                    <td><?= e($item['pillar_name'] ?? '-') ?></td>
                    <td><?= e($item['objective_name'] ?? '-') ?></td>
                    <td><?= e($item['campaign_name'] ?? '-') ?></td>
                    <td class="actions">
                        <a href="<?= e(route_url('suggestions/edit/' . $item['id'])) ?>"><i class="fa-solid fa-pen-to-square"></i> <?= e($t('common.button_edit', 'Editar')) ?></a>
                        <form method="post" action="<?= e(route_url('suggestions/delete/' . $item['id'])) ?>" style="display:inline" onsubmit="return confirm('<?= e($t('suggestions.confirm_delete', 'Excluir sugestão?')) ?>')">
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
