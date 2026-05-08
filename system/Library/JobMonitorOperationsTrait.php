<?php

namespace System\Library;

trait JobMonitorOperationsTrait
{
    public function upsertMonitor(array $data): int
    {
        if (!$this->db()?->connected()) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }

        $jobKey = strtolower(trim((string) ($data['job_key'] ?? '')));
        if ($jobKey === '') {
            return 0;
        }

        $name = trim((string) ($data['name'] ?? $jobKey));
        $description = trim((string) ($data['description'] ?? ''));
        $expectedInterval = max(1, min(10080, (int) ($data['expected_interval_minutes'] ?? 60)));
        $maxRuntime = max(1, min(86400, (int) ($data['max_runtime_seconds'] ?? 300)));
        $enabled = !empty($data['enabled']) ? 1 : 0;

        $row = $this->db()->fetch(
            'SELECT id
             FROM job_monitors
             WHERE job_key = :job_key
             LIMIT 1',
            ['job_key' => $jobKey]
        );

        $payload = [
            'name' => mb_substr($name, 0, 180),
            'description' => $description !== '' ? $description : null,
            'expected_interval_minutes' => $expectedInterval,
            'max_runtime_seconds' => $maxRuntime,
            'enabled' => $enabled,
            'updated_at' => $this->clockDateTimeNow(),
        ];

        if ($row) {
            $this->db()->update('job_monitors', $payload, 'id = :id', ['id' => (int) $row['id']]);
            return (int) $row['id'];
        }

