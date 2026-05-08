<?php

namespace System\Library;

trait SubscriptionServiceBillingInternalsTrait
{
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

        $now = $this->clockDateTimeNow();
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
                'updated_at' => $this->clockDateTimeNow(),
            ], 'id = :id', ['id' => (int) $existing['id']]);
        } else {
            $this->db()->insert('settings', [
                'key_name' => $key,
                'value_text' => $value,
                'autoload' => 1,
                'status' => 1,
                'created_at' => $this->clockDateTimeNow(),
                'updated_at' => $this->clockDateTimeNow(),
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

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);
        $hour = (int) ($matches[4] ?? 0);
        $minute = (int) ($matches[5] ?? 0);
        $second = (int) ($matches[6] ?? 0);

        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
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
        $now = $this->clockDateTimeNow();
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
            'due_at' => $this->clockDateTimeAfterSeconds(2 * 86400),
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $invoiceNumber = 'INV-' . $this->clockFormat('Ymd') . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
        $this->db()->update('billing_invoices', [
            'invoice_number' => $invoiceNumber,
            'updated_at' => $this->clockDateTimeNow(),
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
            'paid_at' => $this->clockDateTimeNow(),
            'updated_at' => $this->clockDateTimeNow(),
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
        $providerTransactionId = 'mock_tx_' . $this->clockFormat('YmdHis') . '_' . bin2hex(random_bytes(4));

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
            'processed_at' => $status === 'paid' ? $this->clockDateTimeNow() : null,
            'created_at' => $this->clockDateTimeNow(),
            'updated_at' => $this->clockDateTimeNow(),
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
            'created_at' => $this->clockDateTimeNow(),
        ]);
    }

    private function mockAutoApprove(): bool
    {
        if ($this->manualPaymentValidationEnabled()) {
            return false;
        }

        $isProduction = $this->isProductionEnvironment();
        $setting = trim($this->settingValue('billing.mock_auto_approve', ''));
        if ($setting !== '') {
            $enabledBySetting = $this->truthy($setting);
            if ($isProduction && $enabledBySetting) {
                return false;
            }

            return $enabledBySetting;
        }

        $value = $this->config()?->get('integrations.billing.mock_auto_approve', false);

        $enabledByConfig = is_bool($value)
            ? $value
            : $this->truthy($value);

        if ($isProduction && $enabledByConfig) {
            return false;
        }

        return $enabledByConfig;
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
}
