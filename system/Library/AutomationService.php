<?php

namespace System\Library;

use System\Engine\Registry;

class AutomationService
{
    private bool $ensured = false;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS automations_webhooks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                event_key VARCHAR(120) NOT NULL,
                endpoint_url VARCHAR(500) NOT NULL,
                http_method ENUM(\'POST\', \'PUT\', \'PATCH\') NOT NULL DEFAULT \'POST\',
                auth_type ENUM(\'none\', \'bearer\', \'basic\', \'header\') NOT NULL DEFAULT \'none\',
                auth_username VARCHAR(190) NULL,
                auth_secret VARCHAR(255) NULL,
                header_name VARCHAR(120) NULL,
                header_value VARCHAR(255) NULL,
                signing_secret VARCHAR(255) NULL,
                timeout_seconds TINYINT UNSIGNED NOT NULL DEFAULT 8,
                retries TINYINT UNSIGNED NOT NULL DEFAULT 1,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_automation_webhooks_event (enabled, event_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS automation_dispatch_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                webhook_id INT UNSIGNED NOT NULL,
                event_key VARCHAR(120) NOT NULL,
                status ENUM(\'success\', \'failed\') NOT NULL DEFAULT \'failed\',
                http_status SMALLINT NULL,
                duration_ms INT UNSIGNED NULL,
                response_body TEXT NULL,
                error_message VARCHAR(255) NULL,
                payload_json LONGTEXT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_automation_dispatch_event (event_key, attempted_at),
                INDEX idx_automation_dispatch_webhook (webhook_id, attempted_at),
                CONSTRAINT fk_automation_dispatch_webhook FOREIGN KEY (webhook_id) REFERENCES automations_webhooks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    public function listWebhooks(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
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
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->db()->update('automations_webhooks', $payload, 'id = :id', ['id' => $id]);
            return $id;
        }

        return $this->db()->insert('automations_webhooks', array_merge($payload, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function deleteWebhook(int $id): void
    {
        if (!$this->db()?->connected() || $id <= 0) {
            return;
        }

        $this->ensureTables();
        $this->db()->delete('automations_webhooks', 'id = :id', ['id' => $id]);
    }

    public function dispatch(string $eventKey, array $payload, array $meta = []): array
    {
        if (!$this->db()?->connected()) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $this->ensureTables();
        $eventKey = trim($eventKey);
        if ($eventKey === '') {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $webhooks = $this->matchingWebhooks($eventKey);
        $onlyWebhookId = (int) ($meta['only_webhook_id'] ?? 0);
        if ($onlyWebhookId > 0) {
            $webhooks = array_values(array_filter(
                $webhooks,
                static fn (array $row): bool => (int) ($row['id'] ?? 0) === $onlyWebhookId
            ));
        }
        if (empty($webhooks)) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $deliveryId = bin2hex(random_bytes(16));
        $envelope = [
            'event' => $eventKey,
            'area' => strtolower((string) (defined('AREA') ? AREA : 'client')),
            'delivery_id' => $deliveryId,
            'meta' => $meta,
            'payload' => $payload,
            'sent_at' => date('c'),
        ];
        $jsonBody = json_encode($envelope, JSON_UNESCAPED_UNICODE);
        if (!is_string($jsonBody)) {
            $jsonBody = '{}';
        }

        $result = [
            'total' => count($webhooks),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($webhooks as $webhook) {
            $attempts = max(1, 1 + (int) ($webhook['retries'] ?? 1));
            $finalStatus = 'failed';
            $finalHttp = null;
            $finalDuration = null;
            $finalResponseBody = null;
            $finalError = null;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $headers = [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Nosfir-Event: ' . $eventKey,
                    'X-Nosfir-Delivery: ' . $deliveryId,
                ];

                $signingSecret = trim((string) ($webhook['signing_secret'] ?? ''));
                if ($signingSecret !== '') {
                    $signature = hash_hmac('sha256', $jsonBody, $signingSecret);
                    $headers[] = 'X-Nosfir-Signature: sha256=' . $signature;
                }

                $this->applyAuthenticationHeaders($headers, $webhook);
                $startedAt = microtime(true);
                $response = $this->sendHttpJson(
                    (string) $webhook['endpoint_url'],
                    (string) $webhook['http_method'],
                    $headers,
                    $jsonBody,
                    (int) ($webhook['timeout_seconds'] ?? 8)
                );
                $duration = max(0, (int) round((microtime(true) - $startedAt) * 1000));

                $finalHttp = $response['http_status'] ?? null;
                $finalDuration = $duration;
                $finalResponseBody = isset($response['body']) ? mb_substr((string) $response['body'], 0, 65000) : null;
                $finalError = $response['error'] ?? null;

                if (!empty($response['ok'])) {
                    $finalStatus = 'success';
                    break;
                }
            }

            $this->db()->insert('automation_dispatch_logs', [
                'webhook_id' => (int) $webhook['id'],
                'event_key' => $eventKey,
                'status' => $finalStatus,
                'http_status' => $finalHttp,
                'duration_ms' => $finalDuration,
                'response_body' => $finalResponseBody,
                'error_message' => $finalError,
                'payload_json' => $jsonBody,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);

            if ($finalStatus === 'success') {
                $result['success']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    public function testWebhook(int $webhookId): array
    {
        if (!$this->db()?->connected() || $webhookId <= 0) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $this->ensureTables();
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

    private function matchingWebhooks(string $eventKey): array
    {
        $all = $this->db()->fetchAll(
            'SELECT *
             FROM automations_webhooks
             WHERE enabled = 1
             ORDER BY id ASC'
        );

        $matched = [];
        foreach ($all as $row) {
            $pattern = trim((string) ($row['event_key'] ?? ''));
            if ($pattern === '') {
                continue;
            }

            if ($this->eventMatches($eventKey, $pattern)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    private function eventMatches(string $eventKey, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $eventKey) {
            return true;
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim(substr($pattern, 0, -1));
            if ($prefix === '') {
                return true;
            }

            return str_starts_with($eventKey, $prefix);
        }

        return false;
    }

    private function applyAuthenticationHeaders(array &$headers, array $webhook): void
    {
        $authType = (string) ($webhook['auth_type'] ?? 'none');
        $username = trim((string) ($webhook['auth_username'] ?? ''));
        $secret = trim((string) ($webhook['auth_secret'] ?? ''));
        $headerName = trim((string) ($webhook['header_name'] ?? ''));
        $headerValue = trim((string) ($webhook['header_value'] ?? ''));

        if ($authType === 'bearer' && $secret !== '') {
            $headers[] = 'Authorization: Bearer ' . $secret;
            return;
        }

        if ($authType === 'basic' && $username !== '' && $secret !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $secret);
            return;
        }

        if ($authType === 'header' && $headerName !== '' && $headerValue !== '') {
            $headers[] = $headerName . ': ' . $headerValue;
        }
    }

    private function sendHttpJson(string $url, string $method, array $headers, string $body, int $timeout): array
    {
        if (!$this->isValidWebhookEndpoint($url)) {
            return [
                'ok' => false,
                'http_status' => null,
                'error' => 'endpoint_blocked',
                'body' => '',
            ];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, max(2, min(30, $timeout)));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $rawBody = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($rawBody === false) {
                return [
                    'ok' => false,
                    'http_status' => null,
                    'error' => $error !== '' ? $error : 'curl_error',
                    'body' => '',
                ];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'http_status' => $status,
                'error' => $status >= 200 && $status < 300 ? null : 'http_' . $status,
                'body' => (string) $rawBody,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => max(2, min(30, $timeout)),
                'ignore_errors' => true,
            ],
        ]);

        $rawBody = @file_get_contents($url, false, $context);
        if ($rawBody === false) {
            return [
                'ok' => false,
                'http_status' => null,
                'error' => 'http_request_failed',
                'body' => '',
            ];
        }

        $status = null;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/[0-9.]+\s+(\d{3})#', $headerLine, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        return [
            'ok' => $status !== null && $status >= 200 && $status < 300,
            'http_status' => $status,
            'error' => ($status !== null && $status >= 200 && $status < 300) ? null : 'http_' . ($status ?? 0),
            'body' => (string) $rawBody,
        ];
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function isValidWebhookEndpoint(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '') {
            return false;
        }

        if ($this->allowPrivateWebhookEndpoints()) {
            return true;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $resolved = @gethostbynamel($host);
        if (!is_array($resolved) || empty($resolved)) {
            return false;
        }

        foreach ($resolved as $ip) {
            if (is_string($ip) && $this->isPublicIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private function allowPrivateWebhookEndpoints(): bool
    {
        $config = $this->registry->get('config');
        if (!is_object($config)) {
            return false;
        }

        return (bool) $config->get('security.automation.allow_private_webhook_endpoints', false);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
