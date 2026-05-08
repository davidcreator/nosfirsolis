<?php

namespace System\Library;

class PlannerService
{
    use TemporalClockTrait;

    public function __construct(private readonly Database $db)
    {
    }

    public function generatePlan(int $planId, string $startDate, string $endDate, array $filters = []): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $planYear = $this->extractYear($startDate);

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
                $monthDay = $this->extractMonthDay((string) ($suggestion['suggestion_date'] ?? ''));
                if ($monthDay === null) {
                    continue;
                }

                $plannedDate = sprintf('%04d-%s', $planYear, $monthDay);
                if ($plannedDate < $startDate || $plannedDate > $endDate) {
                    continue;
                }
            }

            $timestamp = $this->clockDateTimeNow();
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
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    private function extractYear(string $date): int
    {
        if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', trim($date), $matches) === 1) {
            $year = (int) ($matches[1] ?? 0);
            if ($year >= 1970 && $year <= 2100) {
                return $year;
            }
        }

        return (int) $this->clockFormat('Y');
    }

    private function extractMonthDay(string $date): ?string
    {
        if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', trim($date), $matches) !== 1) {
            return null;
        }

        $month = (int) ($matches[1] ?? 0);
        $day = (int) ($matches[2] ?? 0);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%02d-%02d', $month, $day);
    }
}
