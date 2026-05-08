<?php

namespace Client\Model;

use System\Library\PlannerService;

trait PlannerModelPlanLifecycleTrait
{
    public function createPlan(int $userId, array $data): int
    {
        [$yearRef, $monthRef] = $this->derivePlanReferenceFromDate((string) ($data['start_date'] ?? ''));

        return $this->db->insert('content_plans', [
            'user_id' => $userId,
            'campaign_id' => !empty($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year_ref' => $yearRef,
            'month_ref' => $monthRef,
            'filters_json' => json_encode($data['filters'] ?? []),
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_at' => $this->modelClockDateTimeNow(),
            'updated_at' => $this->modelClockDateTimeNow(),
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
                'created_at' => $this->modelClockDateTimeNow(),
                'updated_at' => $this->modelClockDateTimeNow(),
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

    public function planItems(int $planId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM content_plan_items WHERE content_plan_id = :plan_id ORDER BY planned_date ASC',
            ['plan_id' => $planId]
        );
    }

    /**
     * @return array{0:int,1:int}
     */
    private function derivePlanReferenceFromDate(string $date): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', trim($date), $matches) === 1) {
            $year = (int) ($matches[1] ?? 0);
            $month = (int) ($matches[2] ?? 0);
            if ($year >= 1970 && $year <= 2100 && $month >= 1 && $month <= 12) {
                return [$year, $month];
            }
        }

        return [(int) $this->modelClockFormat('Y'), (int) $this->modelClockFormat('m')];
    }
}
