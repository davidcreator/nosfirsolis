<?php
$users = (array) ($users ?? []);
$groups = (array) ($groups ?? []);
$hierarchyGroups = (array) ($hierarchy_groups ?? []);
$currentHierarchyLevel = (int) ($current_hierarchy_level ?? 50);
$planOptions = (array) ($plan_options ?? []);
$featureCatalog = (array) ($feature_catalog ?? []);
$filterGroupOptions = (array) ($filter_group_options ?? []);
$listFilters = is_array($list_filters ?? null) ? $list_filters : [];
$listStats = is_array($list_stats ?? null) ? $list_stats : [];
$listFiltersQuery = trim((string) ($list_filters_query ?? ''));
$isUsingDefaultFilters = !empty($is_using_default_filters ?? false);
$hasSavedDefaultFilters = !empty($has_saved_default_filters ?? false);
?>

<section class="panel">
    <div class="panel-header">
        <h1><i class="fa-solid fa-users-gear"></i> <?= e($t('users.heading_index', 'Usuários e Hierarquia')) ?></h1>
        <span class="meta-text"><?= e($t('users.current_level', 'Seu nível atual: {level} (quanto menor, maior autoridade)', ['level' => (int) $currentHierarchyLevel])) ?></span>
    </div>
    <p class="meta-text"><?= e($t('users.description_scope', 'O administrador só pode criar usuários e ajustar níveis em grupos com nível igual ou inferior ao seu escopo hierárquico.')) ?></p>
</section>

