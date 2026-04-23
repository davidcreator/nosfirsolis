<?php

namespace Admin\Model;

class ContentSuggestionsModel extends AbstractCrudModel
{
    protected string $table = 'content_suggestions';
    protected array $fillable = [
        'title',
        'description',
        'suggestion_date',
        'month_day',
        'is_recurring',
        'recurrence_type',
        'content_category_id',
        'content_pillar_id',
        'content_objective_id',
        'campaign_id',
        'format_type',
        'context_type',
        'channel_priority',
        'status',
    ];

    public function allDetailed(): array
    {
        $sql = 'SELECT cs.*, cc.name AS category_name, cp.name AS pillar_name, co.name AS objective_name, c.name AS campaign_name
                FROM content_suggestions cs
                LEFT JOIN content_categories cc ON cc.id = cs.content_category_id
                LEFT JOIN content_pillars cp ON cp.id = cs.content_pillar_id
                LEFT JOIN content_objectives co ON co.id = cs.content_objective_id
                LEFT JOIN campaigns c ON c.id = cs.campaign_id
                ORDER BY cs.suggestion_date ASC';

        return $this->db->fetchAll($sql);
    }

    public function byPeriod(string $startDate, string $endDate, array $filters = []): array
    {
        $sql = 'SELECT cs.* FROM content_suggestions cs WHERE cs.status = 1
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

        $sql .= ' ORDER BY cs.suggestion_date ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function setChannels(int $suggestionId, array $platformIds): void
    {
        $this->db->delete('content_suggestion_channels', 'content_suggestion_id = :id', ['id' => $suggestionId]);

        foreach ($platformIds as $platformId) {
            $this->db->insert('content_suggestion_channels', [
                'content_suggestion_id' => $suggestionId,
                'content_platform_id' => (int) $platformId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getChannels(int $suggestionId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT content_platform_id FROM content_suggestion_channels WHERE content_suggestion_id = :id',
            ['id' => $suggestionId]
        );

        return array_map(static fn ($row) => (int) $row['content_platform_id'], $rows);
    }
}
