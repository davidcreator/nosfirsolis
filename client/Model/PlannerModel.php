<?php

namespace Client\Model;

use System\Engine\Model;
use System\Library\AutomationService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;
use System\Library\PlannerService;
use System\Library\SocialPublishingService;

class PlannerModel extends Model
{
    public function createPlan(int $userId, array $data): int
    {
        return $this->db->insert('content_plans', [
            'user_id' => $userId,
            'campaign_id' => !empty($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year_ref' => (int) date('Y', strtotime($data['start_date'])),
            'month_ref' => (int) date('m', strtotime($data['start_date'])),
            'filters_json' => json_encode($data['filters'] ?? []),
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function generateItems(int $planId, string $startDate, string $endDate, array $filters = []): int
    {
        $planner = new PlannerService($this->db);

        return $planner->generatePlan($planId, $startDate, $endDate, $filters);
    }

    public function addPlanItems(int $planId, array $items): int
    {
        $inserted = 0;
        foreach ($items as $item) {
            $this->db->insert('content_plan_items', [
                'content_plan_id' => $planId,
                'planned_date' => $item['planned_date'],
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'content_suggestion_id' => $item['content_suggestion_id'] ?? null,
                'campaign_id' => $item['campaign_id'] ?? null,
                'content_objective_id' => $item['content_objective_id'] ?? null,
                'format_type' => $item['format_type'] ?? 'postagem educativa',
                'channels_json' => $item['channels_json'] ?? json_encode([]),
                'status' => $item['status'] ?? 'planned',
                'manual_note' => $item['manual_note'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        return $inserted;
    }

    public function plansByUser(int $userId): array
    {
        $sql = 'SELECT cp.*, COUNT(cpi.id) AS total_items
                FROM content_plans cp
                LEFT JOIN content_plan_items cpi ON cpi.content_plan_id = cp.id
                WHERE cp.user_id = :user_id
                GROUP BY cp.id
                ORDER BY cp.id DESC';

        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    public function planByIdForUser(int $planId, int $userId): ?array
    {
        if (!$this->db->connected()) {
            return null;
        }

        $plan = $this->db->fetch(
            'SELECT cp.*, c.name AS campaign_name
             FROM content_plans cp
             LEFT JOIN campaigns c ON c.id = cp.campaign_id
             WHERE cp.id = :plan_id AND cp.user_id = :user_id
             LIMIT 1',
            [
                'plan_id' => $planId,
                'user_id' => $userId,
            ]
        );

        if (!$plan) {
            return null;
        }

        $totals = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM content_plan_items
             WHERE content_plan_id = :plan_id',
            ['plan_id' => $planId]
        );
        $plan['total_items'] = (int) ($totals['total'] ?? 0);

        return $plan;
    }

    public function planItemsForUser(int $planId, int $userId, array $filters = []): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $allowedStatuses = ['planned', 'scheduled', 'published', 'skipped'];
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $searchTerm = trim((string) ($filters['q'] ?? ''));

        $sql = 'SELECT cpi.*
                FROM content_plan_items cpi
                INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
                WHERE cp.id = :plan_id
                  AND cp.user_id = :user_id';
        $params = [
            'plan_id' => $planId,
            'user_id' => $userId,
        ];

        if (in_array($statusFilter, $allowedStatuses, true)) {
            $sql .= ' AND cpi.status = :status';
            $params['status'] = $statusFilter;
        }

        if ($searchTerm !== '') {
            $sql .= " AND (
                cpi.title LIKE :search
                OR COALESCE(cpi.description, '') LIKE :search
                OR COALESCE(cpi.manual_note, '') LIKE :search
            )";
            $params['search'] = '%' . $searchTerm . '%';
        }

        $sql .= ' ORDER BY cpi.planned_date ASC, cpi.id ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function statusBreakdownForPlan(int $planId, int $userId): array
    {
        $breakdown = [
            'planned' => 0,
            'scheduled' => 0,
            'published' => 0,
            'skipped' => 0,
        ];

        if (!$this->db->connected()) {
            return $breakdown;
        }

        $rows = $this->db->fetchAll(
            'SELECT cpi.status, COUNT(*) AS total
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cp.id = :plan_id
               AND cp.user_id = :user_id
             GROUP BY cpi.status',
            [
                'plan_id' => $planId,
                'user_id' => $userId,
            ]
        );

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (!array_key_exists($status, $breakdown)) {
                continue;
            }
            $breakdown[$status] = (int) ($row['total'] ?? 0);
        }

        return $breakdown;
    }

    public function planInsightsForUser(int $planId, int $userId): array
    {
        $statusCounter = [
            'planned' => 0,
            'scheduled' => 0,
            'published' => 0,
            'skipped' => 0,
        ];

        $insights = [
            'total_items' => 0,
            'overdue_items' => 0,
            'next_pending_date' => null,
            'status_counter' => $statusCounter,
            'completion_rate' => 0.0,
            'publication_rate' => 0.0,
        ];

        if (!$this->db->connected()) {
            return $insights;
        }

        $rows = $this->db->fetchAll(
            'SELECT cpi.status, cpi.planned_date
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cp.id = :plan_id
               AND cp.user_id = :user_id
             ORDER BY cpi.planned_date ASC, cpi.id ASC',
            [
                'plan_id' => $planId,
                'user_id' => $userId,
            ]
        );

        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? 'planned'));
            if (!array_key_exists($status, $statusCounter)) {
                $status = 'planned';
            }

            $statusCounter[$status] = (int) $statusCounter[$status] + 1;
            $insights['total_items'] = (int) $insights['total_items'] + 1;

            $plannedDate = (string) ($row['planned_date'] ?? '');
            $isCompleted = in_array($status, ['published', 'skipped'], true);
            if ($plannedDate !== '' && $plannedDate < $today && !$isCompleted) {
                $insights['overdue_items'] = (int) $insights['overdue_items'] + 1;
            }

            if (
                $insights['next_pending_date'] === null
                && $plannedDate !== ''
                && $plannedDate >= $today
                && !$isCompleted
            ) {
                $insights['next_pending_date'] = $plannedDate;
            }
        }

        $totalItems = (int) $insights['total_items'];
        if ($totalItems > 0) {
            $completedItems = (int) $statusCounter['published'] + (int) $statusCounter['skipped'];
            $publishedItems = (int) $statusCounter['published'];
            $insights['completion_rate'] = round(($completedItems / $totalItems) * 100, 2);
            $insights['publication_rate'] = round(($publishedItems / $totalItems) * 100, 2);
        }

        $insights['status_counter'] = $statusCounter;

        return $insights;
    }

    public function updatePlanItemForUser(int $itemId, int $userId, array $data): bool
    {
        if (!$this->db->connected()) {
            return false;
        }

        $item = $this->db->fetch(
            'SELECT cpi.id, cpi.status, cpi.channels_json, cpi.title, cpi.content_plan_id
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cpi.id = :item_id
               AND cp.user_id = :user_id
             LIMIT 1',
            [
                'item_id' => $itemId,
                'user_id' => $userId,
            ]
        );

        if (!$item) {
            return false;
        }

        $allowedStatuses = ['planned', 'scheduled', 'published', 'skipped'];
        $status = strtolower(trim((string) ($data['status'] ?? 'planned')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'planned';
        }

        $manualNote = trim((string) ($data['manual_note'] ?? ''));

        $this->db->update('content_plan_items', [
            'status' => $status,
            'manual_note' => $manualNote !== '' ? $manualNote : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $itemId]);

        $previousStatus = strtolower((string) ($item['status'] ?? 'planned'));
        $this->handleStatusAutomation(
            $userId,
            (int) ($item['content_plan_id'] ?? 0),
            $itemId,
            $previousStatus,
            $status,
            (string) ($item['channels_json'] ?? '[]'),
            (string) ($item['title'] ?? '')
        );

        return true;
    }

    public function updatePlanItemsStatusForUser(int $planId, int $userId, array $itemIds, string $status): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $allowedStatuses = ['planned', 'scheduled', 'published', 'skipped'];
        if (!in_array($status, $allowedStatuses, true)) {
            return 0;
        }

        $normalizedIds = [];
        foreach ($itemIds as $itemId) {
            $id = (int) $itemId;
            if ($id > 0) {
                $normalizedIds[$id] = true;
            }
        }
        $normalizedIds = array_keys($normalizedIds);

        if (empty($normalizedIds)) {
            return 0;
        }

        $beforeRows = $this->db->fetchAll(
            'SELECT cpi.id, cpi.status, cpi.channels_json, cpi.title, cpi.content_plan_id
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cp.id = :plan_id
               AND cp.user_id = :user_id
               AND cpi.id IN (' . implode(', ', array_map(static fn (int $id): string => (string) $id, $normalizedIds)) . ')',
            [
                'plan_id' => $planId,
                'user_id' => $userId,
            ]
        );
        $beforeMap = [];
        foreach ($beforeRows as $row) {
            $beforeMap[(int) ($row['id'] ?? 0)] = $row;
        }

        $params = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'plan_id' => $planId,
            'user_id' => $userId,
        ];

        $in = [];
        foreach ($normalizedIds as $index => $id) {
            $paramKey = 'item_id_' . $index;
            $in[] = ':' . $paramKey;
            $params[$paramKey] = (int) $id;
        }

        $sql = 'UPDATE content_plan_items cpi
                INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
                SET cpi.status = :status,
                    cpi.updated_at = :updated_at
                WHERE cp.id = :plan_id
                  AND cp.user_id = :user_id
                  AND cpi.id IN (' . implode(', ', $in) . ')';

        $updated = $this->db->query($sql, $params)->rowCount();

        if ($updated > 0) {
            $processed = 0;
            foreach ($normalizedIds as $itemId) {
                if ($processed >= 200) {
                    break;
                }

                $before = $beforeMap[$itemId] ?? null;
                if (!is_array($before)) {
                    continue;
                }

                $previousStatus = strtolower((string) ($before['status'] ?? 'planned'));
                $this->handleStatusAutomation(
                    $userId,
                    (int) ($before['content_plan_id'] ?? $planId),
                    (int) $itemId,
                    $previousStatus,
                    $status,
                    (string) ($before['channels_json'] ?? '[]'),
                    (string) ($before['title'] ?? '')
                );
                $processed++;
            }
        }

        return $updated;
    }

    public function planItems(int $planId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM content_plan_items WHERE content_plan_id = :plan_id ORDER BY planned_date ASC',
            ['plan_id' => $planId]
        );
    }

    public function upsertDayNote(int $userId, string $noteDate, string $contextType, string $noteText): void
    {
        $existing = $this->db->fetch(
            'SELECT id FROM content_day_notes WHERE user_id = :user_id AND note_date = :note_date AND context_type = :context_type LIMIT 1',
            [
                'user_id' => $userId,
                'note_date' => $noteDate,
                'context_type' => $contextType,
            ]
        );

        if ($existing) {
            $this->db->update('content_day_notes', [
                'note_text' => $noteText,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db->insert('content_day_notes', [
            'user_id' => $userId,
            'note_date' => $noteDate,
            'context_type' => $contextType,
            'note_text' => $noteText,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function notesByPeriod(int $userId, string $startDate, string $endDate): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM content_day_notes
             WHERE user_id = :user_id
               AND note_date BETWEEN :start_date AND :end_date
             ORDER BY note_date ASC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['note_date']][] = $row;
        }

        return $grouped;
    }

    public function createExtraEvent(int $userId, array $data): int
    {
        $this->ensureExtraEventsTable();

        return $this->db->insert('calendar_extra_events', [
            'user_id' => $userId,
            'event_date' => $data['event_date'],
            'title' => $data['title'],
            'event_type' => $data['event_type'] ?? 'extra',
            'description' => $data['description'] ?? null,
            'color_hex' => $this->sanitizeColor((string) ($data['color_hex'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteExtraEvent(int $userId, int $eventId): void
    {
        $this->ensureExtraEventsTable();
        $this->db->delete('calendar_extra_events', 'id = :id AND user_id = :user_id', [
            'id' => $eventId,
            'user_id' => $userId,
        ]);
    }

    public function extraEventsByPeriod(int $userId, string $startDate, string $endDate): array
    {
        $this->ensureExtraEventsTable();

        $rows = $this->db->fetchAll(
            'SELECT * FROM calendar_extra_events
             WHERE user_id = :user_id
               AND event_date BETWEEN :start_date AND :end_date
             ORDER BY event_date ASC, id DESC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['event_date']][] = $row;
        }

        return $grouped;
    }

    public function extraEventsFlatByPeriod(int $userId, string $startDate, string $endDate): array
    {
        $this->ensureExtraEventsTable();

        return $this->db->fetchAll(
            'SELECT * FROM calendar_extra_events
             WHERE user_id = :user_id
               AND event_date BETWEEN :start_date AND :end_date
             ORDER BY event_date ASC, id DESC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }

    public function calendarColors(int $userId): array
    {
        $this->ensureCalendarColorsTable();
        $defaults = $this->defaultCalendarColors();

        $rows = $this->db->fetchAll(
            'SELECT color_key, color_hex FROM user_calendar_colors WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        foreach ($rows as $row) {
            $key = (string) ($row['color_key'] ?? '');
            $color = $this->sanitizeColor((string) ($row['color_hex'] ?? ''));
            if ($key !== '' && $color !== null) {
                $defaults[$key] = $color;
            }
        }

        return $defaults;
    }

    public function saveCalendarColors(int $userId, array $colors): void
    {
        $this->ensureCalendarColorsTable();

        foreach ($colors as $key => $value) {
            $color = $this->sanitizeColor((string) $value);
            if ($color === null) {
                continue;
            }

            $existing = $this->db->fetch(
                'SELECT id FROM user_calendar_colors WHERE user_id = :user_id AND color_key = :color_key LIMIT 1',
                [
                    'user_id' => $userId,
                    'color_key' => (string) $key,
                ]
            );

            if ($existing) {
                $this->db->update('user_calendar_colors', [
                    'color_hex' => $color,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => (int) $existing['id']]);
                continue;
            }

            $this->db->insert('user_calendar_colors', [
                'user_id' => $userId,
                'color_key' => (string) $key,
                'color_hex' => $color,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function handleStatusAutomation(
        int $userId,
        int $planId,
        int $itemId,
        string $previousStatus,
        string $newStatus,
        string $channelsJson,
        string $title
    ): void {
        if (!$this->db->connected()) {
            return;
        }

        if ($previousStatus === $newStatus) {
            return;
        }

        $automation = new AutomationService($this->registry);
        $observability = new ObservabilityService($this->registry);
        $jobMonitor = new JobMonitorService($this->registry);

        $payload = [
            'user_id' => $userId,
            'plan_id' => $planId,
            'item_id' => $itemId,
            'title' => $title,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_at' => date('c'),
        ];

        $traceId = $observability->startSpan('plans.status_update', $payload, $userId, 'client');
        $automation->dispatch('plan.item_status_changed', $payload, [
            'source' => 'planner_model',
            'trace_id' => $traceId,
        ]);

        $queuedPublications = 0;
        if ($newStatus === 'scheduled') {
            $channels = json_decode($channelsJson, true);
            if (!is_array($channels)) {
                $channels = [];
            }

            $normalized = [];
            foreach ($channels as $channel) {
                $slug = strtolower(trim((string) $channel));
                if ($slug !== '') {
                    $normalized[$slug] = true;
                }
            }

            if (!empty($normalized)) {
                $publisher = new SocialPublishingService($this->registry);
                $publisher->ensureTables();
                $queuedPublications = $publisher->queueFromPlanItem($userId, $itemId, array_keys($normalized), [
                    'message_text' => '',
                ]);
            }
        }

        $jobMonitor->checkin(
            'plans.status_updates',
            'ok',
            null,
            [
                'item_id' => $itemId,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'queued_publications' => $queuedPublications,
            ],
            null
        );

        $observability->log(
            'info',
            'plans_status',
            'Status do item de plano atualizado.',
            array_merge($payload, ['queued_publications' => $queuedPublications]),
            $userId,
            'client',
            $traceId
        );
        $observability->finishSpan($traceId, 'ok', ['queued_publications' => $queuedPublications]);
    }

    private function defaultCalendarColors(): array
    {
        return [
            'holiday_national' => '#f43f5e',
            'holiday_international' => '#2563eb',
            'holiday_regional' => '#eab308',
            'commemorative' => '#f59e0b',
            'suggestion' => '#0e9f6e',
            'campaign' => '#1d4ed8',
            'base_event' => '#9333ea',
            'extra_event' => '#9f3a03',
            'note' => '#6d28d9',
        ];
    }

    private function sanitizeColor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : null;
    }

    private function ensureExtraEventsTable(): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS calendar_extra_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                event_date DATE NOT NULL,
                title VARCHAR(190) NOT NULL,
                event_type VARCHAR(80) NOT NULL DEFAULT \'extra\',
                description TEXT NULL,
                color_hex VARCHAR(7) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_calendar_extra_user_date (user_id, event_date),
                CONSTRAINT fk_calendar_extra_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $colorColumn = $this->db->fetch("SHOW COLUMNS FROM calendar_extra_events LIKE 'color_hex'");
        if (!$colorColumn) {
            $this->db->execute('ALTER TABLE calendar_extra_events ADD COLUMN color_hex VARCHAR(7) NULL AFTER description');
        }
    }

    private function ensureCalendarColorsTable(): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS user_calendar_colors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                color_key VARCHAR(80) NOT NULL,
                color_hex VARCHAR(7) NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY ux_user_color (user_id, color_key),
                CONSTRAINT fk_user_calendar_colors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}
