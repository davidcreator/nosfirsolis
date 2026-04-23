<?php

namespace Client\Model;

use DateTimeImmutable;
use System\Engine\Model;

class CalendarModel extends Model
{
    public function eventsByPeriod(string $startDate, string $endDate, array $filters = []): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $showNational = (int) ($filters['show_holiday_national'] ?? 1) === 1;
        $showRegional = (int) ($filters['show_holiday_regional'] ?? 1) === 1;
        $showInternational = (int) ($filters['show_holiday_international'] ?? 1) === 1;
        $showCommemoratives = (int) ($filters['show_commemoratives'] ?? 1) === 1;
        $showSuggestions = (int) ($filters['show_suggestions'] ?? 1) === 1;
        $showBaseEvents = (int) ($filters['show_base_events'] ?? 1) === 1;

        $events = [];

        if ($showNational || $showRegional || $showInternational) {
            $holidayTypes = [];
            if ($showNational) {
                $holidayTypes[] = 'national';
            }
            if ($showRegional) {
                $holidayTypes[] = 'regional';
            }
            if ($showInternational) {
                $holidayTypes[] = 'international';
            }

            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];

            $sql = 'SELECT h.* FROM holidays h WHERE h.holiday_date BETWEEN :start_date AND :end_date';
            if ($holidayTypes !== []) {
                $in = [];
                foreach ($holidayTypes as $i => $type) {
                    $key = 'holiday_type_' . $i;
                    $in[] = ':' . $key;
                    $params[$key] = $type;
                }
                $sql .= ' AND h.holiday_type IN (' . implode(', ', $in) . ')';
            }

