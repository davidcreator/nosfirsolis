<?php

namespace System\Library;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

class CalendarService
{
    public function buildMonth(int $year, int $month, array $eventsByDate = []): array
    {
        $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');
        $startWeekday = (int) $firstDay->format('N'); // 1=segunda

        $weeks = [];
        $week = [];

        for ($i = 1; $i < $startWeekday; $i++) {
            $week[] = [
                'date' => null,
                'day' => null,
                'in_month' => false,
                'events' => [],
            ];
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $week[] = [
                'date' => $date,
                'day' => $day,
                'in_month' => true,
                'events' => $eventsByDate[$date] ?? [
                    'holidays' => [],
                    'commemoratives' => [],
                    'suggestions' => [],
                    'campaigns' => [],
                ],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if (count($week) > 0) {
            while (count($week) < 7) {
                $week[] = [
                    'date' => null,
                    'day' => null,
                    'in_month' => false,
                    'events' => [],
                ];
            }
            $weeks[] = $week;
        }

        return [
            'year' => $year,
            'month' => $month,
            'first_day_weekday' => $startWeekday,
            'days_in_month' => $daysInMonth,
            'is_leap_year' => (bool) ((new DateTimeImmutable(sprintf('%04d-01-01', $year)))->format('L')),
            'weeks' => $weeks,
        ];
    }

    public function dateRange(string $startDate, string $endDate): array
    {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        if ($end < $start) {
            return [];
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }
}
