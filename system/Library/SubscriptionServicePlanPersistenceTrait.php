<?php

namespace System\Library;

trait SubscriptionServicePlanPersistenceTrait
{
    private function ensureDefaultPlans(): void
    {
        foreach ($this->defaultPlans() as $plan) {
            $existing = $this->planBySlug((string) $plan['slug']);
            $timestamp = $this->clockDateTimeNow();

            if ($existing) {
                $planId = (int) $existing['id'];
            } else {
                $planId = $this->db()->insert('subscription_plans', [
                    'slug' => (string) $plan['slug'],
                    'name' => (string) $plan['name'],
                    'description' => (string) $plan['description'],
                    'currency' => (string) $plan['currency'],
                    'price_monthly_cents' => (int) $plan['price_monthly_cents'],
                    'price_yearly_cents' => (int) $plan['price_yearly_cents'],
                    'is_free' => !empty($plan['is_free']) ? 1 : 0,
                    'ad_supported' => !empty($plan['ad_supported']) ? 1 : 0,
                    'is_public' => !empty($plan['is_public']) ? 1 : 0,
                    'status' => !empty($plan['status']) ? 1 : 0,
                    'sort_order' => (int) $plan['sort_order'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            foreach ((array) ($plan['limits'] ?? []) as $limitKey => $limitValue) {
                $limitExists = $this->db()->fetch(
                    'SELECT id
                     FROM plan_limits
                     WHERE plan_id = :plan_id
                       AND limit_key = :limit_key
                     LIMIT 1',
                    [
                        'plan_id' => $planId,
                        'limit_key' => (string) $limitKey,
                    ]
                );
                if ($limitExists) {
                    continue;
                }

                $this->upsertLimit($planId, (string) $limitKey, $limitValue);
            }
        }
    }

    private function upsertLimit(int $planId, string $limitKey, mixed $limitValue): void
    {
        if ($planId <= 0 || trim($limitKey) === '') {
            return;
        }

        $valueType = 'text';
        $intValue = null;
        $boolValue = null;
        $textValue = null;

        if (is_bool($limitValue)) {
            $valueType = 'bool';
            $boolValue = $limitValue ? 1 : 0;
        } elseif (is_int($limitValue)) {
            $valueType = 'int';
            $intValue = $limitValue;
        } else {
            $valueType = 'text';
            $textValue = mb_substr(trim((string) $limitValue), 0, 255);
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM plan_limits WHERE plan_id = :plan_id AND limit_key = :limit_key LIMIT 1',
            [
                'plan_id' => $planId,
                'limit_key' => $limitKey,
            ]
        );

        $timestamp = $this->clockDateTimeNow();
        $payload = [
            'value_type' => $valueType,
            'int_value' => $intValue,
            'bool_value' => $boolValue,
            'text_value' => $textValue,
            'updated_at' => $timestamp,
        ];

        if ($existing) {
            $this->db()->update('plan_limits', $payload, 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db()->insert('plan_limits', array_merge($payload, [
            'plan_id' => $planId,
            'limit_key' => $limitKey,
            'created_at' => $timestamp,
        ]));
    }

    private function subscriptionByUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM user_subscriptions
             WHERE user_id = :user_id
             LIMIT 1',
            ['user_id' => $userId]
        );
    }

    private function activatePlan(int $userId, int $planId, string $status = 'active'): void
    {
        if ($userId <= 0 || $planId <= 0) {
            return;
        }

        $plan = $this->db()->fetch(
            'SELECT is_free FROM subscription_plans WHERE id = :id LIMIT 1',
            ['id' => $planId]
        );
        $isFree = (int) ($plan['is_free'] ?? 0) === 1;

        $subscription = $this->subscriptionByUser($userId);
        $periodStart = $this->clockFormat('Y-m-01 00:00:00');
        $periodEnd = $this->clockFormat('Y-m-t 23:59:59');
        $nextBillingAt = $isFree ? null : $this->nextMonthlyBillingAt();
        $now = $this->clockDateTimeNow();

        if ($subscription) {
            $this->db()->update('user_subscriptions', [
                'plan_id' => $planId,
                'status' => $status,
                'billing_cycle' => 'monthly',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billing_at' => $nextBillingAt,
                'canceled_at' => null,
                'updated_at' => $now,
            ], 'id = :id', ['id' => (int) $subscription['id']]);

            $subscriptionId = (int) $subscription['id'];
        } else {
            $subscriptionId = $this->db()->insert('user_subscriptions', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => $status,
                'billing_cycle' => 'monthly',
                'started_at' => $now,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billing_at' => $nextBillingAt,
                'provider' => 'mock',
                'provider_subscription_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->logEvent($userId, $subscriptionId, 'plan_activated', 'Plano ativado com sucesso.', [
            'plan_id' => $planId,
            'status' => $status,
        ]);
    }

    private function nextMonthlyBillingAt(): string
    {
        $year = (int) $this->clockFormat('Y');
        $month = (int) $this->clockFormat('n') + 1;

        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return sprintf('%04d-%02d-01 00:00:00', $year, $month);
    }

    private function planBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM subscription_plans
             WHERE slug = :slug
             LIMIT 1',
            ['slug' => $slug]
        );
    }

    private function intLimit(array $limits, string $key, int $default = -1): int
    {
        if (!array_key_exists($key, $limits)) {
            return $default;
        }

        $value = $limits[$key];
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return $default;
        }

        if (!preg_match('/^-?\d+$/', $normalized)) {
            return $default;
        }

        return (int) $normalized;
    }

    private function boolLimit(array $limits, string $key, bool $default = true): bool
    {
        if (!array_key_exists($key, $limits)) {
            return $default;
        }

        $value = $limits[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '' || !$this->db()?->connected()) {
            return false;
        }

        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $row = $this->db()->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1",
            ['table_name' => $table]
        );

        $exists = $row !== null;
        $this->tableCache[$table] = $exists;
        return $exists;
    }

