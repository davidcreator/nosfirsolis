<?php

namespace System\Library;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;

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
                'moon_phase' => $this->moonPhase($date),
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
                    'moon_phase' => null,
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

    public function moonPhase(string $date): array
    {
        $targetDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if (!$targetDate) {
            return [
                'key' => 'new_moon',
                'label' => 'Lua nova',
                'icon' => '🌑',
                'age_days' => 0.0,
            ];
        }

        $target = $targetDate->setTime(12, 0, 0);
        $reference = new DateTimeImmutable('2000-01-06 18:14:00', new DateTimeZone('UTC'));
        $synodicMonthDays = 29.53058867;

        $elapsedDays = ($target->getTimestamp() - $reference->getTimestamp()) / 86400;
        $moonAge = fmod($elapsedDays, $synodicMonthDays);
        if ($moonAge < 0) {
            $moonAge += $synodicMonthDays;
        }

        $phase = $moonAge / $synodicMonthDays;
        $phaseIndex = (int) floor(($phase * 8) + 0.5) % 8;

        $phases = [
            ['key' => 'new_moon', 'label' => 'Lua nova', 'icon' => '🌑'],
            ['key' => 'waxing_crescent', 'label' => 'Lua crescente', 'icon' => '🌒'],
            ['key' => 'first_quarter', 'label' => 'Quarto crescente', 'icon' => '🌓'],
            ['key' => 'waxing_gibbous', 'label' => 'Lua gibosa crescente', 'icon' => '🌔'],
            ['key' => 'full_moon', 'label' => 'Lua cheia', 'icon' => '🌕'],
            ['key' => 'waning_gibbous', 'label' => 'Lua gibosa minguante', 'icon' => '🌖'],
            ['key' => 'last_quarter', 'label' => 'Quarto minguante', 'icon' => '🌗'],
            ['key' => 'waning_crescent', 'label' => 'Lua minguante', 'icon' => '🌘'],
        ];

        $phaseData = $phases[$phaseIndex] ?? $phases[0];
        $phaseData['age_days'] = round($moonAge, 2);
        return $phaseData;
    }
}
