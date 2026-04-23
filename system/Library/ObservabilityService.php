<?php

namespace System\Library;

use System\Engine\Registry;

class ObservabilityService
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
            'CREATE TABLE IF NOT EXISTS observability_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level ENUM(\'debug\', \'info\', \'warning\', \'error\', \'critical\') NOT NULL DEFAULT \'info\',
                category VARCHAR(80) NOT NULL,
                message VARCHAR(255) NOT NULL,
                area VARCHAR(20) NOT NULL,
                user_id INT UNSIGNED NULL,
                trace_id VARCHAR(64) NULL,
                context_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_observability_level (level, created_at),
                INDEX idx_observability_category (category, created_at),
                INDEX idx_observability_area (area, created_at),
                INDEX idx_observability_user (user_id, created_at),
                CONSTRAINT fk_observability_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS observability_spans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trace_id VARCHAR(64) NOT NULL,
                span_key VARCHAR(120) NOT NULL,
                area VARCHAR(20) NOT NULL,
                user_id INT UNSIGNED NULL,
                status ENUM(\'running\', \'ok\', \'warning\', \'error\') NOT NULL DEFAULT \'running\',
                context_json LONGTEXT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                duration_ms INT UNSIGNED NULL,
                INDEX idx_observability_spans_trace (trace_id),
                INDEX idx_observability_spans_key (span_key, started_at),
                CONSTRAINT fk_observability_spans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    public function startSpan(string $spanKey, array $context = [], ?int $userId = null, ?string $area = null): string
    {
        if (!$this->db()?->connected()) {
            return '';
        }

        $this->ensureTables();
        $traceId = bin2hex(random_bytes(16));

        $this->db()->insert('observability_spans', [
            'trace_id' => $traceId,
            'span_key' => substr(trim($spanKey), 0, 120),
            'area' => $this->normalizeArea($area),
            'user_id' => $userId,
            'status' => 'running',
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'started_at' => date('Y-m-d H:i:s'),
            'ended_at' => null,
            'duration_ms' => null,
        ]);

        return $traceId;
    }

    public function finishSpan(string $traceId, string $status = 'ok', array $context = []): void
    {
        if (!$this->db()?->connected() || trim($traceId) === '') {
            return;
        }

        $this->ensureTables();
        $status = in_array($status, ['ok', 'warning', 'error'], true) ? $status : 'ok';

        $row = $this->db()->fetch(
            'SELECT id, started_at
             FROM observability_spans
             WHERE trace_id = :trace_id
             ORDER BY id DESC
             LIMIT 1',
            ['trace_id' => $traceId]
        );
        if (!$row) {
            return;
        }

        $started = strtotime((string) ($row['started_at'] ?? ''));
        $duration = null;
        if ($started !== false) {
            $duration = max(0, (int) ((microtime(true) - (float) $started) * 1000));
        }

        $this->db()->update('observability_spans', [
            'status' => $status,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'ended_at' => date('Y-m-d H:i:s'),
            'duration_ms' => $duration,
        ], 'id = :id', ['id' => (int) $row['id']]);
    }

    public function log(
        string $level,
        string $category,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?string $area = null,
        ?string $traceId = null
    ): void {
        if (!$this->db()?->connected()) {
            return;
        }

        $this->ensureTables();
        $allowedLevels = ['debug', 'info', 'warning', 'error', 'critical'];
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'info';
        }

        $normalizedCategory = substr(trim($category), 0, 80);
        if ($normalizedCategory === '') {
            $normalizedCategory = 'general';
        }

        $normalizedMessage = trim($message);
        if ($normalizedMessage === '') {
            $normalizedMessage = 'Evento sem mensagem';
        }

        $this->db()->insert('observability_events', [
            'level' => $level,
            'category' => $normalizedCategory,
            'message' => substr($normalizedMessage, 0, 255),
            'area' => $this->normalizeArea($area),
            'user_id' => $userId,
            'trace_id' => $traceId !== null ? substr(trim($traceId), 0, 64) : null,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function captureThrowable(
        \Throwable $throwable,
        string $category = 'exceptions',
        array $context = [],
        ?int $userId = null,
        ?string $area = null,
        ?string $traceId = null
    ): void {
        $payload = array_merge($context, [
            'exception_class' => get_class($throwable),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);

        $this->log(
            'error',
            $category,
            $throwable->getMessage(),
            $payload,
            $userId,
            $area,
            $traceId
        );

        $sentryEnabled = (bool) $this->config()?->get('integrations.observability.sentry_enabled', false);
        if ($sentryEnabled && function_exists('\Sentry\captureException')) {
            \Sentry\captureException($throwable);
        }
    }

    public function recent(int $limit = 50, array $filters = []): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(500, $limit));

        $where = [];
        $params = [];

        $level = strtolower(trim((string) ($filters['level'] ?? '')));
        if ($level !== '' && in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true)) {
            $where[] = 'level = :level';
            $params['level'] = $level;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'category = :category';
            $params['category'] = $category;
        }

        $area = strtolower(trim((string) ($filters['area'] ?? '')));
        if ($area !== '' && in_array($area, ['admin', 'client', 'install'], true)) {
            $where[] = 'area = :area';
            $params['area'] = $area;
        }

        $sql = 'SELECT * FROM observability_events';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        return $this->db()->fetchAll($sql, $params);
    }

    private function normalizeArea(?string $area): string
    {
        $runtime = strtolower(trim((string) ($area ?? (defined('AREA') ? AREA : 'client'))));
        if (!in_array($runtime, ['admin', 'client', 'install'], true)) {
            return 'client';
        }

        return $runtime;
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function config(): ?\System\Engine\Config
    {
        $config = $this->registry->get('config');
        return $config instanceof \System\Engine\Config ? $config : null;
    }
}
