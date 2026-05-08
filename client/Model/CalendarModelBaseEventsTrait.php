<?php

namespace Client\Model;

trait CalendarModelBaseEventsTrait
{
    public function baseEventsCatalog(int $year): array
    {
        $source = $this->baseEventSource();
        if ($source === []) {
            return [];
        }

        $catalog = [];

        foreach ($source as $dayOfYear => $rawText) {
            $parsed = $this->parseBaseEventText($rawText);
            $targetTimestamp = mktime(0, 0, 0, 1, (int) $dayOfYear, $year);
            $sameYear = is_int($targetTimestamp)
                && (int) $this->modelClockFormatAt((int) $targetTimestamp, 'Y') === $year;

            $catalog[] = [
                'day_of_year' => (int) $dayOfYear,
                'raw_text' => $rawText,
                'title' => $parsed['title'],
                'description' => $parsed['description'],
                'date_ref' => $sameYear ? $this->modelClockFormatAt((int) $targetTimestamp, 'Y-m-d') : null,
                'date_label' => $sameYear ? $this->modelClockFormatAt((int) $targetTimestamp, 'd/m') : 'Ano bissexto',
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

        $startTimestamp = $this->parseDateToTimestamp($startDate);
        $endTimestamp = $this->parseDateToTimestamp($endDate);
        if ($startTimestamp === null || $endTimestamp === null || $startTimestamp > $endTimestamp) {
            return [];
        }

        $events = [];

        for ($cursor = $startTimestamp; $cursor <= $endTimestamp;) {
            $doy = (int) $this->modelClockFormatAt($cursor, 'z') + 1;
            if (!isset($source[$doy])) {
                $nextCursor = $this->advanceByDays($cursor, 1);
                if ($nextCursor === null || $nextCursor <= $cursor) {
                    break;
                }
                $cursor = $nextCursor;
                continue;
            }

            $parsed = $this->parseBaseEventText($source[$doy]);
            $date = $this->modelClockFormatAt($cursor, 'Y-m-d');
            $events[$date][] = [
                'day_of_year' => $doy,
                'raw_text' => $source[$doy],
                'title' => $parsed['title'],
                'description' => $parsed['description'],
            ];

            $nextCursor = $this->advanceByDays($cursor, 1);
            if ($nextCursor === null || $nextCursor <= $cursor) {
                break;
            }
            $cursor = $nextCursor;
        }

        return $events;
    }

    private function parseDateToTimestamp(string $value): ?int
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

        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        return is_int($timestamp) ? $timestamp : null;
    }

    private function advanceByDays(int $timestamp, int $days): ?int
    {
        if ($days === 0) {
            return $timestamp;
        }

        $parts = getdate($timestamp);
        $next = mktime(0, 0, 0, (int) ($parts['mon'] ?? 0), (int) ($parts['mday'] ?? 0) + $days, (int) ($parts['year'] ?? 0));
        return is_int($next) ? $next : null;
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
