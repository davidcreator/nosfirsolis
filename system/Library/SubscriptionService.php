<?php

namespace System\Library;

use System\Engine\Config;
use System\Engine\Registry;

class SubscriptionService
{
    private bool $ensured = false;
    private array $contextCache = [];
    private array $tableCache = [];
    private array $settingsCache = [];

    public function __construct(private readonly Registry $registry)
    {
    }

    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS subscription_plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(40) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(255) NULL,
                currency CHAR(3) NOT NULL DEFAULT \'BRL\',
                price_monthly_cents INT UNSIGNED NOT NULL DEFAULT 0,
                price_yearly_cents INT UNSIGNED NOT NULL DEFAULT 0,
                is_free TINYINT(1) NOT NULL DEFAULT 0,
                ad_supported TINYINT(1) NOT NULL DEFAULT 0,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                status TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS plan_limits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_id INT UNSIGNED NOT NULL,
                limit_key VARCHAR(120) NOT NULL,
                value_type ENUM(\'int\', \'bool\', \'text\') NOT NULL DEFAULT \'int\',
                int_value INT NULL,
                bool_value TINYINT(1) NULL,
                text_value VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY ux_plan_limit_key (plan_id, limit_key),
                CONSTRAINT fk_plan_limits_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS user_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                plan_id INT UNSIGNED NOT NULL,
                status ENUM(\'trial\', \'active\', \'past_due\', \'suspended\', \'canceled\') NOT NULL DEFAULT \'active\',
                billing_cycle ENUM(\'monthly\', \'yearly\') NOT NULL DEFAULT \'monthly\',
                started_at DATETIME NOT NULL,
                current_period_start DATETIME NULL,
                current_period_end DATETIME NULL,
                next_billing_at DATETIME NULL,
                canceled_at DATETIME NULL,
                provider VARCHAR(40) NULL,
                provider_subscription_id VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY ux_user_subscriptions_user (user_id),
                INDEX idx_user_subscriptions_plan (plan_id, status),
                CONSTRAINT fk_user_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS billing_invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                subscription_id INT UNSIGNED NULL,
                plan_id INT UNSIGNED NOT NULL,
                invoice_number VARCHAR(40) NULL UNIQUE,
                status ENUM(\'open\', \'paid\', \'void\', \'failed\') NOT NULL DEFAULT \'open\',
                currency CHAR(3) NOT NULL DEFAULT \'BRL\',
                subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
                total_cents INT UNSIGNED NOT NULL DEFAULT 0,
                payment_method VARCHAR(40) NULL,
                provider VARCHAR(40) NOT NULL DEFAULT \'mock\',
                provider_invoice_id VARCHAR(120) NULL,
                description VARCHAR(255) NULL,
                due_at DATETIME NULL,
                paid_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_billing_invoices_user (user_id, status, created_at),
                CONSTRAINT fk_billing_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_billing_invoices_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL,
                CONSTRAINT fk_billing_invoices_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS payment_transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                invoice_id INT UNSIGNED NOT NULL,
                status ENUM(\'pending\', \'paid\', \'failed\', \'refunded\') NOT NULL DEFAULT \'pending\',
                provider VARCHAR(40) NOT NULL DEFAULT \'mock\',
                payment_method VARCHAR(40) NULL,
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT \'BRL\',
                provider_transaction_id VARCHAR(120) NULL,
                payload_json LONGTEXT NULL,
                processed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_payment_transactions_user (user_id, status, created_at),
                INDEX idx_payment_transactions_invoice (invoice_id),
                CONSTRAINT fk_payment_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_payment_transactions_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS subscription_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                subscription_id INT UNSIGNED NULL,
                event_key VARCHAR(80) NOT NULL,
                message VARCHAR(255) NULL,
                payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_subscription_events_user (user_id, created_at),
                CONSTRAINT fk_subscription_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS billing_promotions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(140) NOT NULL,
                code VARCHAR(60) NULL UNIQUE,
                description VARCHAR(255) NULL,
                plan_id INT UNSIGNED NULL,
                discount_type ENUM(\'percent\', \'amount\') NOT NULL DEFAULT \'percent\',
                discount_value INT UNSIGNED NOT NULL DEFAULT 0,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_billing_promotions_status (status, starts_at, ends_at),
                CONSTRAINT fk_billing_promotions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS billing_announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                announcement_type ENUM(\'discount\', \'reajuste\', \'informativo\') NOT NULL DEFAULT \'informativo\',
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_billing_announcements_status (status, starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensureDefaultPlans();
        $this->ensured = true;
    }

    public function ensureUserSubscription(int $userId): void
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return;
        }

        $this->ensureTables();

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
        $features = [
            'allow_template_plans' => $this->boolLimit($limits, 'allow_template_plans', true),
            'allow_ai_draft_generator' => $this->boolLimit($limits, 'allow_ai_draft_generator', true),
            'allow_format_presets' => $this->boolLimit($limits, 'allow_format_presets', true),
            'allow_publish_hub' => $this->boolLimit($limits, 'allow_publish_hub', true),
            'allow_queue_processing' => $this->boolLimit($limits, 'allow_queue_processing', true),
            'allow_tracking_links' => $this->boolLimit($limits, 'allow_tracking_links', true),
            'allow_social_connections' => $this->boolLimit($limits, 'allow_social_connections', true),
        ];

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
            'ads_enabled' => (bool) ($subscription['ad_supported'] ?? false) || $this->boolLimit($limits, 'ads_enabled', false),
            'metrics' => $this->metricsForView($limits, $usage),
        ];

        $this->contextCache[$userId] = $context;
        return $context;
    }

    public function publicPlans(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
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
            return ['success' => false, 'message' => 'Plano inválido para atualização.'];
        }

        $this->ensureTables();
        $plan = $this->db()->fetch('SELECT * FROM subscription_plans WHERE id = :id LIMIT 1', ['id' => $planId]);
        if (!$plan) {
            return ['success' => false, 'message' => 'Plano não encontrado.'];
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
            'updated_at' => date('Y-m-d H:i:s'),
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

    public function listPromotions(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT bp.*, sp.name AS plan_name
             FROM billing_promotions bp
             LEFT JOIN subscription_plans sp ON sp.id = bp.plan_id
             ORDER BY bp.id DESC
             LIMIT ' . $limit
        );
    }

    public function savePromotion(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Não foi possível salvar promoção agora.'];
        }

        $this->ensureTables();
        $id = max(0, (int) ($payload['id'] ?? 0));
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return ['success' => false, 'message' => 'Nome da promoção deve ter ao menos 3 caracteres.'];
        }

        $planId = max(0, (int) ($payload['plan_id'] ?? 0));
        if ($planId <= 0) {
            $planId = null;
        }

        $discountType = strtolower(trim((string) ($payload['discount_type'] ?? 'percent')));
        if (!in_array($discountType, ['percent', 'amount'], true)) {
            $discountType = 'percent';
        }

        $discountValue = max(1, (int) ($payload['discount_value'] ?? 0));
        if ($discountType === 'percent') {
            $discountValue = min(100, $discountValue);
        }

        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?: '';
        if ($code === '') {
            $code = null;
        }

        $startsAt = $this->normalizeDatetime($payload['starts_at'] ?? null);
        $endsAt = $this->normalizeDatetime($payload['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            return ['success' => false, 'message' => 'Período de promoção inválido: fim menor que início.'];
        }

        $description = mb_substr(trim((string) ($payload['description'] ?? '')), 0, 255);
        $status = $this->truthy($payload['status'] ?? 0) ? 1 : 0;
        $isPublic = $this->truthy($payload['is_public'] ?? 0) ? 1 : 0;

        if ($code !== null) {
            $existingCode = $this->db()->fetch(
                'SELECT id
                 FROM billing_promotions
                 WHERE code = :code
                   AND id <> :id
                 LIMIT 1',
                ['code' => $code, 'id' => $id]
            );
            if ($existingCode) {
                return ['success' => false, 'message' => 'Código de promoção já está em uso.'];
            }
        }

        $data = [
            'name' => mb_substr($name, 0, 140),
            'code' => $code,
            'description' => $description !== '' ? $description : null,
            'plan_id' => $planId,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_public' => $isPublic,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->db()->update('billing_promotions', $data, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Promoção atualizada com sucesso.'];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db()->insert('billing_promotions', $data);
        return ['success' => true, 'message' => 'Promoção cadastrada com sucesso.'];
    }

    public function deletePromotion(int $promotionId): bool
    {
        if ($promotionId <= 0 || !$this->db()?->connected()) {
            return false;
        }

        $this->ensureTables();
        return $this->db()->delete('billing_promotions', 'id = :id', ['id' => $promotionId]) > 0;
    }

    public function listAnnouncements(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT *
             FROM billing_announcements
             ORDER BY id DESC
             LIMIT ' . $limit
        );
    }

    public function activeAnnouncements(int $limit = 10): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $now = date('Y-m-d H:i:s');

        return $this->db()->fetchAll(
            'SELECT *
             FROM billing_announcements
             WHERE status = 1
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY starts_at DESC, id DESC
             LIMIT ' . $limit,
            [
                'now_start' => $now,
                'now_end' => $now,
            ]
        );
    }

    public function saveAnnouncement(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Não foi possível salvar comunicado agora.'];
        }

        $this->ensureTables();
        $id = max(0, (int) ($payload['id'] ?? 0));
        $title = trim((string) ($payload['title'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if (mb_strlen($title) < 3) {
            return ['success' => false, 'message' => 'Título do comunicado deve ter ao menos 3 caracteres.'];
        }
        if (mb_strlen($message) < 8) {
            return ['success' => false, 'message' => 'Mensagem do comunicado deve ter ao menos 8 caracteres.'];
        }

        $type = strtolower(trim((string) ($payload['announcement_type'] ?? 'informativo')));
        if (!in_array($type, ['discount', 'reajuste', 'informativo'], true)) {
            $type = 'informativo';
        }

        $startsAt = $this->normalizeDatetime($payload['starts_at'] ?? null);
        $endsAt = $this->normalizeDatetime($payload['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            return ['success' => false, 'message' => 'Período do comunicado inválido.'];
        }

        $data = [
            'title' => mb_substr($title, 0, 180),
            'message' => mb_substr($message, 0, 2000),
            'announcement_type' => $type,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $this->truthy($payload['status'] ?? 0) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->db()->update('billing_announcements', $data, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Comunicado atualizado com sucesso.'];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db()->insert('billing_announcements', $data);
        return ['success' => true, 'message' => 'Comunicado criado com sucesso.'];
    }

    public function deleteAnnouncement(int $announcementId): bool
    {
        if ($announcementId <= 0 || !$this->db()?->connected()) {
            return false;
        }

        $this->ensureTables();
        return $this->db()->delete('billing_announcements', 'id = :id', ['id' => $announcementId]) > 0;
    }

    public function billingSettings(): array
    {
        if (isset($this->settingsCache['_billing_settings']) && is_array($this->settingsCache['_billing_settings'])) {
            return $this->settingsCache['_billing_settings'];
        }

        $configuredCurrency = strtoupper(trim((string) $this->config()?->get('integrations.billing.currency', 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $configuredCurrency)) {
            $configuredCurrency = 'BRL';
        }

        $validationMode = strtolower(trim((string) $this->settingValue('billing.validation_mode', 'automatic')));
        if (!in_array($validationMode, ['automatic', 'manual'], true)) {
            $validationMode = 'automatic';
        }

        $settings = [
            'currency' => strtoupper(trim((string) $this->settingValue('billing.currency', $configuredCurrency))),
            'receiver_name' => (string) $this->settingValue('billing.receiver_name', ''),
            'receiver_document' => (string) $this->settingValue('billing.receiver_document', ''),
            'receiver_bank' => (string) $this->settingValue('billing.receiver_bank', ''),
            'receiver_agency' => (string) $this->settingValue('billing.receiver_agency', ''),
            'receiver_account' => (string) $this->settingValue('billing.receiver_account', ''),
            'receiver_account_type' => (string) $this->settingValue('billing.receiver_account_type', 'checking'),
            'receiver_pix_key' => (string) $this->settingValue('billing.receiver_pix_key', ''),
            'receiver_email' => (string) $this->settingValue('billing.receiver_email', ''),
            'validation_mode' => $validationMode,
            'mock_auto_approve' => $this->truthy($this->settingValue('billing.mock_auto_approve', '1')),
            'validation_notes' => (string) $this->settingValue('billing.validation_notes', ''),
            'methods' => [
                'pix' => $this->truthy($this->settingValue('billing.method.pix', '1')),
                'boleto' => $this->truthy($this->settingValue('billing.method.boleto', '1')),
                'card' => $this->truthy($this->settingValue('billing.method.card', '1')),
                'transfer' => $this->truthy($this->settingValue('billing.method.transfer', '0')),
            ],
        ];

        if (!preg_match('/^[A-Z]{3}$/', $settings['currency'])) {
            $settings['currency'] = $configuredCurrency;
        }

        $this->settingsCache['_billing_settings'] = $settings;
        return $settings;
    }

    public function saveBillingSettings(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Não foi possível salvar configurações de pagamento.'];
        }

        $this->ensureTables();
        if (!$this->tableExists('settings')) {
            return ['success' => false, 'message' => 'Tabela de configurações indisponível para salvar billing.'];
        }

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'BRL';
        }

        $validationMode = strtolower(trim((string) ($payload['validation_mode'] ?? 'automatic')));
        if (!in_array($validationMode, ['automatic', 'manual'], true)) {
            $validationMode = 'automatic';
        }

        $methodPix = $this->truthy($payload['method_pix'] ?? 0);
        $methodBoleto = $this->truthy($payload['method_boleto'] ?? 0);
        $methodCard = $this->truthy($payload['method_card'] ?? 0);
        $methodTransfer = $this->truthy($payload['method_transfer'] ?? 0);
        if (!$methodPix && !$methodBoleto && !$methodCard && !$methodTransfer) {
            $methodPix = true;
        }

        $this->saveSettingValue('billing.currency', $currency);
        $this->saveSettingValue('billing.receiver_name', mb_substr(trim((string) ($payload['receiver_name'] ?? '')), 0, 140));
        $this->saveSettingValue('billing.receiver_document', mb_substr(trim((string) ($payload['receiver_document'] ?? '')), 0, 80));
        $this->saveSettingValue('billing.receiver_bank', mb_substr(trim((string) ($payload['receiver_bank'] ?? '')), 0, 120));
        $this->saveSettingValue('billing.receiver_agency', mb_substr(trim((string) ($payload['receiver_agency'] ?? '')), 0, 40));
        $this->saveSettingValue('billing.receiver_account', mb_substr(trim((string) ($payload['receiver_account'] ?? '')), 0, 60));
        $this->saveSettingValue('billing.receiver_account_type', mb_substr(trim((string) ($payload['receiver_account_type'] ?? 'checking')), 0, 40));
        $this->saveSettingValue('billing.receiver_pix_key', mb_substr(trim((string) ($payload['receiver_pix_key'] ?? '')), 0, 120));
        $this->saveSettingValue('billing.receiver_email', mb_substr(trim((string) ($payload['receiver_email'] ?? '')), 0, 140));
        $this->saveSettingValue('billing.validation_mode', $validationMode);
        $this->saveSettingValue('billing.mock_auto_approve', $this->truthy($payload['mock_auto_approve'] ?? 0) ? '1' : '0');
        $this->saveSettingValue('billing.validation_notes', mb_substr(trim((string) ($payload['validation_notes'] ?? '')), 0, 2000));
        $this->saveSettingValue('billing.method.pix', $methodPix ? '1' : '0');
        $this->saveSettingValue('billing.method.boleto', $methodBoleto ? '1' : '0');
        $this->saveSettingValue('billing.method.card', $methodCard ? '1' : '0');
        $this->saveSettingValue('billing.method.transfer', $methodTransfer ? '1' : '0');

        unset($this->settingsCache['_billing_settings']);
        return ['success' => true, 'message' => 'Configurações de pagamento atualizadas com sucesso.'];
    }

    public function paymentMethodsForCheckout(): array
    {
        $settings = $this->billingSettings();
        $methods = (array) ($settings['methods'] ?? []);

        $map = [
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'card' => 'Cartão',
            'transfer' => 'Transferência',
        ];

        $available = [];
        foreach ($map as $key => $label) {
            if (!empty($methods[$key])) {
                $available[] = ['key' => $key, 'label' => $label];
            }
        }

        if ($available === []) {
            $available[] = ['key' => 'pix', 'label' => 'PIX'];
        }

        return $available;
    }

    public function pendingValidations(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT pt.*, bi.invoice_number, bi.status AS invoice_status, bi.plan_id, bi.total_cents,
                    sp.name AS plan_name, u.name AS user_name, u.email AS user_email
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             INNER JOIN users u ON u.id = pt.user_id
             LEFT JOIN subscription_plans sp ON sp.id = bi.plan_id
             WHERE pt.status = \'pending\'
             ORDER BY pt.id DESC
             LIMIT ' . $limit
        );
    }

    public function approvePaymentTransaction(int $transactionId, int $adminUserId = 0, string $note = ''): array
    {
        if ($transactionId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Transação inválida para aprovação.'];
        }

        $this->ensureTables();
        $row = $this->db()->fetch(
            'SELECT pt.*, bi.subscription_id, bi.plan_id, bi.status AS invoice_status
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             WHERE pt.id = :id
             LIMIT 1',
            ['id' => $transactionId]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Transação não encontrada.'];
        }

        $status = (string) ($row['status'] ?? 'pending');
        if ($status === 'paid') {
            return ['success' => true, 'message' => 'Transação já estava aprovada.'];
        }
        if ($status !== 'pending') {
            return ['success' => false, 'message' => 'Apenas transações pendentes podem ser aprovadas.'];
        }

        $payload = $this->decodeJsonPayload((string) ($row['payload_json'] ?? ''));
        $payload['validated_by_admin_id'] = $adminUserId;
        $payload['validated_at'] = date('Y-m-d H:i:s');
        if (trim($note) !== '') {
            $payload['validation_note'] = mb_substr(trim($note), 0, 255);
        }

        $this->db()->update('payment_transactions', [
            'status' => 'paid',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $transactionId]);

        $invoiceId = (int) ($row['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $paymentMethod = (string) ($row['payment_method'] ?? 'pix');
            $this->markInvoicePaid($invoiceId, $paymentMethod);
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $planId = (int) ($row['plan_id'] ?? 0);
        if ($userId > 0 && $planId > 0) {
            $this->activatePlan($userId, $planId, 'active');
            $this->invalidateContext($userId);
        }

        $this->logEvent(
            $userId,
            isset($row['subscription_id']) ? (int) $row['subscription_id'] : null,
            'invoice_paid_manual_validation',
            'Pagamento aprovado manualmente pelo admin.',
            [
                'transaction_id' => $transactionId,
                'invoice_id' => $invoiceId,
                'admin_user_id' => $adminUserId,
            ]
        );

        return ['success' => true, 'message' => 'Pagamento validado e aprovado com sucesso.'];
    }

    public function rejectPaymentTransaction(int $transactionId, int $adminUserId = 0, string $reason = ''): array
    {
        if ($transactionId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Transação inválida para rejeição.'];
        }

        $this->ensureTables();
        $row = $this->db()->fetch(
            'SELECT pt.*, bi.subscription_id, bi.status AS invoice_status
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             WHERE pt.id = :id
             LIMIT 1',
            ['id' => $transactionId]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Transação não encontrada.'];
        }

        $status = (string) ($row['status'] ?? 'pending');
        if ($status === 'failed') {
            return ['success' => true, 'message' => 'Transação já estava rejeitada.'];
        }
        if ($status !== 'pending') {
            return ['success' => false, 'message' => 'Apenas transações pendentes podem ser rejeitadas.'];
        }

        $payload = $this->decodeJsonPayload((string) ($row['payload_json'] ?? ''));
        $payload['rejected_by_admin_id'] = $adminUserId;
        $payload['rejected_at'] = date('Y-m-d H:i:s');
        if (trim($reason) !== '') {
            $payload['rejection_reason'] = mb_substr(trim($reason), 0, 255);
        }

        $this->db()->update('payment_transactions', [
            'status' => 'failed',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $transactionId]);

        $invoiceId = (int) ($row['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $this->db()->update('billing_invoices', [
                'status' => 'failed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $invoiceId]);
        }

        $this->logEvent(
            (int) ($row['user_id'] ?? 0),
            isset($row['subscription_id']) ? (int) $row['subscription_id'] : null,
            'invoice_rejected_manual_validation',
            'Pagamento rejeitado manualmente pelo admin.',
            [
                'transaction_id' => $transactionId,
                'invoice_id' => $invoiceId,
                'admin_user_id' => $adminUserId,
                'reason' => mb_substr(trim($reason), 0, 255),
            ]
        );

        return ['success' => true, 'message' => 'Pagamento rejeitado e marcado para revisão.'];
    }

    public function evaluateFeature(int $userId, string $featureKey): array
    {
        $featureKey = strtolower(trim($featureKey));
        $context = $this->contextForUser($userId);
        $allowed = (bool) ($context['features'][$featureKey] ?? true);

        if ($allowed) {
            return ['allowed' => true, 'message' => ''];
        }

        $labels = [
            'allow_template_plans' => 'templates anuais completos',
            'allow_ai_draft_generator' => 'gerador de conteúdo estratégico',
            'allow_format_presets' => 'presets avançados de formato',
            'allow_publish_hub' => 'hub de publicação social',
            'allow_queue_processing' => 'processamento automático da fila',
            'allow_tracking_links' => 'rastreamento de campanhas',
            'allow_social_connections' => 'conexões sociais adicionais',
        ];

        return [
            'allowed' => false,
            'message' => sprintf(
                'O recurso "%s" não está disponível no plano %s. Faça upgrade em Planos e Faturamento.',
                $labels[$featureKey] ?? $featureKey,
                (string) ($context['plan']['name'] ?? 'atual')
            ),
        ];
    }

    public function evaluateQuota(int $userId, string $metricKey, int $increment = 1): array
    {
        $metricKey = strtolower(trim($metricKey));
        $increment = max(1, $increment);
        $context = $this->contextForUser($userId);

        $metricMap = [
            'max_editorial_plans_per_month' => ['usage_key' => 'editorial_plans', 'label' => 'planos editoriais no mês'],
            'max_social_publications_per_month' => ['usage_key' => 'social_publications', 'label' => 'publicações sociais no mês'],
            'max_social_accounts' => ['usage_key' => 'social_accounts', 'label' => 'contas sociais conectadas'],
            'max_tracking_links_per_month' => ['usage_key' => 'tracking_links', 'label' => 'links de rastreamento no mês'],
            'max_calendar_extra_events_per_month' => ['usage_key' => 'calendar_extra_events', 'label' => 'eventos extras de calendário no mês'],
        ];

        if (!isset($metricMap[$metricKey])) {
            return ['allowed' => true, 'message' => ''];
        }

        $usageKey = (string) $metricMap[$metricKey]['usage_key'];
        $label = (string) $metricMap[$metricKey]['label'];
        $current = (int) ($context['usage'][$usageKey] ?? 0);
        $limit = $this->intLimit((array) ($context['limits'] ?? []), $metricKey, -1);

        if ($limit < 0) {
            return ['allowed' => true, 'message' => ''];
        }

        if (($current + $increment) <= $limit) {
            return ['allowed' => true, 'message' => ''];
        }

        return [
            'allowed' => false,
            'message' => sprintf(
                'Limite do plano %s atingido para %s: %d/%d. Faça upgrade em Planos e Faturamento.',
                (string) ($context['plan']['name'] ?? 'atual'),
                $label,
                $current,
                $limit
            ),
        ];
    }

    public function changePlan(int $userId, string $planSlug, string $paymentMethod = 'pix'): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Não foi possível atualizar seu plano no momento.'];
        }

        $paymentMethod = $this->sanitizePaymentMethod($paymentMethod);

        $this->ensureTables();
        $this->ensureUserSubscription($userId);

        $targetPlan = $this->planBySlug($planSlug);
        if (!$targetPlan || (int) ($targetPlan['status'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Plano selecionado é inválido ou está indisponível.'];
        }

        $current = $this->contextForUser($userId);
        $currentPlanId = (int) ($current['plan']['id'] ?? 0);
        $targetPlanId = (int) ($targetPlan['id'] ?? 0);

        if ($currentPlanId === $targetPlanId) {
            return ['success' => true, 'message' => 'Seu plano já está ativo.'];
        }

        $subscription = $this->subscriptionByUser($userId);
        if (!$subscription) {
            return ['success' => false, 'message' => 'Assinatura não encontrada para este usuário.'];
        }

        $amountCents = (int) ($targetPlan['price_monthly_cents'] ?? 0);
        if ((int) ($targetPlan['is_free'] ?? 0) === 1 || $amountCents <= 0) {
            $this->activatePlan($userId, $targetPlanId, 'active');
            $invoiceId = $this->createInvoice(
                $userId,
                (int) ($subscription['id'] ?? 0),
                $targetPlanId,
                0,
                $paymentMethod,
                'Ativação de plano gratuito'
            );
            $this->markInvoicePaid($invoiceId, $paymentMethod);
            $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, 0, (string) ($targetPlan['currency'] ?? 'BRL'), [
                'provider' => 'mock',
                'note' => 'free_plan_activation',
            ]);

            $this->invalidateContext($userId);
            return ['success' => true, 'message' => 'Plano gratuito ativado com sucesso.'];
        }

        $pricing = $this->priceForPlanWithPromotion($targetPlanId, $amountCents);
        $discountCents = (int) ($pricing['discount_cents'] ?? 0);
        $finalAmountCents = (int) ($pricing['total_cents'] ?? $amountCents);
        $promotion = is_array($pricing['promotion'] ?? null) ? $pricing['promotion'] : null;
        $description = 'Upgrade para plano ' . (string) ($targetPlan['name'] ?? $planSlug);
        if ($promotion !== null && $discountCents > 0) {
            $description .= ' com promoção ' . (string) ($promotion['name'] ?? 'ativa');
        }

        if ($finalAmountCents <= 0) {
            $this->activatePlan($userId, $targetPlanId, 'active');
            $invoiceId = $this->createInvoice(
                $userId,
                (int) ($subscription['id'] ?? 0),
                $targetPlanId,
                0,
                $paymentMethod,
                $description,
                $amountCents
            );
            $this->markInvoicePaid($invoiceId, $paymentMethod);
            $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, 0, (string) ($targetPlan['currency'] ?? 'BRL'), [
                'provider' => 'mock',
                'note' => 'promotion_full_discount',
            ]);

            $this->invalidateContext($userId);
            return ['success' => true, 'message' => 'Plano atualizado com desconto promocional integral.'];
        }

        $invoiceId = $this->createInvoice(
            $userId,
            (int) ($subscription['id'] ?? 0),
            $targetPlanId,
            $finalAmountCents,
            $paymentMethod,
            $description,
            $amountCents
        );

        if ($this->mockAutoApprove()) {
            return $this->payInvoice($userId, $invoiceId, $paymentMethod);
        }

        $this->invalidateContext($userId);
        return [
            'success' => true,
            'message' => 'Fatura criada com sucesso. Finalize o pagamento para ativar o novo plano.',
            'invoice_id' => $invoiceId,
        ];
    }

    public function payInvoice(int $userId, int $invoiceId, string $paymentMethod = 'pix'): array
    {
        if ($userId <= 0 || $invoiceId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Pagamento não pode ser processado.'];
        }

        $paymentMethod = $this->sanitizePaymentMethod($paymentMethod);

        $this->ensureTables();

        $invoice = $this->db()->fetch(
            'SELECT *
             FROM billing_invoices
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1',
            [
                'id' => $invoiceId,
                'user_id' => $userId,
            ]
        );
        if (!$invoice) {
            return ['success' => false, 'message' => 'Fatura não encontrada para este usuário.'];
        }

        if ((string) ($invoice['status'] ?? '') === 'paid') {
            return ['success' => true, 'message' => 'Esta fatura já está paga.'];
        }

        $amount = (int) ($invoice['total_cents'] ?? 0);
        $currency = (string) ($invoice['currency'] ?? 'BRL');
        $planId = (int) ($invoice['plan_id'] ?? 0);

        if ($this->manualPaymentValidationEnabled()) {
            $pending = $this->db()->fetch(
                'SELECT id
                 FROM payment_transactions
                 WHERE invoice_id = :invoice_id
                   AND status = \'pending\'
                 ORDER BY id DESC
                 LIMIT 1',
                ['invoice_id' => $invoiceId]
            );
            if ($pending) {
                return ['success' => true, 'message' => 'Pagamento já enviado para validação manual.'];
            }

            $this->insertTransaction($userId, $invoiceId, 'pending', $paymentMethod, $amount, $currency, [
                'provider' => 'mock',
                'gateway' => 'simulado',
                'submitted_at' => date('Y-m-d H:i:s'),
                'validation_mode' => 'manual',
            ]);

            $this->db()->update('billing_invoices', [
                'payment_method' => mb_substr(strtolower(trim($paymentMethod)), 0, 40),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $invoiceId]);

            $this->logEvent(
                $userId,
                isset($invoice['subscription_id']) ? (int) $invoice['subscription_id'] : null,
                'payment_validation_requested',
                'Pagamento enviado para validação manual.',
                [
                    'invoice_id' => $invoiceId,
                    'plan_id' => $planId,
                    'payment_method' => $paymentMethod,
                    'amount_cents' => $amount,
                ]
            );

            return ['success' => true, 'message' => 'Pagamento enviado para validação. O plano será ativado após aprovação do admin.'];
        }

        $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, $amount, $currency, [
            'provider' => 'mock',
            'gateway' => 'simulado',
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $this->markInvoicePaid($invoiceId, $paymentMethod);
        if ($planId > 0) {
            $this->activatePlan($userId, $planId, 'active');
        }

        $this->logEvent(
            $userId,
            isset($invoice['subscription_id']) ? (int) $invoice['subscription_id'] : null,
            'invoice_paid',
            'Fatura paga e plano atualizado.',
            [
                'invoice_id' => $invoiceId,
                'plan_id' => $planId,
                'payment_method' => $paymentMethod,
                'amount_cents' => $amount,
            ]
        );

        $this->invalidateContext($userId);

        return ['success' => true, 'message' => 'Pagamento confirmado e plano atualizado com sucesso.'];
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

    private function priceForPlanWithPromotion(int $planId, int $baseAmountCents): array
    {
        $subtotal = max(0, $baseAmountCents);
        $result = [
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'total_cents' => $subtotal,
            'promotion' => null,
        ];

        if ($subtotal <= 0 || $planId <= 0 || !$this->db()?->connected() || !$this->tableExists('billing_promotions')) {
            return $result;
        }

        $now = date('Y-m-d H:i:s');
        $rows = $this->db()->fetchAll(
            'SELECT *
             FROM billing_promotions
             WHERE status = 1
               AND is_public = 1
               AND (plan_id IS NULL OR plan_id = :plan_id)
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY id DESC',
            [
                'plan_id' => $planId,
                'now_start' => $now,
                'now_end' => $now,
            ]
        );

        $bestPromotion = null;
        $bestDiscount = 0;
        $bestSpecificity = -1;

        foreach ($rows as $row) {
            $type = strtolower((string) ($row['discount_type'] ?? 'percent'));
            $value = max(0, (int) ($row['discount_value'] ?? 0));
            if ($value <= 0) {
                continue;
            }

            if ($type === 'amount') {
                $discount = min($subtotal, $value);
            } else {
                $percent = min(100, max(1, $value));
                $discount = (int) round(($subtotal * $percent) / 100);
            }

            $specificity = ((int) ($row['plan_id'] ?? 0) === $planId) ? 1 : 0;
            if ($discount > $bestDiscount || ($discount === $bestDiscount && $specificity > $bestSpecificity)) {
                $bestPromotion = $row;
                $bestDiscount = $discount;
                $bestSpecificity = $specificity;
            }
        }

        if ($bestPromotion === null || $bestDiscount <= 0) {
            return $result;
        }

        $result['promotion'] = $bestPromotion;
        $result['discount_cents'] = $bestDiscount;
        $result['total_cents'] = max(0, $subtotal - $bestDiscount);
        return $result;
    }

    private function manualPaymentValidationEnabled(): bool
    {
        $settings = $this->billingSettings();
        $mode = strtolower(trim((string) ($settings['validation_mode'] ?? 'automatic')));
        return $mode === 'manual';
    }

    private function settingValue(string $key, string $default = ''): string
    {
        $key = trim($key);
        if ($key === '') {
            return $default;
        }

        if (array_key_exists($key, $this->settingsCache)) {
            return (string) $this->settingsCache[$key];
        }

        if (!$this->db()?->connected() || !$this->tableExists('settings')) {
            $this->settingsCache[$key] = $default;
            return $default;
        }

        $row = $this->db()->fetch(
            'SELECT value_text
             FROM settings
             WHERE key_name = :key
             LIMIT 1',
            ['key' => $key]
        );

        $value = $row !== null ? (string) ($row['value_text'] ?? '') : $default;
        $this->settingsCache[$key] = $value;
        return $value;
    }

    private function saveSettingValue(string $key, string $value): void
    {
        $key = trim($key);
        if ($key === '' || !$this->db()?->connected() || !$this->tableExists('settings')) {
            return;
        }

        $existing = $this->db()->fetch(
            'SELECT id
             FROM settings
             WHERE key_name = :key
             LIMIT 1',
            ['key' => $key]
        );

        if ($existing) {
            $this->db()->update('settings', [
                'value_text' => $value,
                'autoload' => 1,
                'status' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $existing['id']]);
        } else {
            $this->db()->insert('settings', [
                'key_name' => $key,
                'value_text' => $value,
                'autoload' => 1,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->settingsCache[$key] = $value;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function decodeJsonPayload(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeDatetime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function ensureDefaultPlans(): void
    {
        foreach ($this->defaultPlans() as $plan) {
            $existing = $this->planBySlug((string) $plan['slug']);
            $timestamp = date('Y-m-d H:i:s');

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

        $payload = [
            'value_type' => $valueType,
            'int_value' => $intValue,
            'bool_value' => $boolValue,
            'text_value' => $textValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db()->update('plan_limits', $payload, 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db()->insert('plan_limits', array_merge($payload, [
            'plan_id' => $planId,
            'limit_key' => $limitKey,
            'created_at' => date('Y-m-d H:i:s'),
        ]));
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

        $periodStart = date('Y-m-01 00:00:00');
        $periodEnd = date('Y-m-t 23:59:59');

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
            ['limit_key' => 'max_editorial_plans_per_month', 'usage_key' => 'editorial_plans', 'label' => 'Planos editoriais/mês'],
            ['limit_key' => 'max_social_publications_per_month', 'usage_key' => 'social_publications', 'label' => 'Publicações sociais/mês'],
            ['limit_key' => 'max_social_accounts', 'usage_key' => 'social_accounts', 'label' => 'Contas sociais conectadas'],
            ['limit_key' => 'max_tracking_links_per_month', 'usage_key' => 'tracking_links', 'label' => 'Links rastreáveis/mês'],
            ['limit_key' => 'max_calendar_extra_events_per_month', 'usage_key' => 'calendar_extra_events', 'label' => 'Eventos extras/mês'],
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
        $periodStart = date('Y-m-01 00:00:00');
        $periodEnd = date('Y-m-t 23:59:59');
        $nextBillingAt = $isFree ? null : date('Y-m-01 00:00:00', strtotime('+1 month'));
        $now = date('Y-m-d H:i:s');

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

    private function createInvoice(
        int $userId,
        int $subscriptionId,
        int $planId,
        int $amountCents,
        string $paymentMethod,
        string $description,
        int $subtotalCents = -1
    ): int {
        $now = date('Y-m-d H:i:s');
        $subtotal = $subtotalCents >= 0 ? $subtotalCents : $amountCents;
        $invoiceId = $this->db()->insert('billing_invoices', [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId > 0 ? $subscriptionId : null,
            'plan_id' => $planId,
            'invoice_number' => null,
            'status' => 'open',
            'currency' => (string) $this->defaultCurrency(),
            'subtotal_cents' => max(0, $subtotal),
            'total_cents' => max(0, $amountCents),
            'payment_method' => mb_substr(strtolower(trim($paymentMethod)), 0, 40),
            'provider' => 'mock',
            'provider_invoice_id' => null,
            'description' => mb_substr(trim($description), 0, 255),
            'due_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
        $this->db()->update('billing_invoices', [
            'invoice_number' => $invoiceNumber,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $invoiceId]);

        return $invoiceId;
    }

    private function markInvoicePaid(int $invoiceId, string $paymentMethod): void
    {
        if ($invoiceId <= 0) {
            return;
        }

        $this->db()->update('billing_invoices', [
            'status' => 'paid',
            'payment_method' => mb_substr(strtolower(trim($paymentMethod)), 0, 40),
            'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $invoiceId]);
    }

    private function insertTransaction(
        int $userId,
        int $invoiceId,
        string $status,
        string $paymentMethod,
        int $amountCents,
        string $currency,
        array $payload
    ): int {
        $providerTransactionId = 'mock_tx_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

        return $this->db()->insert('payment_transactions', [
            'user_id' => $userId,
            'invoice_id' => $invoiceId,
            'status' => in_array($status, ['pending', 'paid', 'failed', 'refunded'], true) ? $status : 'pending',
            'provider' => 'mock',
            'payment_method' => mb_substr(strtolower(trim($paymentMethod)), 0, 40),
            'amount_cents' => max(0, $amountCents),
            'currency' => mb_substr(strtoupper(trim($currency)), 0, 3),
            'provider_transaction_id' => $providerTransactionId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed_at' => $status === 'paid' ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function logEvent(
        int $userId,
        ?int $subscriptionId,
        string $eventKey,
        string $message,
        array $payload = []
    ): void {
        if ($userId <= 0) {
            return;
        }

        $this->db()->insert('subscription_events', [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId !== null && $subscriptionId > 0 ? $subscriptionId : null,
            'event_key' => mb_substr(trim($eventKey), 0, 80),
            'message' => mb_substr(trim($message), 0, 255),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function mockAutoApprove(): bool
    {
        if ($this->manualPaymentValidationEnabled()) {
            return false;
        }

        $setting = trim($this->settingValue('billing.mock_auto_approve', ''));
        if ($setting !== '') {
            return $this->truthy($setting);
        }

        $value = $this->config()?->get('integrations.billing.mock_auto_approve', true);

        if (is_bool($value)) {
            return $value;
        }

        return $this->truthy($value);
    }

    private function defaultCurrency(): string
    {
        $fromSettings = strtoupper(trim($this->settingValue('billing.currency', '')));
        if (preg_match('/^[A-Z]{3}$/', $fromSettings) === 1) {
            return $fromSettings;
        }

        $configured = strtoupper(trim((string) $this->config()?->get('integrations.billing.currency', 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $configured)) {
            return 'BRL';
        }

        return $configured;
    }

    private function sanitizePaymentMethod(string $paymentMethod): string
    {
        $normalized = strtolower(trim($paymentMethod));
        if ($normalized === 'free') {
            return 'free';
        }

        $available = $this->paymentMethodsForCheckout();
        $allowed = [];
        foreach ($available as $item) {
            $key = strtolower(trim((string) ($item['key'] ?? '')));
            if ($key !== '') {
                $allowed[$key] = true;
            }
        }

        if ($normalized !== '' && isset($allowed[$normalized])) {
            return $normalized;
        }

        $fallback = array_key_first($allowed);
        return is_string($fallback) && $fallback !== '' ? $fallback : 'pix';
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

    private function defaultPlans(): array
    {
        return [
            [
                'slug' => 'gratuito',
                'name' => 'Básico Gratuito',
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
                'description' => 'Menos restrições para operação diária, com mais postagens e integrações.',
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
                'description' => 'Plano intermediário com mais recursos avançados, volume de posts e escalabilidade.',
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

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function config(): ?Config
    {
        $config = $this->registry->get('config');
        return $config instanceof Config ? $config : null;
    }
}
