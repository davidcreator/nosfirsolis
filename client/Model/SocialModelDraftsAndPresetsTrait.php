<?php

namespace Client\Model;

trait SocialModelDraftsAndPresetsTrait
{
    public function saveDraft(int $userId, array $draft): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }

        return $this->db->insert('social_content_drafts', [
            'user_id' => $userId,
            'title' => (string) ($draft['title'] ?? 'Plano estrategico'),
            'goal' => (string) ($draft['objective'] ?? ''),
            'pillar' => (string) ($draft['pillar'] ?? ''),
            'frequency' => (string) ($draft['frequency'] ?? 'semanal'),
            'channels_json' => json_encode($draft['channels'] ?? [], JSON_UNESCAPED_UNICODE),
            'base_text' => (string) ($draft['base_text'] ?? ''),
            'hooks_json' => json_encode($draft['hooks'] ?? [], JSON_UNESCAPED_UNICODE),
            'hashtags_json' => json_encode($draft['hashtags'] ?? [], JSON_UNESCAPED_UNICODE),
            'cta_text' => (string) ($draft['cta'] ?? ''),
            'variants_json' => json_encode($draft['variants'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at' => $this->modelClockDateTimeNow(),
            'updated_at' => $this->modelClockDateTimeNow(),
        ]);
    }

    public function recentDrafts(int $userId, int $limit = 10): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT *
             FROM social_content_drafts
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['user_id' => $userId]
        );

        foreach ($rows as &$row) {
            $row['channels'] = json_decode((string) ($row['channels_json'] ?? '[]'), true) ?: [];
            $row['hooks'] = json_decode((string) ($row['hooks_json'] ?? '[]'), true) ?: [];
            $row['hashtags'] = json_decode((string) ($row['hashtags_json'] ?? '[]'), true) ?: [];
            $row['variants'] = json_decode((string) ($row['variants_json'] ?? '[]'), true) ?: [];
        }
        unset($row);

        return $rows;
    }

    public function formatPresetsByUser(int $userId): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, platform_slug, format_type, preset_name, width_px, height_px, aspect_ratio, safe_area_text, color_hex, notes, source_links_json, created_at, updated_at
             FROM social_format_presets
             WHERE user_id = :user_id
             ORDER BY updated_at DESC, id DESC',
            ['user_id' => $userId]
        );
    }

    public function createFormatPreset(int $userId, array $data): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }

        $formatType = strtolower(trim((string) ($data['format_type'] ?? 'post')));
        if (!in_array($formatType, ['post', 'carousel'], true)) {
            $formatType = 'post';
        }

        $width = max(1, min(8000, (int) ($data['width_px'] ?? 1080)));
        $height = max(1, min(8000, (int) ($data['height_px'] ?? 1080)));
        $ratio = trim((string) ($data['aspect_ratio'] ?? '1:1'));
        if ($ratio === '') {
            $ratio = '1:1';
        }

        $color = strtoupper(trim((string) ($data['color_hex'] ?? '')));
        if ($color !== '' && preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            $color = null;
        }

        $name = trim((string) ($data['preset_name'] ?? ''));
        if ($name === '') {
            $name = ucfirst((string) ($data['platform_slug'] ?? 'canal')) . ' ' . ($formatType === 'carousel' ? 'Carrossel' : 'Post');
        }

        $sources = $data['source_links'] ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }

        return $this->db->insert('social_format_presets', [
            'user_id' => $userId,
            'platform_slug' => strtolower(trim((string) ($data['platform_slug'] ?? ''))),
            'format_type' => $formatType,
            'preset_name' => $name,
            'width_px' => $width,
            'height_px' => $height,
            'aspect_ratio' => $ratio,
            'safe_area_text' => trim((string) ($data['safe_area_text'] ?? '')),
            'color_hex' => $color,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'source_links_json' => json_encode(array_values($sources), JSON_UNESCAPED_UNICODE),
            'created_at' => $this->modelClockDateTimeNow(),
            'updated_at' => $this->modelClockDateTimeNow(),
        ]);
    }

    public function deleteFormatPreset(int $userId, int $presetId): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db->delete('social_format_presets', 'id = :id AND user_id = :user_id', [
            'id' => $presetId,
            'user_id' => $userId,
        ]);
    }
}
