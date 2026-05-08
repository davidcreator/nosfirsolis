<?php

namespace System\Library;

trait SubscriptionServiceSchemaAndPlansTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $requiredTables = [
            'subscription_plans',
            'plan_limits',
            'user_subscriptions',
            'billing_invoices',
            'payment_transactions',
            'subscription_events',
            'billing_promotions',
            'billing_announcements',
            'user_feature_overrides',
        ];

        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema de assinaturas/faturamento ausente. Execute a migracao operacional. '
                . 'missing=' . implode(',', $missing)
            );
            $this->ensured = true;
            return;
        }

        $this->ensureDefaultPlans();
        $this->ensured = true;
    }

    public function publicPlans(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $rows = $this->db()->fetchAll(
            'SELECT *
             FROM subscription_plans
             WHERE status = 1
               AND is_public = 1
             ORDER BY sort_order ASC, price_monthly_cents ASC'
        );

        foreach ($rows as &$row) {
            $planId = (int) ($row['id'] ?? 0);
            $row['limits'] = $this->limitsByPlan($planId);
            $baseMonthly = (int) ($row['price_monthly_cents'] ?? 0);
            $pricing = $this->priceForPlanWithPromotion($planId, $baseMonthly);
            $row['active_promotion'] = $pricing['promotion'];
            $row['price_monthly_base_cents'] = $pricing['subtotal_cents'];
            $row['price_monthly_final_cents'] = $pricing['total_cents'];
            $row['price_monthly_discount_cents'] = $pricing['discount_cents'];
        }
        unset($row);

        return $rows;
    }

    public function recentInvoices(int $userId, int $limit = 20): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(100, $limit));

        return $this->db()->fetchAll(
            'SELECT bi.*, sp.name AS plan_name
             FROM billing_invoices bi
             INNER JOIN subscription_plans sp ON sp.id = bi.plan_id
             WHERE bi.user_id = :user_id
             ORDER BY bi.id DESC
             LIMIT ' . $limit,
            ['user_id' => $userId]
        );
    }

    public function adminPlans(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $rows = $this->db()->fetchAll(
            'SELECT *
             FROM subscription_plans
             ORDER BY sort_order ASC, id ASC'
        );

        foreach ($rows as &$row) {
            $planId = (int) ($row['id'] ?? 0);
            $row['limits'] = $this->limitsByPlan($planId);
            $monthly = (int) ($row['price_monthly_cents'] ?? 0);
            $pricing = $this->priceForPlanWithPromotion($planId, $monthly);
            $row['active_promotion'] = $pricing['promotion'];
            $row['price_monthly_effective_cents'] = $pricing['total_cents'];
            $row['price_monthly_discount_cents'] = $pricing['discount_cents'];
        }
        unset($row);

        return $rows;
    }

    public function savePlanConfig(int $planId, array $payload): array
    {
        if ($planId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Plano invÃƒÂ¡lido para atualizaÃƒÂ§ÃƒÂ£o.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de assinaturas indisponivel para atualizacao.'];
        }
        $plan = $this->db()->fetch('SELECT * FROM subscription_plans WHERE id = :id LIMIT 1', ['id' => $planId]);
        if (!$plan) {
            return ['success' => false, 'message' => 'Plano nÃƒÂ£o encontrado.'];
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return ['success' => false, 'message' => 'Nome do plano deve ter ao menos 3 caracteres.'];
        }

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = $this->defaultCurrency();
        }

        $isFree = $this->truthy($payload['is_free'] ?? 0);
        $monthly = max(0, (int) ($payload['price_monthly_cents'] ?? 0));
        $yearly = max(0, (int) ($payload['price_yearly_cents'] ?? 0));
        if ($isFree) {
            $monthly = 0;
            $yearly = 0;
        }

        $this->db()->update('subscription_plans', [
            'name' => mb_substr($name, 0, 120),
            'description' => mb_substr(trim((string) ($payload['description'] ?? '')), 0, 255),
            'currency' => $currency,
            'price_monthly_cents' => $monthly,
            'price_yearly_cents' => $yearly,
            'is_free' => $isFree ? 1 : 0,
            'ad_supported' => $this->truthy($payload['ad_supported'] ?? 0) ? 1 : 0,
            'is_public' => $this->truthy($payload['is_public'] ?? 0) ? 1 : 0,
            'status' => $this->truthy($payload['status'] ?? 0) ? 1 : 0,
            'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
            'updated_at' => $this->clockDateTimeNow(),
        ], 'id = :id', ['id' => $planId]);

        $limitsPayload = (array) ($payload['limits'] ?? []);
        foreach ($this->planLimitDefinitions() as $limitKey => $type) {
            if ($type === 'bool') {
                $this->upsertLimit($planId, $limitKey, $this->truthy($limitsPayload[$limitKey] ?? 0));
                continue;
            }

            $rawValue = trim((string) ($limitsPayload[$limitKey] ?? '0'));
            if ($rawValue === '' || !preg_match('/^-?\d+$/', $rawValue)) {
                $rawValue = '0';
            }
            $this->upsertLimit($planId, $limitKey, (int) $rawValue);
        }

        $this->contextCache = [];
        return ['success' => true, 'message' => 'Plano atualizado com sucesso.'];
    }
}
