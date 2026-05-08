<?php

namespace Admin\Controller\Concerns;

trait UsersControllerSubscriptionTrait
{
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
        $result = $this->subscriptionService()->assignPlanToUser($userId, $planId, $adminUserId);

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
        $result = $this->subscriptionService()->saveUserFeatureOverrides($userId, $payload, $adminUserId);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('users.flash_features_updated', 'Recursos do usuario atualizados com sucesso.')));
            $this->redirectToUsersIndex($returnFiltersQuery);
        }

        flash('error', (string) ($result['message'] ?? $this->t('users.flash_features_update_error', 'Nao foi possivel atualizar os recursos do usuario.')));
        $this->redirectToUsersIndex($returnFiltersQuery);
    }

    protected function resolveManageableUser(int $targetUserId): ?array
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

    protected function enrichUsersWithSubscriptionContext(array $users, int $currentHierarchyLevel): array
    {
        $subscription = $this->subscriptionService();
        $subscription->ensureTables();

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

        return $users;
    }
}
