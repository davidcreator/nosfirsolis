<?php

namespace System\Library;

class PlannerService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function generatePlan(int $planId, string $startDate, string $endDate, array $filters = []): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $planYear = (int) date('Y', strtotime($startDate));

        $sql = 'SELECT cs.*
                FROM content_suggestions cs
                WHERE cs.status = 1
                  AND (cs.suggestion_date BETWEEN :start_date_main AND :end_date_main
                       OR (cs.is_recurring = 1 AND DATE_FORMAT(cs.suggestion_date, "%m-%d") BETWEEN DATE_FORMAT(:start_date_rec, "%m-%d") AND DATE_FORMAT(:end_date_rec, "%m-%d")))';

        $params = [
            'start_date_main' => $startDate,
            'end_date_main' => $endDate,
            'start_date_rec' => $startDate,
            'end_date_rec' => $endDate,
        ];

        if (!empty($filters['campaign_id'])) {
            $sql .= ' AND cs.campaign_id = :campaign_id';
            $params['campaign_id'] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['content_objective_id'])) {
            $sql .= ' AND cs.content_objective_id = :content_objective_id';
            $params['content_objective_id'] = (int) $filters['content_objective_id'];
        }

        if (!empty($filters['channel_id'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM content_suggestion_channels csc
                WHERE csc.content_suggestion_id = cs.id
                  AND csc.content_platform_id = :channel_id
            )';
            $params['channel_id'] = (int) $filters['channel_id'];
        }

        $suggestions = $this->db->fetchAll($sql, $params);

        $inserted = 0;
        foreach ($suggestions as $suggestion) {
            $plannedDate = $suggestion['suggestion_date'];
            if ((int) $suggestion['is_recurring'] === 1) {
                $plannedDate = $planYear . '-' . date('m-d', strtotime($suggestion['suggestion_date']));
                if ($plannedDate < $startDate || $plannedDate > $endDate) {
                    continue;
                }
            }

            $this->db->insert('content_plan_items', [
                'content_plan_id' => $planId,
                'planned_date' => $plannedDate,
                'title' => $suggestion['title'],
                'description' => $suggestion['description'],
                'content_suggestion_id' => $suggestion['id'],
                'campaign_id' => $suggestion['campaign_id'],
                'content_objective_id' => $suggestion['content_objective_id'],
                'format_type' => $suggestion['format_type'],
                'channels_json' => json_encode([]),
                'status' => 'planned',
                'manual_note' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        return $inserted;
    }
}
