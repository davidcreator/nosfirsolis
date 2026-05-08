<?php

namespace System\Library;

trait SubscriptionServiceAdminOverridesTrait
{
    public function assignPlanToUser(int $userId, int $planId, int $adminUserId = 0): array
    {
        if ($userId <= 0 || $planId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel atualizar o plano do usuario.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de planos indisponivel para atribuicao.'];
        }
        $this->ensureUserSubscription($userId);

        $plan = $this->db()->fetch(
            'SELECT id, name, status
             FROM subscription_plans
             WHERE id = :id
             LIMIT 1',
            ['id' => $planId]
        );
        if (!$plan) {
            return ['success' => false, 'message' => 'Plano nao encontrado.'];
        }

        if ((int) ($plan['status'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'O plano selecionado esta inativo.'];
        }

        $currentContext = $this->contextForUser($userId);
        $currentPlanId = (int) ($currentContext['plan']['id'] ?? 0);
        if ($currentPlanId === $planId) {
            return ['success' => true, 'message' => 'O usuario ja esta neste plano.'];
        }

        $this->activatePlan($userId, $planId, 'active');
        $subscription = $this->subscriptionByUser($userId);
        $this->logEvent(
            $userId,
            $subscription ? (int) ($subscription['id'] ?? 0) : null,
            'plan_changed_by_admin',
            'Plano atualizado manualmente por administrador.',
            [
                'plan_id' => $planId,
                'admin_user_id' => max(0, $adminUserId),
            ]
        );

        $this->invalidateContext($userId);
        return ['success' => true, 'message' => 'Plano do usuario atualizado com sucesso.'];
    }

    public function saveUserFeatureOverrides(int $userId, array $payload, int $adminUserId = 0): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel atualizar os recursos do usuario.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de recursos indisponivel para atualizacao.'];
        }
        $catalog = $this->featureDefinitions();
        if ($catalog === []) {
            return ['success' => false, 'message' => 'Nenhum recurso configuravel foi encontrado.'];
        }

        $normalized = [];
        foreach ($catalog as $featureKey => $meta) {
            $normalized[$featureKey] = $this->truthy($payload[$featureKey] ?? 0) ? 1 : 0;
        }

        $existingRows = $this->db()->fetchAll(
            'SELECT id, feature_key, is_enabled
             FROM user_feature_overrides
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        $existingByKey = [];
        foreach ($existingRows as $row) {
            $existingByKey[(string) ($row['feature_key'] ?? '')] = $row;
        }

        $this->db()->beginTransaction();
        try {
            $now = $this->clockDateTimeNow();
            foreach ($normalized as $featureKey => $state) {
                $existing = $existingByKey[$featureKey] ?? null;
                if ($existing) {
                    $this->db()->update('user_feature_overrides', [
                        'is_enabled' => $state,
                        'updated_at' => $now,
                    ], 'id = :id', ['id' => (int) ($existing['id'] ?? 0)]);
                    continue;
                }

                $this->db()->insert('user_feature_overrides', [
                    'user_id' => $userId,
                    'feature_key' => $featureKey,
                    'is_enabled' => $state,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->db()->commit();
        } catch (\Throwable $exception) {
            $this->db()->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar os recursos personalizados do usuario.'];
        }

        $subscription = $this->subscriptionByUser($userId);
        $this->logEvent(
            $userId,
            $subscription ? (int) ($subscription['id'] ?? 0) : null,
            'feature_overrides_changed_by_admin',
            'Recursos personalizados atualizados por administrador.',
            [
                'admin_user_id' => max(0, $adminUserId),
                'features' => $normalized,
            ]
        );

        $this->invalidateContext($userId);
        return ['success' => true, 'message' => 'Recursos do usuario atualizados com sucesso.'];
    }
}