    private function invalidateContext(int $userId): void
    {
        unset($this->contextCache[$userId]);
    }

    private function defaultPlans(): array
    {
        return [
            [
                'slug' => 'gratuito',
                'name' => 'BÃƒÂ¡sico Gratuito',
                'description' => 'Plano de entrada com propaganda, recursos essenciais e limites de uso.',
                'currency' => 'BRL',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'is_free' => true,
                'ad_supported' => true,
                'is_public' => true,
                'status' => true,
                'sort_order' => 10,
                'limits' => [
                    'max_editorial_plans_per_month' => 2,
                    'max_social_publications_per_month' => 20,
                    'max_social_accounts' => 1,
                    'max_tracking_links_per_month' => 15,
                    'max_calendar_extra_events_per_month' => 12,
                    'ads_enabled' => true,
                    'allow_template_plans' => false,
                    'allow_ai_draft_generator' => false,
                    'allow_format_presets' => false,
                    'allow_publish_hub' => true,
                    'allow_queue_processing' => false,
                    'allow_tracking_links' => true,
                    'allow_social_connections' => true,
                ],
            ],
            [
                'slug' => 'bronze',
                'name' => 'Plano Bronze',
                'description' => 'Menos restriÃƒÂ§ÃƒÂµes para operaÃƒÂ§ÃƒÂ£o diÃƒÂ¡ria, com mais postagens e integraÃƒÂ§ÃƒÂµes.',
                'currency' => 'BRL',
                'price_monthly_cents' => 7900,
                'price_yearly_cents' => 75840,
                'is_free' => false,
                'ad_supported' => false,
                'is_public' => true,
                'status' => true,
                'sort_order' => 20,
                'limits' => [
                    'max_editorial_plans_per_month' => 8,
                    'max_social_publications_per_month' => 120,
                    'max_social_accounts' => 4,
                    'max_tracking_links_per_month' => 120,
                    'max_calendar_extra_events_per_month' => 60,
                    'ads_enabled' => false,
                    'allow_template_plans' => true,
                    'allow_ai_draft_generator' => true,
                    'allow_format_presets' => true,
                    'allow_publish_hub' => true,
                    'allow_queue_processing' => true,
                    'allow_tracking_links' => true,
                    'allow_social_connections' => true,
                ],
            ],
            [
                'slug' => 'prata',
                'name' => 'Plano Prata',
                'description' => 'Plano intermediÃƒÂ¡rio com mais recursos avanÃƒÂ§ados, volume de posts e escalabilidade.',
                'currency' => 'BRL',
                'price_monthly_cents' => 15900,
                'price_yearly_cents' => 152640,
                'is_free' => false,
                'ad_supported' => false,
                'is_public' => true,
                'status' => true,
                'sort_order' => 30,
                'limits' => [
                    'max_editorial_plans_per_month' => 20,
                    'max_social_publications_per_month' => 400,
                    'max_social_accounts' => 10,
                    'max_tracking_links_per_month' => 400,
                    'max_calendar_extra_events_per_month' => 200,
                    'ads_enabled' => false,
                    'allow_template_plans' => true,
                    'allow_ai_draft_generator' => true,
                    'allow_format_presets' => true,
                    'allow_publish_hub' => true,
                    'allow_queue_processing' => true,
                    'allow_tracking_links' => true,
                    'allow_social_connections' => true,
                ],
            ],
            [
                'slug' => 'ouro',
                'name' => 'Plano Ouro',
                'description' => 'Todos os recursos liberados, sem limite de postagens e sem propaganda.',
                'currency' => 'BRL',
                'price_monthly_cents' => 32900,
                'price_yearly_cents' => 315840,
                'is_free' => false,
                'ad_supported' => false,
                'is_public' => true,
                'status' => true,
                'sort_order' => 40,
                'limits' => [
                    'max_editorial_plans_per_month' => -1,
                    'max_social_publications_per_month' => -1,
                    'max_social_accounts' => -1,
                    'max_tracking_links_per_month' => -1,
                    'max_calendar_extra_events_per_month' => -1,
                    'ads_enabled' => false,
                    'allow_template_plans' => true,
                    'allow_ai_draft_generator' => true,
                    'allow_format_presets' => true,
                    'allow_publish_hub' => true,
                    'allow_queue_processing' => true,
                    'allow_tracking_links' => true,
                    'allow_social_connections' => true,
                ],
            ],
        ];
    }
}