            foreach ($this->db->fetchAll($sql . ' ORDER BY h.holiday_date ASC', $params) as $row) {
                $events[$row['holiday_date']]['holidays'][] = $row;
            }
        }

        if ($showCommemoratives) {
            $rows = $this->db->fetchAll(
                'SELECT * FROM commemorative_dates WHERE event_date BETWEEN :start_date AND :end_date ORDER BY event_date ASC',
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );

            foreach ($rows as $row) {
                $events[$row['event_date']]['commemoratives'][] = $row;
            }
        }

        if ($showSuggestions) {
            $params = [
                'start_date_main' => $startDate,
                'end_date_main' => $endDate,
                'start_date_rec' => $startDate,
                'end_date_rec' => $endDate,
            ];

            $sql = 'SELECT cs.* FROM content_suggestions cs WHERE cs.status = 1
                    AND (cs.suggestion_date BETWEEN :start_date_main AND :end_date_main
                         OR (cs.is_recurring = 1 AND DATE_FORMAT(cs.suggestion_date, "%m-%d") BETWEEN DATE_FORMAT(:start_date_rec, "%m-%d") AND DATE_FORMAT(:end_date_rec, "%m-%d")))';

            if (!empty($filters['campaign_id'])) {
                $sql .= ' AND cs.campaign_id = :campaign_id';
                $params['campaign_id'] = (int) $filters['campaign_id'];
            }

            if (!empty($filters['objective_id'])) {
                $sql .= ' AND cs.content_objective_id = :objective_id';
                $params['objective_id'] = (int) $filters['objective_id'];
            }

            if (!empty($filters['channel_id'])) {
                $sql .= ' AND EXISTS (
                    SELECT 1
                    FROM content_suggestion_channels csc
                    WHERE csc.content_suggestion_id = cs.id
                      AND csc.content_platform_id = :channel_id
                )';
                $params['channel_id'] = (int) $filters['channel_id'];
            }

            foreach ($this->db->fetchAll($sql . ' ORDER BY cs.suggestion_date ASC', $params) as $row) {
                $date = $row['suggestion_date'];
                if ((int) $row['is_recurring'] === 1) {
                    $targetYear = (int) substr($startDate, 0, 4);
                    $date = $targetYear . '-' . date('m-d', strtotime($row['suggestion_date']));
                }

                if ($date >= $startDate && $date <= $endDate) {
                    $events[$date]['suggestions'][] = $row;
                }
            }
        }

        if ($showBaseEvents) {
            $baseEvents = $this->baseEventsByPeriod($startDate, $endDate);
            foreach ($baseEvents as $date => $items) {
                $events[$date]['base_events'] = $items;
            }
        }

        $campaignRows = $this->db->fetchAll(
            'SELECT * FROM campaigns
             WHERE (start_date IS NULL OR start_date <= :end_date)
               AND (end_date IS NULL OR end_date >= :start_date)
             ORDER BY start_date ASC',
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        foreach ($campaignRows as $campaign) {
            if (empty($campaign['start_date']) || empty($campaign['end_date'])) {
                continue;
            }

            $cursor = $campaign['start_date'];
            while ($cursor <= $campaign['end_date']) {
                if ($cursor >= $startDate && $cursor <= $endDate) {
                    $events[$cursor]['campaigns'][] = $campaign;
                }
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }

        return $events;
    }

    public function filterData(): array
    {
        return [
            'channels' => $this->db->fetchAll('SELECT id, name FROM content_platforms WHERE status = 1 ORDER BY name ASC'),
            'objectives' => $this->db->fetchAll('SELECT id, name FROM content_objectives WHERE status = 1 ORDER BY name ASC'),
            'campaigns' => $this->db->fetchAll('SELECT id, name FROM campaigns ORDER BY name ASC'),
        ];
    }

    public function holidayCatalog(): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, name, holiday_date, holiday_type, country_code, state_code
             FROM holidays
             WHERE status = 1
               AND holiday_type IN ("national", "international", "regional")
             ORDER BY holiday_date ASC, name ASC'
        );
    }

    public function baseEventsCatalog(int $year): array
    {
        $source = $this->baseEventSource();
        if ($source === []) {
            return [];
        }

        $catalog = [];
        $yearStart = new DateTimeImmutable(sprintf('%04d-01-01', $year));

        foreach ($source as $dayOfYear => $rawText) {
            $parsed = $this->parseBaseEventText($rawText);
            $targetDate = $yearStart->modify('+' . ((int) $dayOfYear - 1) . ' day');
            $sameYear = (int) $targetDate->format('Y') === $year;

            $catalog[] = [
                'day_of_year' => (int) $dayOfYear,
                'raw_text' => $rawText,
                'title' => $parsed['title'],
                'description' => $parsed['description'],
                'date_ref' => $sameYear ? $targetDate->format('Y-m-d') : null,
                'date_label' => $sameYear ? $targetDate->format('d/m') : 'Ano bissexto',
            ];
        }

        return $catalog;
    }

    public function baseEventsByPeriod(string $startDate, string $endDate): array
    {
        $source = $this->baseEventSource();
        if ($source === []) {
            return [];
        }

        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        $events = [];

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $doy = (int) $cursor->format('z') + 1;
            if (!isset($source[$doy])) {
                continue;
            }

            $parsed = $this->parseBaseEventText($source[$doy]);
            $date = $cursor->format('Y-m-d');
            $events[$date][] = [
                'day_of_year' => $doy,
                'raw_text' => $source[$doy],
                'title' => $parsed['title'],
                'description' => $parsed['description'],
            ];
        }

        return $events;
    }

    private function baseEventSource(): array
    {
        $file = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'base_events.php';
        if (!is_file($file)) {
            return [];
        }

        $rows = require $file;
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $day => $text) {
            $key = (int) $day;
            $value = trim((string) $text);
            if ($key <= 0 || $value === '') {
                continue;
            }
            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function parseBaseEventText(string $rawText): array
    {
        $parts = explode(' - ', $rawText, 2);
        $title = trim($parts[0]);
        $description = isset($parts[1]) ? trim($parts[1]) : '';

        if ($title === '') {
            $title = 'Evento base';
        }

        return [
            'title' => $title,
            'description' => $description,
        ];
    }
}
