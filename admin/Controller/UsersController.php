<?php

namespace Admin\Controller;

use System\Library\SubscriptionService;

class UsersController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.users');

        $groupsModel = $this->loader->model('user_groups');
        $usersModel = $this->loader->model('users');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $groups = $groupsModel->optionsForHierarchy($currentHierarchyLevel);
        $allGroups = $groupsModel->allWithHierarchy();
        $manageableGroups = array_values(array_filter(
            $allGroups,
            static fn (array $group): bool => (int) ($group['hierarchy_level'] ?? 50) >= $currentHierarchyLevel
        ));

        $users = $usersModel->allWithGroup();
        $subscription = new SubscriptionService($this->registry);
        $subscription->ensureTables();

        $planOptions = array_values(array_filter(
            $subscription->adminPlans(),
            static fn (array $plan): bool => (int) ($plan['status'] ?? 0) === 1
        ));
        $featureCatalog = $subscription->featureCatalog();
        $skipDefaultFilters = (int) $this->request->get('skip_default_filters', 0) === 1;
        $listFilters = $this->normalizeUsersListFilters();
        $usingSavedDefaultFilters = false;
        $hasSavedDefaultFilters = $this->hasSavedUsersListFilters($currentUserId);

        if (!$skipDefaultFilters && !$this->hasUsersListFilterInput()) {
            $savedDefaultFilters = $this->loadSavedUsersListFilters($currentUserId);
            if ($savedDefaultFilters !== []) {
                $listFilters = $savedDefaultFilters;
                $usingSavedDefaultFilters = true;
            }
        }

        foreach ($users as &$user) {
            $userId = (int) ($user['id'] ?? 0);
            $targetLevel = max(1, min(999, (int) ($user['group_hierarchy_level'] ?? 50)));
            $canManage = $targetLevel >= $currentHierarchyLevel;

            $context = $userId > 0 ? $subscription->contextForUser($userId) : [];
            $user['subscription_context'] = $context;
            $user['plan_name'] = (string) ($context['plan']['name'] ?? '-');
            $user['plan_id'] = (int) ($context['plan']['id'] ?? 0);
            $user['plan_status'] = (string) ($context['subscription']['status'] ?? '-');
            $user['features_effective'] = (array) ($context['features'] ?? []);
            $user['feature_overrides'] = (array) ($context['feature_overrides'] ?? []);
            $user['has_custom_overrides'] = !empty($user['feature_overrides']);
            $user['can_manage_subscription'] = $canManage;
        }
        unset($user);

        $totalUsersCount = count($users);
        $users = $this->applyUsersListFilters($users, $listFilters);
        $shownUsersCount = count($users);

        $this->render('users/index', [
            'title' => $this->t('users.title_index', 'Usuários e Hierarquia'),
            'users' => $users,
            'groups' => $groups,
            'hierarchy_groups' => $manageableGroups,
            'current_hierarchy_level' => $currentHierarchyLevel,
            'plan_options' => $planOptions,
            'feature_catalog' => $featureCatalog,
            'filter_group_options' => $manageableGroups,
            'list_filters' => $listFilters,
            'list_filters_query' => $this->buildUsersListFiltersQuery($listFilters, $skipDefaultFilters),
            'is_using_default_filters' => $usingSavedDefaultFilters,
            'has_saved_default_filters' => $hasSavedDefaultFilters,
            'list_stats' => [
                'total' => $totalUsersCount,
                'shown' => $shownUsersCount,
            ],
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $groupId = (int) $this->request->post('user_group_id');
        $targetGroup = $groupsModel->find($groupId);
        if (!$targetGroup) {
            flash('error', $this->t('users.flash_invalid_group', 'Grupo de usuário inválido.'));
            $this->redirectToRoute('users/index');
        }

        $targetHierarchyLevel = max(1, min(999, (int) ($targetGroup['hierarchy_level'] ?? 50)));
        if ($targetHierarchyLevel < $currentHierarchyLevel) {
            flash('error', $this->t('users.flash_group_above_hierarchy', 'Você não pode criar usuário em um grupo acima do seu nível hierárquico.'));
            $this->redirectToRoute('users/index');
        }

        $name = trim((string) $this->request->post('name'));
        $email = strtolower(trim((string) $this->request->post('email')));
        $password = (string) $this->request->post('password');

        if ($name === '' || $email === '' || $password === '') {
            flash('error', $this->t('users.flash_required_fields', 'Nome, email e senha são obrigatórios.'));
            $this->redirectToRoute('users/index');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('users.flash_invalid_email', 'Informe um email válido.'));
            $this->redirectToRoute('users/index');
        }

        if (strlen($password) < 8) {
            flash('error', $this->t('users.flash_password_min_length', 'A senha deve ter no mínimo 8 caracteres.'));
            $this->redirectToRoute('users/index');
        }

        $existing = $this->db->fetch('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
        if ($existing) {
            flash('error', $this->t('users.flash_email_exists', 'Já existe um usuário com este email.'));
            $this->redirectToRoute('users/index');
        }

        $this->loader->model('users')->create([
            'user_group_id' => $groupId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('users.flash_created', 'Usuário criado.'));
        $this->redirectToRoute('users/index');
    }

    public function saveHierarchy(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $payload = (array) $this->request->post('hierarchy_level', []);
        if (empty($payload)) {
            flash('error', $this->t('users.flash_no_hierarchy_payload', 'Nenhum nível hierárquico foi enviado para atualização.'));
            $this->redirectToRoute('users/index');
        }

        $updated = 0;
        $blocked = 0;

        foreach ($payload as $groupIdRaw => $levelRaw) {
            $groupIdString = (string) $groupIdRaw;
            if (!ctype_digit($groupIdString)) {
                continue;
            }

            $groupId = (int) $groupIdString;
            if ($groupId <= 0) {
                continue;
            }

            $group = $groupsModel->find($groupId);
            if (!$group) {
                continue;
            }

            $currentGroupLevel = max(1, min(999, (int) ($group['hierarchy_level'] ?? 50)));
            if ($currentGroupLevel < $currentHierarchyLevel) {
                $blocked++;
                continue;
            }

            $levelString = trim((string) $levelRaw);
            if ($levelString === '' || !ctype_digit($levelString)) {
                continue;
            }

            $newLevel = max(1, min(999, (int) $levelString));
            if ($newLevel < $currentHierarchyLevel) {
                $blocked++;
                continue;
            }

            if ($newLevel === $currentGroupLevel) {
                continue;
            }

            if ($groupsModel->updateHierarchyLevel($groupId, $newLevel) > 0) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $message = $this->t(
                'users.flash_hierarchy_updated',
                'Níveis hierárquicos atualizados: {count}.',
                ['count' => $updated]
            );
            if ($blocked > 0) {
                $message .= ' ' . $this->t(
                    'users.flash_hierarchy_blocked',
                    'Itens bloqueados por permissão: {count}.',
                    ['count' => $blocked]
                );
            }
            flash('success', $message);
            $this->redirectToRoute('users/index');
        }

        if ($blocked > 0) {
            flash('error', $this->t('users.flash_hierarchy_update_denied', 'Não foi possível atualizar. Alguns níveis estão acima da sua permissão.'));
            $this->redirectToRoute('users/index');
        }

        flash('success', $this->t('users.flash_hierarchy_no_changes', 'Nenhuma alteração de hierarquia foi necessária.'));
        $this->redirectToRoute('users/index');
    }

    public function saveDefaultFilters(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        if ($currentUserId <= 0) {
            flash('error', $this->t('users.flash_default_filters_save_error', 'Nao foi possivel salvar o filtro padrao.'));
            $this->redirectToRoute('users/index');
        }

        $filters = $this->normalizeUsersListFilters((array) $this->request->post);
        $filtersQuery = $this->buildUsersListFiltersQuery($filters);

        try {
            $this->loader
                ->model('settings')
                ->setValue($this->usersDefaultFiltersSettingKey($currentUserId), $filtersQuery);
        } catch (\Throwable) {
            flash('error', $this->t('users.flash_default_filters_save_error', 'Nao foi possivel salvar o filtro padrao.'));
            $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
        }

        if ($filtersQuery === '') {
            flash('success', $this->t('users.flash_default_filters_cleared', 'Filtro padrao removido.'));
            $this->redirectToUsersIndex('');
        }

        flash('success', $this->t('users.flash_default_filters_saved', 'Filtro padrao salvo com sucesso.'));
        $this->redirectToUsersIndex($filtersQuery);
    }

    public function clearDefaultFilters(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        if ($currentUserId <= 0) {
            flash('error', $this->t('users.flash_default_filters_remove_error', 'Nao foi possivel remover o filtro padrao.'));
            $this->redirectToRoute('users/index');
        }

        try {
            $this->loader
                ->model('settings')
                ->setValue($this->usersDefaultFiltersSettingKey($currentUserId), '');
        } catch (\Throwable) {
            flash('error', $this->t('users.flash_default_filters_remove_error', 'Nao foi possivel remover o filtro padrao.'));
            $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
        }

        flash('success', $this->t('users.flash_default_filters_cleared', 'Filtro padrao removido.'));
        $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
    }

    public function updatePlan(int $userId): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();
        $returnFiltersQuery = $this->returnUsersListFiltersQueryFromPost();

        $scope = $this->resolveManageableUser($userId);
        if ($scope === null) {
            flash('error', $this->t('users.flash_user_not_found', 'Usuario alvo nao encontrado.'));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        if (!$scope['allowed']) {
            flash('error', $this->t('users.flash_plan_scope_denied', 'Voce nao pode alterar plano de usuarios acima do seu nivel hierarquico.'));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        $planId = (int) $this->request->post('plan_id', 0);
        if ($planId <= 0) {
            flash('error', $this->t('users.flash_plan_invalid', 'Selecione um plano valido.'));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        $adminUserId = (int) ($this->auth->user()['id'] ?? 0);
        $service = new SubscriptionService($this->registry);
        $result = $service->assignPlanToUser($userId, $planId, $adminUserId);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('users.flash_plan_updated', 'Plano atualizado com sucesso.')));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        flash('error', (string) ($result['message'] ?? $this->t('users.flash_plan_update_error', 'Nao foi possivel atualizar o plano.')));
        $this->redirectToUsersIndex($returnFiltersQuery);
    }

    public function saveUserFeatures(int $userId): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();
        $returnFiltersQuery = $this->returnUsersListFiltersQueryFromPost();

        $scope = $this->resolveManageableUser($userId);
        if ($scope === null) {
            flash('error', $this->t('users.flash_user_not_found', 'Usuario alvo nao encontrado.'));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        if (!$scope['allowed']) {
            flash('error', $this->t('users.flash_feature_scope_denied', 'Voce nao pode alterar recursos de usuarios acima do seu nivel hierarquico.'));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        $payload = (array) $this->request->post('feature', []);
        $adminUserId = (int) ($this->auth->user()['id'] ?? 0);
        $service = new SubscriptionService($this->registry);
        $result = $service->saveUserFeatureOverrides($userId, $payload, $adminUserId);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('users.flash_features_updated', 'Recursos do usuario atualizados com sucesso.')));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        flash('error', (string) ($result['message'] ?? $this->t('users.flash_features_update_error', 'Nao foi possivel atualizar os recursos do usuario.')));
        $this->redirectToUsersIndex($returnFiltersQuery);
    }

    private function normalizeUsersListFilters(?array $source = null): array
    {
        $source = is_array($source) ? $source : (array) $this->request->get;
        $read = static function (array $data, string $key, mixed $default = ''): mixed {
            return array_key_exists($key, $data) ? $data[$key] : $default;
        };

        $q = trim((string) $read($source, 'f_q', ''));
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 120);
        } else {
            $q = substr($q, 0, 120);
        }

        $scope = strtolower(trim((string) $read($source, 'f_scope', 'all')));
        if (!in_array($scope, ['all', 'manageable', 'restricted'], true)) {
            $scope = 'all';
        }

        $userStatus = strtolower(trim((string) $read($source, 'f_user_status', 'all')));
        if (!in_array($userStatus, ['all', 'active', 'inactive'], true)) {
            $userStatus = 'all';
        }

        $subscriptionStatus = strtolower(trim((string) $read($source, 'f_subscription_status', 'all')));
        if (!in_array($subscriptionStatus, ['all', 'trial', 'active', 'past_due', 'suspended', 'canceled'], true)) {
            $subscriptionStatus = 'all';
        }

        $overrideMode = strtolower(trim((string) $read($source, 'f_override_mode', 'all')));
        if (!in_array($overrideMode, ['all', 'custom', 'no_custom'], true)) {
            $overrideMode = 'all';
        }

        $groupId = max(0, (int) $read($source, 'f_group_id', 0));
        $planId = max(0, (int) $read($source, 'f_plan_id', 0));

        return [
            'q' => $q,
            'group_id' => $groupId,
            'plan_id' => $planId,
            'scope' => $scope,
            'user_status' => $userStatus,
            'subscription_status' => $subscriptionStatus,
            'override_mode' => $overrideMode,
        ];
    }

    private function applyUsersListFilters(array $users, array $filters): array
    {
        $normalize = static function (string $value): string {
            $value = trim($value);
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        };

        $searchNeedle = $normalize((string) ($filters['q'] ?? ''));
        $groupId = (int) ($filters['group_id'] ?? 0);
        $planId = (int) ($filters['plan_id'] ?? 0);
        $scope = (string) ($filters['scope'] ?? 'all');
        $userStatus = (string) ($filters['user_status'] ?? 'all');
        $subscriptionStatus = (string) ($filters['subscription_status'] ?? 'all');
        $overrideMode = (string) ($filters['override_mode'] ?? 'all');

        return array_values(array_filter($users, static function (array $user) use (
            $normalize,
            $searchNeedle,
            $groupId,
            $planId,
            $scope,
            $userStatus,
            $subscriptionStatus,
            $overrideMode
        ): bool {
            if ($searchNeedle !== '') {
                $haystack = $normalize(
                    (string) ($user['name'] ?? '') . ' ' .
                    (string) ($user['email'] ?? '') . ' ' .
                    (string) ($user['group_name'] ?? '') . ' ' .
                    (string) ($user['plan_name'] ?? '')
                );
                if (!str_contains($haystack, $searchNeedle)) {
                    return false;
                }
            }

            if ($groupId > 0 && (int) ($user['user_group_id'] ?? 0) !== $groupId) {
                return false;
            }

            if ($planId > 0 && (int) ($user['plan_id'] ?? 0) !== $planId) {
                return false;
            }

            if ($scope === 'manageable' && empty($user['can_manage_subscription'])) {
                return false;
            }
            if ($scope === 'restricted' && !empty($user['can_manage_subscription'])) {
                return false;
            }

            if ($userStatus !== 'all') {
                $currentStatus = (int) ($user['status'] ?? 0) === 1 ? 'active' : 'inactive';
                if ($currentStatus !== $userStatus) {
                    return false;
                }
            }

            if ($subscriptionStatus !== 'all') {
                $currentSubscriptionStatus = strtolower(trim((string) ($user['plan_status'] ?? '')));
                if ($currentSubscriptionStatus !== $subscriptionStatus) {
                    return false;
                }
            }

            $hasCustomOverrides = !empty($user['has_custom_overrides']);
            if ($overrideMode === 'custom' && !$hasCustomOverrides) {
                return false;
            }
            if ($overrideMode === 'no_custom' && $hasCustomOverrides) {
                return false;
            }

            return true;
        }));
    }

    private function buildUsersListFiltersQuery(array $filters, bool $skipDefaultFilters = false): string
    {
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $params['f_q'] = $q;
        }

        $groupId = (int) ($filters['group_id'] ?? 0);
        if ($groupId > 0) {
            $params['f_group_id'] = $groupId;
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $params['f_plan_id'] = $planId;
        }

        $scope = (string) ($filters['scope'] ?? 'all');
        if ($scope !== 'all') {
            $params['f_scope'] = $scope;
        }

        $userStatus = (string) ($filters['user_status'] ?? 'all');
        if ($userStatus !== 'all') {
            $params['f_user_status'] = $userStatus;
        }

        $subscriptionStatus = (string) ($filters['subscription_status'] ?? 'all');
        if ($subscriptionStatus !== 'all') {
            $params['f_subscription_status'] = $subscriptionStatus;
        }

        $overrideMode = (string) ($filters['override_mode'] ?? 'all');
        if ($overrideMode !== 'all') {
            $params['f_override_mode'] = $overrideMode;
        }

        if ($skipDefaultFilters) {
            $params['skip_default_filters'] = 1;
        }

        return $params === [] ? '' : http_build_query($params);
    }

    private function returnUsersListFiltersQueryFromPost(): string
    {
        $rawQuery = trim((string) $this->request->post('_return_qs', ''));
        if ($rawQuery === '') {
            return '';
        }

        parse_str($rawQuery, $parsed);
        if (!is_array($parsed)) {
            return '';
        }

        $skipDefaultFilters = (int) ($parsed['skip_default_filters'] ?? 0) === 1;
        $filters = $this->normalizeUsersListFilters($parsed);
        return $this->buildUsersListFiltersQuery($filters, $skipDefaultFilters);
    }

    private function redirectToUsersIndex(string $filtersQuery = ''): never
    {
        $url = route_url('users/index');
        $filtersQuery = ltrim(trim($filtersQuery), '?');
        if ($filtersQuery !== '') {
            $url .= '?' . $filtersQuery;
        }

        $this->response->redirect($url);
    }

    private function resolveManageableUser(int $targetUserId): ?array
    {
        if ($targetUserId <= 0) {
            return null;
        }

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();
        $usersModel = $this->loader->model('users');

        $targetUser = $usersModel->findWithGroup($targetUserId);
        if (!$targetUser) {
            return null;
        }

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);
        $targetLevel = max(1, min(999, (int) ($targetUser['group_hierarchy_level'] ?? 50)));

        return [
            'user' => $targetUser,
            'current_level' => $currentHierarchyLevel,
            'target_level' => $targetLevel,
            'allowed' => $targetLevel >= $currentHierarchyLevel,
        ];
    }

    private function hasUsersListFilterInput(?array $source = null): bool
    {
        $source = is_array($source) ? $source : (array) $this->request->get;
        foreach (['f_q', 'f_group_id', 'f_plan_id', 'f_scope', 'f_user_status', 'f_subscription_status', 'f_override_mode'] as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }

        return false;
    }

    private function loadSavedUsersListFilters(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $rawQuery = trim((string) $this->loader
                ->model('settings')
                ->getValue($this->usersDefaultFiltersSettingKey($userId), ''));
        } catch (\Throwable) {
            return [];
        }
        if ($rawQuery === '') {
            return [];
        }

        parse_str($rawQuery, $parsed);
        if (!is_array($parsed)) {
            return [];
        }

        return $this->normalizeUsersListFilters($parsed);
    }

    private function hasSavedUsersListFilters(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $rawQuery = trim((string) $this->loader
                ->model('settings')
                ->getValue($this->usersDefaultFiltersSettingKey($userId), ''));
        } catch (\Throwable) {
            return false;
        }

        return $rawQuery !== '';
    }

    private function usersDefaultFiltersSettingKey(int $userId): string
    {
        return 'users.default_filters.' . max(1, $userId);
    }
}
