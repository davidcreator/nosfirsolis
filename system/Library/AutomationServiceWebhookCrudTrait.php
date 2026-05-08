<?php

namespace System\Library;

trait AutomationServiceWebhookCrudTrait
{
    public function listWebhooks(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        return $this->db()->fetchAll(
            'SELECT *
             FROM automations_webhooks
             ORDER BY id DESC'
        );
    }

    public function recentDispatches(int $limit = 50): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT l.*, w.name AS webhook_name, w.endpoint_url
             FROM automation_dispatch_logs l
             INNER JOIN automations_webhooks w ON w.id = l.webhook_id
             ORDER BY l.id DESC
             LIMIT ' . $limit
        );
    }

    public function saveWebhook(array $data): int
    {
        if (!$this->db()?->connected()) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }

        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $eventKey = trim((string) ($data['event_key'] ?? ''));
        $endpoint = trim((string) ($data['endpoint_url'] ?? ''));
        if ($name === '' || $eventKey === '' || $endpoint === '') {
            return 0;
        }

        if (!$this->isValidWebhookEndpoint($endpoint)) {
            return 0;
        }

        $method = strtoupper(trim((string) ($data['http_method'] ?? 'POST')));
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $method = 'POST';
        }

        $authType = strtolower(trim((string) ($data['auth_type'] ?? 'none')));
        if (!in_array($authType, ['none', 'bearer', 'basic', 'header'], true)) {
            $authType = 'none';
        }

        $payload = [
            'name' => mb_substr($name, 0, 160),
            'event_key' => mb_substr($eventKey, 0, 120),
            'endpoint_url' => mb_substr($endpoint, 0, 500),
            'http_method' => $method,
            'auth_type' => $authType,
            'auth_username' => trim((string) ($data['auth_username'] ?? '')) ?: null,
            'auth_secret' => trim((string) ($data['auth_secret'] ?? '')) ?: null,
            'header_name' => trim((string) ($data['header_name'] ?? '')) ?: null,
            'header_value' => trim((string) ($data['header_value'] ?? '')) ?: null,
            'signing_secret' => trim((string) ($data['signing_secret'] ?? '')) ?: null,
            'timeout_seconds' => max(2, min(30, (int) ($data['timeout_seconds'] ?? 8))),
            'retries' => max(0, min(5, (int) ($data['retries'] ?? 1))),
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'updated_at' => $this->clockDateTimeNow(),
        ];

        if ($id > 0) {
            $this->db()->update('automations_webhooks', $payload, 'id = :id', ['id' => $id]);
            return $id;
        }

        return $this->db()->insert('automations_webhooks', array_merge($payload, [
            'created_at' => $this->clockDateTimeNow(),
        ]));
    }

    public function deleteWebhook(int $id): void
    {
        if (!$this->db()?->connected() || $id <= 0) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db()->delete('automations_webhooks', 'id = :id', ['id' => $id]);
    }

    public function testWebhook(int $webhookId): array
    {
        if (!$this->db()?->connected() || $webhookId <= 0) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
        $hook = $this->db()->fetch(
            'SELECT *
             FROM automations_webhooks
             WHERE id = :id
             LIMIT 1',
            ['id' => $webhookId]
        );
        if (!$hook) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        return $this->dispatch('system.webhook_test', [
            'message' => 'Disparo de teste do painel administrativo.',
            'webhook_id' => $webhookId,
        ], [
            'source' => 'admin.operations',
            'only_webhook_id' => $webhookId,
        ]);
    }
}
