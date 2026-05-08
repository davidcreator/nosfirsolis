<?php

namespace System\Library;

trait SubscriptionServiceContextTrait
{
    public function ensureUserSubscription(int $userId): void
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM user_subscriptions WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
        if ($existing) {
            return;
        }

        $freePlan = $this->planBySlug('gratuito');
        if (!$freePlan) {
            $freePlan = $this->db()->fetch(
                'SELECT * FROM subscription_plans WHERE status = 1 ORDER BY is_free DESC, sort_order ASC LIMIT 1'
            );
        }
        if (!$freePlan) {
            return;
        }

        $this->activatePlan($userId, (int) $freePlan['id'], 'active');
    }

    public function contextForUser(int $userId): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return $this->guestContext();
        }

        if (isset($this->contextCache[$userId])) {
            return $this->contextCache[$userId];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            $context = $this->guestContext();
            $this->contextCache[$userId] = $context;
            return $context;
        }
        $this->ensureUserSubscription($userId);

        $subscription = $this->db()->fetch(
            'SELECT us.*, sp.slug AS plan_slug, sp.name AS plan_name, sp.description AS plan_description,
                    sp.currency, sp.price_monthly_cents, sp.price_yearly_cents, sp.is_free, sp.ad_supported
             FROM user_subscriptions us
             INNER JOIN subscription_plans sp ON sp.id = us.plan_id
             WHERE us.user_id = :user_id
             LIMIT 1',
            ['user_id' => $userId]
        );

        if (!$subscription) {
            $context = $this->guestContext();
            $this->contextCache[$userId] = $context;
            return $context;
        }

        $planId = (int) ($subscription['plan_id'] ?? 0);
        $limits = $this->limitsByPlan($planId);
        $usage = $this->usageByUser($userId);
        $featuresFromPlan = [];
        foreach (array_keys($this->featureDefinitions()) as $featureKey) {
            $featuresFromPlan[$featureKey] = $this->boolLimit($limits, $featureKey, true);
        }
        $featureOverrides = $this->featureOverridesByUser($userId);
        $features = $this->applyFeatureOverrides($featuresFromPlan, $featureOverrides);

        $context = [
            'plan' => [
                'id' => $planId,
                'slug' => (string) ($subscription['plan_slug'] ?? 'gratuito'),
                'name' => (string) ($subscription['plan_name'] ?? 'Gratuito'),
                'description' => (string) ($subscription['plan_description'] ?? ''),
                'currency' => (string) ($subscription['currency'] ?? 'BRL'),
                'price_monthly_cents' => (int) ($subscription['price_monthly_cents'] ?? 0),
                'price_yearly_cents' => (int) ($subscription['price_yearly_cents'] ?? 0),
                'is_free' => (bool) ($subscription['is_free'] ?? false),
            ],
            'subscription' => [
                'id' => (int) ($subscription['id'] ?? 0),
                'status' => (string) ($subscription['status'] ?? 'active'),
                'billing_cycle' => (string) ($subscription['billing_cycle'] ?? 'monthly'),
                'started_at' => (string) ($subscription['started_at'] ?? ''),
                'current_period_start' => (string) ($subscription['current_period_start'] ?? ''),
                'current_period_end' => (string) ($subscription['current_period_end'] ?? ''),
                'next_billing_at' => (string) ($subscription['next_billing_at'] ?? ''),
            ],
            'limits' => $limits,
            'usage' => $usage,
            'features' => $features,
            'features_base' => $featuresFromPlan,
            'feature_overrides' => $featureOverrides,
            'ads_enabled' => (bool) ($subscription['ad_supported'] ?? false) || $this->boolLimit($limits, 'ads_enabled', false),
            'metrics' => $this->metricsForView($limits, $usage),
        ];

        $this->contextCache[$userId] = $context;
        return $context;
    }

    private function planLimitDefinitions(): array
    {
        return [
            'max_editorial_plans_per_month' => 'int',
            'max_social_publications_per_month' => 'int',
            'max_social_accounts' => 'int',
            'max_tracking_links_per_month' => 'int',
            'max_calendar_extra_events_per_month' => 'int',
            'ads_enabled' => 'bool',
            'allow_template_plans' => 'bool',
            'allow_ai_draft_generator' => 'bool',
            'allow_format_presets' => 'bool',
            'allow_publish_hub' => 'bool',
            'allow_queue_processing' => 'bool',
            'allow_tracking_links' => 'bool',
            'allow_social_connections' => 'bool',
        ];
    }

    private function featureDefinitions(): array
    {
        return [
            'allow_template_plans' => [
                'label' => 'Templates anuais completos',
                'description' => 'Libera templates prontos para planejamento anual.',
            ],
            'allow_ai_draft_generator' => [
                'label' => 'Gerador de conteudo com IA',
                'description' => 'Permite gerar rascunhos estrategicos automaticamente.',
            ],
            'allow_format_presets' => [
                'label' => 'Presets avancados de formato',
                'description' => 'Habilita presets extras para adaptacao de conteudo.',
            ],
            'allow_publish_hub' => [
                'label' => 'Hub de publicacao social',
                'description' => 'Acesso ao painel de publicacoes multicanal.',
            ],
            'allow_queue_processing' => [
                'label' => 'Processamento automatico da fila',
                'description' => 'Executa a fila de publicacoes de forma automatizada.',
            ],
            'allow_tracking_links' => [
                'label' => 'Rastreamento de campanhas',
                'description' => 'Permite criar links rastreaveis com metricas.',
            ],
            'allow_social_connections' => [
                'label' => 'Conexoes sociais adicionais',
                'description' => 'Libera conexoes extras de contas sociais.',
            ],
        ];
    }

    private function featureOverridesByUser(int $userId): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return [];
        }

        if (!$this->tableExists('user_feature_overrides')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT feature_key, is_enabled
             FROM user_feature_overrides
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        $overrides = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row['feature_key'] ?? '')));
            if ($key === '') {
                continue;
            }

            $overrides[$key] = (int) ($row['is_enabled'] ?? 0) === 1;
        }

        return $overrides;
    }

    private function applyFeatureOverrides(array $featuresFromPlan, array $featureOverrides): array
    {
        if ($featureOverrides === []) {
            return $featuresFromPlan;
        }

        $merged = $featuresFromPlan;
        foreach ($featureOverrides as $featureKey => $isEnabled) {
            if (!array_key_exists($featureKey, $merged)) {
                continue;
            }

            $merged[$featureKey] = (bool) $isEnabled;
        }

        return $merged;
    }

    private function limitsByPlan(int $planId): array
    {
        if ($planId <= 0 || !$this->db()?->connected()) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT limit_key, value_type, int_value, bool_value, text_value
             FROM plan_limits
             WHERE plan_id = :plan_id',
            ['plan_id' => $planId]
        );

        $limits = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['limit_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $type = (string) ($row['value_type'] ?? 'text');
            if ($type === 'int') {
                $limits[$key] = (int) ($row['int_value'] ?? 0);
                continue;
            }
            if ($type === 'bool') {
                $limits[$key] = (int) ($row['bool_value'] ?? 0) === 1;
                continue;
            }

            $limits[$key] = (string) ($row['text_value'] ?? '');
        }

        return $limits;
    }

    private function usageByUser(int $userId): array
    {
        $usage = [
            'editorial_plans' => 0,
            'social_publications' => 0,
            'social_accounts' => 0,
            'tracking_links' => 0,
            'calendar_extra_events' => 0,
        ];

        if ($userId <= 0 || !$this->db()?->connected()) {
            return $usage;
        }

        $periodStart = $this->clockFormat('Y-m-01 00:00:00');
        $periodEnd = $this->clockFormat('Y-m-t 23:59:59');

        $usage['editorial_plans'] = $this->countInPeriod('content_plans', 'user_id', $userId, 'created_at', $periodStart, $periodEnd);
        $usage['social_publications'] = $this->countInPeriod('social_publications', 'user_id', $userId, 'created_at', $periodStart, $periodEnd);
        $usage['tracking_links'] = $this->countInPeriod('campaign_tracking_links', 'user_id', $userId, 'created_at', $periodStart, $periodEnd);
        $usage['calendar_extra_events'] = $this->countInPeriod('calendar_extra_events', 'user_id', $userId, 'created_at', $periodStart, $periodEnd);

        if ($this->tableExists('social_connections')) {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total
                 FROM social_connections
                 WHERE user_id = :user_id
                   AND status IN (\'connected\', \'manual\')',
                ['user_id' => $userId]
            );
            $usage['social_accounts'] = (int) ($row['total'] ?? 0);
        }

        return $usage;
    }

    private function countInPeriod(
        string $table,
        string $userColumn,
        int $userId,
        string $dateColumn,
        string $periodStart,
        string $periodEnd
    ): int {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM ' . $table . '
             WHERE ' . $userColumn . ' = :user_id
               AND ' . $dateColumn . ' BETWEEN :period_start AND :period_end',
            [
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function metricsForView(array $limits, array $usage): array
    {
        $items = [
            ['limit_key' => 'max_editorial_plans_per_month', 'usage_key' => 'editorial_plans', 'label' => 'Planos editoriais/mÃƒÂªs'],
            ['limit_key' => 'max_social_publications_per_month', 'usage_key' => 'social_publications', 'label' => 'PublicaÃƒÂ§ÃƒÂµes sociais/mÃƒÂªs'],
            ['limit_key' => 'max_social_accounts', 'usage_key' => 'social_accounts', 'label' => 'Contas sociais conectadas'],
            ['limit_key' => 'max_tracking_links_per_month', 'usage_key' => 'tracking_links', 'label' => 'Links rastreÃƒÂ¡veis/mÃƒÂªs'],
            ['limit_key' => 'max_calendar_extra_events_per_month', 'usage_key' => 'calendar_extra_events', 'label' => 'Eventos extras/mÃƒÂªs'],
        ];

        $metrics = [];
        foreach ($items as $item) {
            $limit = $this->intLimit($limits, (string) $item['limit_key'], -1);
            $used = (int) ($usage[(string) $item['usage_key']] ?? 0);
            $remaining = $limit < 0 ? null : max(0, $limit - $used);
            $percent = $limit > 0 ? max(0.0, min(100.0, round(($used / $limit) * 100, 2))) : 0.0;

            $metrics[] = [
                'label' => (string) $item['label'],
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'percent' => $percent,
            ];
        }

        return $metrics;
    }

    private function guestContext(): array
    {
        return [
            'plan' => [
                'id' => 0,
                'slug' => 'guest',
                'name' => 'Visitante',
                'description' => '',
                'currency' => $this->defaultCurrency(),
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'is_free' => true,
            ],
            'subscription' => [
                'id' => 0,
                'status' => 'guest',
                'billing_cycle' => 'monthly',
                'started_at' => '',
                'current_period_start' => '',
                'current_period_end' => '',
                'next_billing_at' => '',
            ],
            'limits' => [],
            'usage' => [],
            'features' => [],
            'ads_enabled' => false,
            'metrics' => [],
        ];
    }
}
