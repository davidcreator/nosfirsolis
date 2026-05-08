<?php

namespace Client\Model;

trait PlannerModelCalendarTrait
{
    public function upsertDayNote(int $userId, string $noteDate, string $contextType, string $noteText): void
    {
        $existing = $this->db->fetch(
            'SELECT id FROM content_day_notes WHERE user_id = :user_id AND note_date = :note_date AND context_type = :context_type LIMIT 1',
            [
                'user_id' => $userId,
                'note_date' => $noteDate,
                'context_type' => $contextType,
            ]
        );

        if ($existing) {
            $this->db->update('content_day_notes', [
                'note_text' => $noteText,
                'updated_at' => $this->modelClockDateTimeNow(),
            ], 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db->insert('content_day_notes', [
            'user_id' => $userId,
            'note_date' => $noteDate,
            'context_type' => $contextType,
            'note_text' => $noteText,
            'created_at' => $this->modelClockDateTimeNow(),
            'updated_at' => $this->modelClockDateTimeNow(),
        ]);
    }

    public function notesByPeriod(int $userId, string $startDate, string $endDate): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM content_day_notes
             WHERE user_id = :user_id
               AND note_date BETWEEN :start_date AND :end_date
             ORDER BY note_date ASC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['note_date']][] = $row;
        }

        return $grouped;
    }

    public function createExtraEvent(int $userId, array $data): int
    {
        if (!$this->ensureExtraEventsTable()) {
            return 0;
        }

        return $this->db->insert('calendar_extra_events', [
            'user_id' => $userId,
            'event_date' => $data['event_date'],
            'title' => $data['title'],
            'event_type' => $data['event_type'] ?? 'extra',
            'description' => $data['description'] ?? null,
            'color_hex' => $this->sanitizeColor((string) ($data['color_hex'] ?? '')),
            'created_at' => $this->modelClockDateTimeNow(),
            'updated_at' => $this->modelClockDateTimeNow(),
        ]);
    }

    public function deleteExtraEvent(int $userId, int $eventId): void
    {
        if (!$this->ensureExtraEventsTable()) {
            return;
        }

        $this->db->delete('calendar_extra_events', 'id = :id AND user_id = :user_id', [
            'id' => $eventId,
            'user_id' => $userId,
        ]);
    }

    public function extraEventsByPeriod(int $userId, string $startDate, string $endDate): array
    {
        if (!$this->ensureExtraEventsTable()) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM calendar_extra_events
             WHERE user_id = :user_id
               AND event_date BETWEEN :start_date AND :end_date
             ORDER BY event_date ASC, id DESC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['event_date']][] = $row;
        }

        return $grouped;
    }

    public function extraEventsFlatByPeriod(int $userId, string $startDate, string $endDate): array
    {
        if (!$this->ensureExtraEventsTable()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM calendar_extra_events
             WHERE user_id = :user_id
               AND event_date BETWEEN :start_date AND :end_date
             ORDER BY event_date ASC, id DESC',
            [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }

    public function calendarColors(int $userId): array
    {
        $defaults = $this->defaultCalendarColors();
        if (!$this->ensureCalendarColorsTable()) {
            return $defaults;
        }

        $rows = $this->db->fetchAll(
            'SELECT color_key, color_hex FROM user_calendar_colors WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        foreach ($rows as $row) {
            $key = (string) ($row['color_key'] ?? '');
            $color = $this->sanitizeColor((string) ($row['color_hex'] ?? ''));
            if ($key !== '' && $color !== null) {
                $defaults[$key] = $color;
            }
        }

        return $defaults;
    }

    public function saveCalendarColors(int $userId, array $colors): void
    {
        if (!$this->ensureCalendarColorsTable()) {
            return;
        }

        foreach ($colors as $key => $value) {
            $color = $this->sanitizeColor((string) $value);
            if ($color === null) {
                continue;
            }

            $existing = $this->db->fetch(
                'SELECT id FROM user_calendar_colors WHERE user_id = :user_id AND color_key = :color_key LIMIT 1',
                [
                    'user_id' => $userId,
                    'color_key' => (string) $key,
                ]
            );

            if ($existing) {
                $this->db->update('user_calendar_colors', [
                    'color_hex' => $color,
                    'updated_at' => $this->modelClockDateTimeNow(),
                ], 'id = :id', ['id' => (int) $existing['id']]);
                continue;
            }

            $this->db->insert('user_calendar_colors', [
                'user_id' => $userId,
                'color_key' => (string) $key,
                'color_hex' => $color,
                'updated_at' => $this->modelClockDateTimeNow(),
            ]);
        }
    }

    private function defaultCalendarColors(): array
    {
        return [
            'holiday_national' => '#f43f5e',
            'holiday_international' => '#2563eb',
            'holiday_regional' => '#eab308',
            'commemorative' => '#f59e0b',
            'suggestion' => '#0e9f6e',
            'campaign' => '#1d4ed8',
            'base_event' => '#9333ea',
            'extra_event' => '#9f3a03',
            'note' => '#6d28d9',
        ];
    }

    private function sanitizeColor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : null;
    }

    private function ensureExtraEventsTable(): bool
    {
        if ($this->extraEventsTableReady !== null) {
            return $this->extraEventsTableReady;
        }

        if (!$this->db->connected()) {
            $this->extraEventsTableReady = false;
            return false;
        }

        if (!$this->tableExists('calendar_extra_events')) {
            error_log(
                '[Solis] Tabela calendar_extra_events ausente. '
                . 'Execute a migracao operacional para habilitar eventos extras do calendario.'
            );
            $this->extraEventsTableReady = false;
            return false;
        }

        if (!$this->columnExists('calendar_extra_events', 'color_hex')) {
            error_log(
                '[Solis] Coluna calendar_extra_events.color_hex ausente. '
                . 'Execute a migracao operacional para habilitar cores de eventos extras.'
            );
            $this->extraEventsTableReady = false;
            return false;
        }

        $this->extraEventsTableReady = true;
        return true;
    }

    private function ensureCalendarColorsTable(): bool
    {
        if ($this->calendarColorsTableReady !== null) {
            return $this->calendarColorsTableReady;
        }

        if (!$this->db->connected()) {
            $this->calendarColorsTableReady = false;
            return false;
        }

        if (!$this->tableExists('user_calendar_colors')) {
            error_log(
                '[Solis] Tabela user_calendar_colors ausente. '
                . 'Execute a migracao operacional para habilitar cores personalizadas do calendario.'
            );
            $this->calendarColorsTableReady = false;
            return false;
        }

        $this->calendarColorsTableReady = true;
        return true;
    }

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        $row = $this->db->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }

    private function columnExists(string $table, string $column): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1 || preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
            return false;
        }

        $row = $this->db->fetch("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return (bool) $row;
    }
}
