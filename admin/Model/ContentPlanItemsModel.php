<?php

namespace Admin\Model;

class ContentPlanItemsModel extends AbstractCrudModel
{
    protected string $table = 'content_plan_items';
    protected array $fillable = [
        'content_plan_id',
        'planned_date',
        'title',
        'description',
        'content_suggestion_id',
        'campaign_id',
        'content_objective_id',
        'format_type',
        'channels_json',
        'status',
        'manual_note',
    ];

    public function byPlan(int $planId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM content_plan_items WHERE content_plan_id = :plan_id ORDER BY planned_date ASC',
            ['plan_id' => $planId]
        );
    }
}
