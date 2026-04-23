<?php

namespace Admin\Model;

class ContentPlansModel extends AbstractCrudModel
{
    protected string $table = 'content_plans';
    protected array $fillable = [
        'user_id',
        'campaign_id',
        'name',
        'start_date',
        'end_date',
        'year_ref',
        'month_ref',
        'filters_json',
        'status',
        'notes',
    ];

    public function withTotals(int $userId): array
    {
        $sql = 'SELECT cp.*, COUNT(cpi.id) AS total_items
                FROM content_plans cp
                LEFT JOIN content_plan_items cpi ON cpi.content_plan_id = cp.id
                WHERE cp.user_id = :user_id
                GROUP BY cp.id
                ORDER BY cp.created_at DESC';

        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }
}
