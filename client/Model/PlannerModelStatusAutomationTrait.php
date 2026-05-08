<?php

namespace Client\Model;

use System\Library\AutomationService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;
use System\Library\SocialPublishingService;

trait PlannerModelStatusAutomationTrait
{
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

        $today = $this->modelClockFormat('Y-m-d');
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
            'updated_at' => $this->modelClockDateTimeNow(),
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
            'updated_at' => $this->modelClockDateTimeNow(),
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
            'changed_at' => $this->modelClockIso8601Now(),
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
}
