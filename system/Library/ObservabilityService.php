<?php

namespace System\Library;

use System\Engine\Registry;

class ObservabilityService
{
    use TemporalClockTrait;

    private bool $ensured = false;
    private bool $schemaAvailable = true;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $requiredTables = ['observability_events', 'observability_spans'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema de observabilidade ausente. Execute a migracao operacional. '
                . 'missing=' . implode(',', $missing)
            );
            $this->ensured = true;
            return;
        }

        $this->ensured = true;
    }

    public function startSpan(string $spanKey, array $context = [], ?int $userId = null, ?string $area = null): string
    {
        if (!$this->db()?->connected()) {
            return '';
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return '';
        }
        $traceId = bin2hex(random_bytes(16));

        $this->db()->insert('observability_spans', [
            'trace_id' => $traceId,
            'span_key' => substr(trim($spanKey), 0, 120),
            'area' => $this->normalizeArea($area),
            'user_id' => $userId,
            'status' => 'running',
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'started_at' => $this->clockDateTimeNow(),
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
        if (!$this->schemaAvailable) {
            return;
        }
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

        $started = $this->parseDateTimeToUnix((string) ($row['started_at'] ?? ''));
        $duration = null;
        if ($started !== null) {
            $duration = max(0, ($this->clockUnixNow() - $started) * 1000);
        }

        $this->db()->update('observability_spans', [
            'status' => $status,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'ended_at' => $this->clockDateTimeNow(),
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
        if (!$this->schemaAvailable) {
            return;
        }
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
            'created_at' => $this->clockDateTimeNow(),
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
        if (!$this->schemaAvailable) {
            return [];
        }
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

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        $row = $this->db()?->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }

    private function config(): ?\System\Engine\Config
    {
        $config = $this->registry->get('config');
        return $config instanceof \System\Engine\Config ? $config : null;
    }

    private function parseDateTimeToUnix(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/', $value, $matches) !== 1) {
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

        return mktime($hour, $minute, $second, $month, $day, $year);
    }

}
