<?php

namespace System\Library;

trait AutomationServiceDispatchTrait
{
    public function dispatch(string $eventKey, array $payload, array $meta = []): array
    {
        if (!$this->db()?->connected()) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
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
            'sent_at' => $this->clockIso8601Now(),
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
                $startedAtNs = function_exists('hrtime') ? hrtime(true) : null;
                $response = $this->sendHttpJson(
                    (string) $webhook['endpoint_url'],
                    (string) $webhook['http_method'],
                    $headers,
                    $jsonBody,
                    (int) ($webhook['timeout_seconds'] ?? 8)
                );
                $duration = 0;
                if (($startedAtNs !== null) && function_exists('hrtime')) {
                    $duration = max(0, (int) round((hrtime(true) - (float) $startedAtNs) / 1000000));
                }

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
                'attempted_at' => $this->clockDateTimeNow(),
            ]);

            if ($finalStatus === 'success') {
                $result['success']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
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
}
