<?php
$groups = (array) ($groups ?? []);
$hierarchyGroups = (array) ($hierarchy_groups ?? []);
$currentHierarchyLevel = (int) ($current_hierarchy_level ?? 50);
?>

<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-users-gear"></i> <?= e($t('users.heading_index', 'Usuários e Hierarquia')) ?></h1>
        <span class="meta-text"><?= e($t('users.current_level', 'Seu nível atual: {level} (quanto menor, maior autoridade)', ['level' => (int) $currentHierarchyLevel])) ?></span>
    </div>
    <p class="meta-text"><?= e($t('users.description_scope', 'O administrador só pode criar usuários e ajustar níveis em grupos com nível igual ou inferior ao seu escopo hierárquico.')) ?></p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-user-plus"></i> <?= e($t('users.heading_create', 'Criar usuário')) ?></h2>
    </div>

    <?php if (empty($groups)): ?>
        <p><?= e($t('users.empty_groups_for_create', 'Nenhum grupo disponível para atribuição de usuários no seu nível hierárquico.')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= e(route_url('users/store')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <label><?= e($t('users.field_name', 'Nome')) ?>
                <input type="text" name="name" required>
            </label>
            <label><?= e($t('users.field_email', 'Email')) ?>
                <input type="email" name="email" required>
            </label>
            <label><?= e($t('users.field_password', 'Senha')) ?>
                <input type="password" name="password" required minlength="8">
            </label>
            <label><?= e($t('users.field_group', 'Grupo')) ?>
                <select name="user_group_id" required>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>">
                            <?= e($group['name']) ?> (<?= e($t('users.group_level_label', 'Nível')) ?> <?= (int) ($group['hierarchy_level'] ?? 50) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e($t('users.field_status', 'Status')) ?>
                <select name="status">
                    <option value="1"><?= e($t('common.status_active', 'Ativo')) ?></option>
                    <option value="0"><?= e($t('common.status_inactive', 'Inativo')) ?></option>
                </select>
            </label>
            <button type="submit"><i class="fa-solid fa-user-plus"></i> <?= e($t('users.button_create', 'Criar usuário')) ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-sitemap"></i> <?= e($t('users.heading_hierarchy', 'Controle de níveis hierárquicos')) ?></h2>
        <span class="meta-text"><?= e($t('users.hierarchy_hint', 'Menor número = maior permissão')) ?></span>
    </div>

    <?php if (empty($hierarchyGroups)): ?>
        <p><?= e($t('users.empty_hierarchy_groups', 'Sem grupos disponíveis para gerenciar neste nível.')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= e(route_url('users/saveHierarchy')) ?>">
            <?= csrf_field() ?>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e($t('users.col_group', 'Grupo')) ?></th>
                        <th><?= e($t('users.col_description', 'Descrição')) ?></th>
                        <th><?= e($t('users.col_permissions', 'Permissões')) ?></th>
                        <th><?= e($t('users.col_level', 'Nível')) ?></th>
                        <th><?= e($t('users.col_status', 'Status')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($hierarchyGroups as $group): ?>
                        <?php
                        $permissions = json_decode((string) ($group['permissions_json'] ?? '[]'), true);
                        if (!is_array($permissions)) {
                            $permissions = [];
                        }
                        $permissions = array_values(array_filter(array_map('strval', $permissions), static fn (string $item): bool => trim($item) !== ''));
                        $preview = implode(', ', array_slice($permissions, 0, 4));
                        if (count($permissions) > 4) {
                            $preview .= ' ... +' . (count($permissions) - 4);
                        }
                        if ($preview === '') {
                            $preview = '-';
                        }
                        ?>
                        <tr>
                            <td><?= e($group['name']) ?></td>
                            <td><?= e($group['description'] ?? '-') ?></td>
                            <td><?= e($preview) ?></td>
                            <td>
                                <input
                                    type="number"
                                    name="hierarchy_level[<?= (int) $group['id'] ?>]"
                                    value="<?= (int) ($group['hierarchy_level'] ?? 50) ?>"
                                    min="<?= (int) $currentHierarchyLevel ?>"
                                    max="999"
                                    required
                                >
                            </td>
                            <td><?= (int) ($group['status'] ?? 0) === 1 ? e($t('common.status_active', 'Ativo')) : e($t('common.status_inactive', 'Inativo')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit"><i class="fa-solid fa-sitemap"></i> <?= e($t('users.button_save_levels', 'Salvar níveis')) ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-address-card"></i> <?= e($t('users.heading_registered', 'Usuários cadastrados')) ?></h2>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e($t('users.col_name', 'Nome')) ?></th>
                    <th><?= e($t('users.col_email', 'Email')) ?></th>
                    <th><?= e($t('users.col_group', 'Grupo')) ?></th>
                    <th><?= e($t('users.col_level', 'Nível')) ?></th>
                    <th><?= e($t('users.col_status', 'Status')) ?></th>
                    <th><?= e($t('users.col_last_login', 'Último login')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['name']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e($user['group_name'] ?? '-') ?></td>
                        <td><?= (int) ($user['group_hierarchy_level'] ?? 50) ?></td>
                        <td><?= (int) $user['status'] === 1 ? e($t('common.status_active', 'Ativo')) : e($t('common.status_inactive', 'Inativo')) ?></td>
                        <td><?= e($user['last_login_at'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