        return $this->db()->insert('job_monitors', array_merge($payload, [
            'job_key' => $jobKey,
            'last_status' => 'stale',
            'created_at' => $this->clockDateTimeNow(),
        ]));
    }

    public function deleteMonitor(int $id): void
    {
        if (!$this->db()?->connected() || $id <= 0) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db()->delete('job_monitors', 'id = :id', ['id' => $id]);
    }

    public function checkin(
        string $jobKey,
        string $status = 'ok',
        ?int $durationMs = null,
        array $payload = [],
        ?string $errorMessage = null
    ): void {
        if (!$this->db()?->connected()) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $jobKey = strtolower(trim($jobKey));
        if ($jobKey === '') {
            return;
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['ok', 'warning', 'error'], true)) {
            $status = 'ok';
        }

        $monitor = $this->db()->fetch(
            'SELECT *
             FROM job_monitors
             WHERE job_key = :job_key
             LIMIT 1',
            ['job_key' => $jobKey]
        );
        if (!$monitor) {
            $this->upsertMonitor([
                'job_key' => $jobKey,
                'name' => $jobKey,
                'description' => 'Monitor criado automaticamente.',
                'enabled' => 1,
                'expected_interval_minutes' => 60,
                'max_runtime_seconds' => 300,
            ]);
            $monitor = $this->db()->fetch(
                'SELECT *
                 FROM job_monitors
                 WHERE job_key = :job_key
                 LIMIT 1',
                ['job_key' => $jobKey]
            );
        }
        if (!$monitor) {
            return;
        }

        $monitorId = (int) $monitor['id'];
        $durationMs = $durationMs !== null ? max(0, $durationMs) : null;
        $shortError = $errorMessage !== null ? mb_substr(trim($errorMessage), 0, 255) : null;

        $this->db()->insert('job_checkins', [
            'monitor_id' => $monitorId,
            'status' => $status,
            'duration_ms' => $durationMs,
            'error_message' => $shortError,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'checked_at' => $this->clockDateTimeNow(),
        ]);

        $this->db()->update('job_monitors', [
            'last_checkin_at' => $this->clockDateTimeNow(),
            'last_status' => $status,
            'last_duration_ms' => $durationMs,
            'last_error' => $shortError,
            'updated_at' => $this->clockDateTimeNow(),
        ], 'id = :id', ['id' => $monitorId]);

        $this->syncFailureAlert($monitorId, $status, $shortError);
        $this->syncSlowAlert($monitor, $durationMs);
    }

    public function evaluateStaleMonitors(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }

        $alerts = [];
        $rows = $this->db()->fetchAll(
            'SELECT *
             FROM job_monitors
             WHERE enabled = 1
             ORDER BY id ASC'
        );

        foreach ($rows as $monitor) {
            $monitorId = (int) ($monitor['id'] ?? 0);
            if ($monitorId <= 0) {
                continue;
            }

            $expectedMinutes = max(1, (int) ($monitor['expected_interval_minutes'] ?? 60));
            $lastCheckinAt = (string) ($monitor['last_checkin_at'] ?? '');
            $stale = true;
            if ($lastCheckinAt !== '') {
                $lastTs = $this->parseDateTimeToUnix($lastCheckinAt);
                if ($lastTs !== null && ($this->clockUnixNow() - $lastTs) <= ($expectedMinutes * 60)) {
                    $stale = false;
                }
            }

            if ($stale) {
                $message = 'Monitor sem check-in no intervalo esperado.';
                $opened = $this->openAlert($monitorId, 'stale', $message);
                if ($opened) {
                    $alerts[] = [
                        'monitor_id' => $monitorId,
                        'alert_type' => 'stale',
                        'message' => $message,
                        'job_key' => (string) ($monitor['job_key'] ?? ''),
                    ];
                }

                $this->db()->update('job_monitors', [
                    'last_status' => 'stale',
                    'updated_at' => $this->clockDateTimeNow(),
                ], 'id = :id', ['id' => $monitorId]);
                continue;
            }

            $this->resolveAlert($monitorId, 'stale');
        }

        return $alerts;
    }

    public function activeAlerts(int $limit = 50): array
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
            'SELECT a.*, m.job_key, m.name AS monitor_name
             FROM job_alerts a
             INNER JOIN job_monitors m ON m.id = a.monitor_id
             WHERE a.status = \'open\'
             ORDER BY a.id DESC
             LIMIT ' . $limit
        );
    }

    public function listMonitors(): array
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
             FROM job_monitors
             ORDER BY id DESC'
        );
    }

    public function recentCheckins(int $limit = 50): array
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
            'SELECT c.*, m.job_key, m.name AS monitor_name
             FROM job_checkins c
             INNER JOIN job_monitors m ON m.id = c.monitor_id
             ORDER BY c.id DESC
             LIMIT ' . $limit
        );
    }

    private function syncFailureAlert(int $monitorId, string $status, ?string $errorMessage): void
    {
        if ($status === 'error') {
            $message = $errorMessage !== null && $errorMessage !== ''
                ? 'Falha de job: ' . $errorMessage
                : 'Falha de job detectada.';
            $this->openAlert($monitorId, 'failure', $message);
            return;
        }

        $this->resolveAlert($monitorId, 'failure');
    }

    private function syncSlowAlert(array $monitor, ?int $durationMs): void
    {
        $monitorId = (int) ($monitor['id'] ?? 0);
        if ($monitorId <= 0) {
            return;
        }

        if ($durationMs === null) {
            $this->resolveAlert($monitorId, 'slow');
            return;
        }

        $maxRuntimeSeconds = max(1, (int) ($monitor['max_runtime_seconds'] ?? 300));
        $maxMs = $maxRuntimeSeconds * 1000;

        if ($durationMs > $maxMs) {
            $message = 'Job excedeu o tempo maximo esperado (' . $maxRuntimeSeconds . 's).';
            $this->openAlert($monitorId, 'slow', $message);
            return;
        }

        $this->resolveAlert($monitorId, 'slow');
    }

    private function openAlert(int $monitorId, string $alertType, string $message): bool
    {
        $existing = $this->db()->fetch(
            'SELECT id
             FROM job_alerts
             WHERE monitor_id = :monitor_id
               AND alert_type = :alert_type
               AND status = \'open\'
             LIMIT 1',
            [
                'monitor_id' => $monitorId,
                'alert_type' => $alertType,
            ]
        );

        if ($existing) {
            $this->db()->update('job_alerts', [
                'message' => mb_substr($message, 0, 255),
            ], 'id = :id', ['id' => (int) $existing['id']]);
            return false;
        }

        $this->db()->insert('job_alerts', [
            'monitor_id' => $monitorId,
            'alert_type' => $alertType,
            'status' => 'open',
            'message' => mb_substr($message, 0, 255),
            'created_at' => $this->clockDateTimeNow(),
            'resolved_at' => null,
        ]);

        $monitor = $this->db()->fetch(
            'SELECT id, job_key, name
             FROM job_monitors
             WHERE id = :id
             LIMIT 1',
            ['id' => $monitorId]
        );
        if ($monitor) {
            $automation = new AutomationService($this->registry);
            $automation->dispatch('jobs.alert.opened', [
                'monitor_id' => (int) $monitor['id'],
                'job_key' => (string) ($monitor['job_key'] ?? ''),
                'monitor_name' => (string) ($monitor['name'] ?? ''),
                'alert_type' => $alertType,
                'message' => $message,
            ], [
                'source' => 'job_monitor_service',
            ]);
        }

        return true;
    }

    private function resolveAlert(int $monitorId, string $alertType): void
    {
        $this->db()->query(
            'UPDATE job_alerts
             SET status = \'resolved\',
                 resolved_at = :resolved_at
             WHERE monitor_id = :monitor_id
               AND alert_type = :alert_type
               AND status = \'open\'',
            [
                'resolved_at' => $this->clockDateTimeNow(),
                'monitor_id' => $monitorId,
                'alert_type' => $alertType,
            ]
        );
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
