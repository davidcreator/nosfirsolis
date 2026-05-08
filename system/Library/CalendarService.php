<?php

namespace System\Library;

class CalendarService
{
    public function buildMonth(int $year, int $month, array $eventsByDate = []): array
    {
        $firstDayTs = gmmktime(0, 0, 0, $month, 1, $year);
        if (!is_int($firstDayTs)) {
            return [
                'year' => $year,
                'month' => $month,
                'first_day_weekday' => 1,
                'days_in_month' => 0,
                'is_leap_year' => false,
                'weeks' => [],
            ];
        }

        $daysInMonth = (int) gmdate('t', $firstDayTs);
        $startWeekday = (int) gmdate('N', $firstDayTs); // 1=segunda

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
            'is_leap_year' => (int) gmdate('L', gmmktime(0, 0, 0, 1, 1, $year)) === 1,
            'weeks' => $weeks,
        ];
    }

    public function dateRange(string $startDate, string $endDate): array
    {
        $start = $this->dateToUtcTimestamp($startDate);
        $end = $this->dateToUtcTimestamp($endDate);
        if ($start === null || $end === null || $end < $start) {
            return [];
        }

        $dates = [];
        for ($cursor = $start; $cursor <= $end; $cursor += 86400) {
            $dates[] = gmdate('Y-m-d', $cursor);
        }

        return $dates;
    }

    public function moonPhase(string $date): array
    {
        $parts = $this->parseDateParts($date);
        if ($parts === null) {
            return [
                'key' => 'new_moon',
                'label' => 'Lua nova',
                'icon' => '🌑',
                'age_days' => 0.0,
            ];
        }

        [$year, $month, $day] = $parts;
        $target = gmmktime(12, 0, 0, $month, $day, $year);
        $reference = gmmktime(18, 14, 0, 1, 6, 2000);
        if (!is_int($target) || !is_int($reference)) {
            return [
                'key' => 'new_moon',
                'label' => 'Lua nova',
                'icon' => '🌑',
                'age_days' => 0.0,
            ];
        }

        $synodicMonthDays = 29.53058867;
        $elapsedDays = ($target - $reference) / 86400;
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

    private function dateToUtcTimestamp(string $value): ?int
    {
        $parts = $this->parseDateParts($value);
        if ($parts === null) {
            return null;
        }

        [$year, $month, $day] = $parts;
        $timestamp = gmmktime(0, 0, 0, $month, $day, $year);
        return is_int($timestamp) ? $timestamp : null;
    }

    private function parseDateParts(string $value): ?array
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);
        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return [$year, $month, $day];
    }
}
