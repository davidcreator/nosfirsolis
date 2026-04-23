<?php

namespace Admin\Model;

class CampaignsModel extends AbstractCrudModel
{
    protected string $table = 'campaigns';
    protected array $fillable = [
        'name',
        'description',
        'objective',
        'start_date',
        'end_date',
        'status',
    ];

    public function activeInRange(string $startDate, string $endDate): array
    {
        $sql = 'SELECT * FROM campaigns
                WHERE (start_date IS NULL OR start_date <= :end_date)
                  AND (end_date IS NULL OR end_date >= :start_date)
                ORDER BY start_date ASC';

        return $this->db->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
