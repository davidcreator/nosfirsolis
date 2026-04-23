<?php

namespace Admin\Model;

class CommemorativeDatesModel extends AbstractCrudModel
{
    protected string $table = 'commemorative_dates';
    protected array $fillable = [
        'name',
        'event_date',
        'month_day',
        'recurrence_type',
        'context_type',
        'country_code',
        'description',
        'status',
    ];

    public function byPeriod(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM commemorative_dates WHERE event_date BETWEEN :start_date AND :end_date ORDER BY event_date ASC',
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }
}