<section class="panel users-subscription-panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-sliders"></i> <?= e($t('users.heading_plan_access', 'Planos e recursos por usuario')) ?></h2>
        <span class="meta-text"><?= e($t('users.plan_access_hint', 'Ative/desative recursos conforme necessidade e troque o plano direto nesta tela.')) ?></span>
    </div>

    <?php
    $shownCount = (int) ($listStats['shown'] ?? count($users));
    $totalCount = (int) ($listStats['total'] ?? count($users));
    ?>
    <form method="get" action="<?= e(route_url('users/index')) ?>" class="users-filter-form">
        <div class="users-filter-grid">
            <label>
                <span><?= e($t('users.filter_search', 'Busca')) ?></span>
                <input type="search" name="f_q" value="<?= e((string) ($listFilters['q'] ?? '')) ?>" placeholder="<?= e($t('users.filter_search_placeholder', 'Nome, email, grupo ou plano')) ?>">
            </label>
            <label>
                <span><?= e($t('users.filter_group', 'Grupo')) ?></span>
                <select name="f_group_id">
                    <option value="0"><?= e($t('users.filter_group_all', 'Todos')) ?></option>
                    <?php foreach ($filterGroupOptions as $group): ?>
                        <?php $groupId = (int) ($group['id'] ?? 0); ?>
                        <option value="<?= (int) $groupId ?>" <?= $groupId === (int) ($listFilters['group_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= e((string) ($group['name'] ?? '-')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= e($t('users.filter_plan', 'Plano')) ?></span>
                <select name="f_plan_id">
                    <option value="0"><?= e($t('users.filter_plan_all', 'Todos')) ?></option>
                    <?php foreach ($planOptions as $plan): ?>
                        <?php $optionId = (int) ($plan['id'] ?? 0); ?>
                        <option value="<?= (int) $optionId ?>" <?= $optionId === (int) ($listFilters['plan_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= e((string) ($plan['name'] ?? ('Plano #' . $optionId))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= e($t('users.filter_scope', 'Escopo')) ?></span>
                <select name="f_scope">
                    <?php $scope = (string) ($listFilters['scope'] ?? 'all'); ?>
                    <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>><?= e($t('users.filter_scope_all', 'Todos')) ?></option>
                    <option value="manageable" <?= $scope === 'manageable' ? 'selected' : '' ?>><?= e($t('users.filter_scope_manageable', 'Gerenciaveis')) ?></option>
                    <option value="restricted" <?= $scope === 'restricted' ? 'selected' : '' ?>><?= e($t('users.filter_scope_restricted', 'Restritos')) ?></option>
                </select>
            </label>
            <label>
                <span><?= e($t('users.filter_user_status', 'Status do usuario')) ?></span>
                <?php $userStatusFilter = (string) ($listFilters['user_status'] ?? 'all'); ?>
                <select name="f_user_status">
                    <option value="all" <?= $userStatusFilter === 'all' ? 'selected' : '' ?>><?= e($t('users.filter_user_status_all', 'Todos')) ?></option>
                    <option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>><?= e($t('users.filter_user_status_active', 'Ativos')) ?></option>
                    <option value="inactive" <?= $userStatusFilter === 'inactive' ? 'selected' : '' ?>><?= e($t('users.filter_user_status_inactive', 'Inativos')) ?></option>
                </select>
            </label>
            <label>
                <span><?= e($t('users.filter_subscription_status', 'Status da assinatura')) ?></span>
                <?php $subscriptionStatusFilter = (string) ($listFilters['subscription_status'] ?? 'all'); ?>
                <select name="f_subscription_status">
                    <option value="all" <?= $subscriptionStatusFilter === 'all' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_all', 'Todos')) ?></option>
                    <option value="trial" <?= $subscriptionStatusFilter === 'trial' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_trial', 'Trial')) ?></option>
                    <option value="active" <?= $subscriptionStatusFilter === 'active' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_active', 'Ativo')) ?></option>
                    <option value="past_due" <?= $subscriptionStatusFilter === 'past_due' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_past_due', 'Em atraso')) ?></option>
                    <option value="suspended" <?= $subscriptionStatusFilter === 'suspended' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_suspended', 'Suspenso')) ?></option>
                    <option value="canceled" <?= $subscriptionStatusFilter === 'canceled' ? 'selected' : '' ?>><?= e($t('users.filter_subscription_status_canceled', 'Cancelado')) ?></option>
                </select>
            </label>
            <label>
                <span><?= e($t('users.filter_override_mode', 'Recursos personalizados')) ?></span>
                <?php $overrideModeFilter = (string) ($listFilters['override_mode'] ?? 'all'); ?>
                <select name="f_override_mode">
                    <option value="all" <?= $overrideModeFilter === 'all' ? 'selected' : '' ?>><?= e($t('users.filter_override_mode_all', 'Todos')) ?></option>
                    <option value="custom" <?= $overrideModeFilter === 'custom' ? 'selected' : '' ?>><?= e($t('users.filter_override_mode_custom', 'Com personalizacao')) ?></option>
                    <option value="no_custom" <?= $overrideModeFilter === 'no_custom' ? 'selected' : '' ?>><?= e($t('users.filter_override_mode_no_custom', 'Sem personalizacao')) ?></option>
                </select>
            </label>
        </div>

        <div class="users-filter-actions">
            <button type="submit"><i class="fa-solid fa-filter"></i> <?= e($t('users.button_apply_filters', 'Aplicar filtros')) ?></button>
            <a class="btn-link" href="<?= e(route_url('users/index?skip_default_filters=1')) ?>"><i class="fa-solid fa-eraser"></i> <?= e($t('users.button_clear_filters', 'Limpar')) ?></a>
            <form method="post" action="<?= e(route_url('users/saveDefaultFilters')) ?>" class="users-default-filter-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_return_qs" value="<?= e($listFiltersQuery) ?>">
                <input type="hidden" name="f_q" value="<?= e((string) ($listFilters['q'] ?? '')) ?>">
                <input type="hidden" name="f_group_id" value="<?= (int) ($listFilters['group_id'] ?? 0) ?>">
                <input type="hidden" name="f_plan_id" value="<?= (int) ($listFilters['plan_id'] ?? 0) ?>">
                <input type="hidden" name="f_scope" value="<?= e((string) ($listFilters['scope'] ?? 'all')) ?>">
                <input type="hidden" name="f_user_status" value="<?= e((string) ($listFilters['user_status'] ?? 'all')) ?>">
                <input type="hidden" name="f_subscription_status" value="<?= e((string) ($listFilters['subscription_status'] ?? 'all')) ?>">
                <input type="hidden" name="f_override_mode" value="<?= e((string) ($listFilters['override_mode'] ?? 'all')) ?>">
                <button type="submit" class="btn-link"><i class="fa-solid fa-bookmark"></i> <?= e($t('users.button_save_default_filters', 'Salvar filtro padrao')) ?></button>
            </form>
            <?php if ($hasSavedDefaultFilters): ?>
                <form method="post" action="<?= e(route_url('users/clearDefaultFilters')) ?>" class="users-default-filter-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_return_qs" value="<?= e($listFiltersQuery) ?>">
                    <button type="submit" class="btn-link danger"><i class="fa-solid fa-bookmark-slash"></i> <?= e($t('users.button_clear_default_filters', 'Remover filtro padrao')) ?></button>
                </form>
            <?php endif; ?>
            <?php if ($isUsingDefaultFilters): ?>
                <span class="meta-text users-filter-default-note"><?= e($t('users.default_filters_applied', 'Filtro padrao aplicado automaticamente.')) ?></span>
            <?php endif; ?>
            <span class="meta-text users-filter-summary"><?= e($t('users.filters_results', 'Exibindo {shown} de {total} usuarios.', ['shown' => $shownCount, 'total' => $totalCount])) ?></span>
        </div>
    </form>

    <?php if (empty($users) || empty($planOptions) || empty($featureCatalog)): ?>
        <p><?= e($t('users.empty_plan_access', 'Nao ha dados suficientes para gerenciar planos e recursos neste momento.')) ?></p>
    <?php else: ?>
        <div class="users-admin-list">
            <div class="users-admin-list-head">
                <span><?= e($t('users.col_user', 'Usuario')) ?></span>
                <span><?= e($t('users.col_plan_current', 'Plano atual')) ?></span>
                <span><?= e($t('users.col_plan_change', 'Alterar plano')) ?></span>
                <span><?= e($t('users.col_feature_access', 'Recursos')) ?></span>
            </div>

            <?php foreach ($users as $user): ?>
                <?php
                $userId = (int) ($user['id'] ?? 0);
                $canManage = !empty($user['can_manage_subscription']);
                $planId = (int) ($user['plan_id'] ?? 0);
                $planName = (string) ($user['plan_name'] ?? '-');
                $planStatus = trim((string) ($user['plan_status'] ?? '-'));
                $featuresEffective = is_array($user['features_effective'] ?? null) ? $user['features_effective'] : [];
                $featureOverrides = is_array($user['feature_overrides'] ?? null) ? $user['feature_overrides'] : [];
                ?>
                <article class="users-admin-row <?= $canManage ? 'is-manageable' : 'is-restricted' ?>">
                    <div class="users-admin-cell user-main">
                        <strong><?= e($user['name'] ?? '-') ?></strong>
                        <p class="meta-text"><?= e($user['email'] ?? '-') ?></p>
                        <div class="users-admin-inline">
                            <span><i class="fa-solid fa-user-group"></i> <?= e($user['group_name'] ?? '-') ?></span>
                            <span><i class="fa-solid fa-sitemap"></i> <?= e($t('users.group_level_label', 'Nivel')) ?> <?= (int) ($user['group_hierarchy_level'] ?? 50) ?></span>
                        </div>
                        <span class="user-scope-pill <?= $canManage ? 'is-ok' : 'is-locked' ?>">
                            <?= $canManage
                                ? e($t('users.scope_manageable', 'Pode gerenciar'))
                                : e($t('users.scope_restricted', 'Restrito por hierarquia')) ?>
                        </span>
                    </div>

                    <div class="users-admin-cell user-plan">
                        <span class="user-plan-chip"><?= e($planName) ?></span>
                        <span class="meta-text"><?= e($t('users.plan_status_label', 'Status')) ?>: <?= e($planStatus) ?></span>
                    </div>

                    <div class="users-admin-cell user-plan-edit">
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= e(route_url('users/updatePlan/' . $userId)) ?>" class="user-plan-form admin-list-plan-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_return_qs" value="<?= e($listFiltersQuery) ?>">
                                <select name="plan_id" required>
                                    <?php foreach ($planOptions as $plan): ?>
                                        <?php $optionId = (int) ($plan['id'] ?? 0); ?>
                                        <option value="<?= (int) $optionId ?>" <?= $optionId === $planId ? 'selected' : '' ?>>
                                            <?= e($plan['name'] ?? ('Plano #' . $optionId)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-sm"><i class="fa-solid fa-rotate"></i> <?= e($t('users.button_update_plan', 'Atualizar')) ?></button>
                            </form>
                        <?php else: ?>
                            <p class="meta-text"><?= e($t('users.scope_outside', 'Fora do seu escopo')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="users-admin-cell user-feature-edit">
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= e(route_url('users/saveUserFeatures/' . $userId)) ?>" class="user-feature-form admin-list-feature-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_return_qs" value="<?= e($listFiltersQuery) ?>">
                                <div class="user-feature-grid admin-list-feature-grid">
                                    <?php foreach ($featureCatalog as $feature): ?>
                                        <?php
                                        $featureKey = (string) ($feature['key'] ?? '');
                                        if ($featureKey === '') {
                                            continue;
                                        }
                                        $featureEnabled = (bool) ($featuresEffective[$featureKey] ?? false);
                                        $featureSource = array_key_exists($featureKey, $featureOverrides)
                                            ? $t('users.feature_source_custom', 'Personalizado')
                                            : $t('users.feature_source_plan', 'Plano');
                                        $featureId = 'feature-' . $userId . '-' . preg_replace('/[^a-z0-9_]+/i', '-', $featureKey);
                                        ?>
                                        <label class="user-feature-item admin-list-feature-item <?= $featureEnabled ? 'is-enabled' : 'is-disabled' ?>" for="<?= e($featureId) ?>">
                                            <span class="user-feature-item-copy">
                                                <span class="feature-title"><?= e($feature['label'] ?? $featureKey) ?></span>
                                                <small><?= e($featureSource) ?></small>
                                            </span>
                                            <input type="hidden" name="feature[<?= e($featureKey) ?>]" value="0">
                                            <input id="<?= e($featureId) ?>" type="checkbox" name="feature[<?= e($featureKey) ?>]" value="1" <?= $featureEnabled ? 'checked' : '' ?>>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn-sm"><i class="fa-solid fa-sliders"></i> <?= e($t('users.button_save_features', 'Salvar recursos')) ?></button>
                            </form>
                        <?php else: ?>
                            <p class="meta-text"><?= e($t('users.scope_outside', 'Fora do seu escopo')) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-user-plus"></i> <?= e($t('users.heading_create', 'Criar usuário')) ?></h2>
    </div>

    <?php if (empty($groups)): ?>
        <p><?= e($t('users.empty_groups_for_create', 'Nenhum grupo disponível para atribuição de usuários no seu nível hierárquico.')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= e(route_url('users/store')) ?>" class="form-grid form-grid-user-create">
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

