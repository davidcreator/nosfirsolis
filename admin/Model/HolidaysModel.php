<?php

namespace Admin\Model;

class HolidaysModel extends AbstractCrudModel
{
    protected string $table = 'holidays';
    protected array $fillable = [
        'name',
        'holiday_date',
        'month_day',
        'is_fixed',
        'is_movable',
        'movable_rule',
        'holiday_type',
        'holiday_region_id',
        'country_code',
        'state_code',
        'description',
        'status',
    ];

    public function byPeriod(string $startDate, string $endDate, array $types = []): array
    {
        $sql = 'SELECT h.*, hr.name AS region_name
                FROM holidays h
                LEFT JOIN holiday_regions hr ON hr.id = h.holiday_region_id
                WHERE h.holiday_date BETWEEN :start_date AND :end_date';
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if (!empty($types)) {
            $in = [];
            foreach ($types as $i => $type) {
                $key = 't' . $i;
                $in[] = ':' . $key;
                $params[$key] = $type;
            }
            $sql .= ' AND h.holiday_type IN (' . implode(', ', $in) . ')';
        }

        $sql .= ' ORDER BY h.holiday_date ASC';

        return $this->db->fetchAll($sql, $params);
    }
}
