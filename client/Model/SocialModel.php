<?php

namespace Client\Model;

use System\Engine\Model;
use System\Library\TokenCipher;

class SocialModel extends Model
{
    private bool $ensured = false;

    public function connectionsByUser(int $userId): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $this->ensureTables();

        return $this->db->fetchAll(
            'SELECT id, platform_slug, account_name, platform_user_id, scopes_text, token_expires_at, status, metadata_json, updated_at
             FROM social_connections
             WHERE user_id = :user_id
             ORDER BY platform_slug ASC',
            ['user_id' => $userId]
        );
    }

    public function upsertConnection(int $userId, string $platform, array $payload): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->ensureTables();
        $cipher = $this->cipher();

        $existing = $this->db->fetch(
            'SELECT id FROM social_connections WHERE user_id = :user_id AND platform_slug = :platform LIMIT 1',
            ['user_id' => $userId, 'platform' => $platform]
        );

        $data = [
            'account_name' => trim((string) ($payload['account_name'] ?? '')),
            'platform_user_id' => trim((string) ($payload['platform_user_id'] ?? '')),
            'access_token_enc' => $cipher->encrypt((string) ($payload['access_token'] ?? '')),
            'refresh_token_enc' => $cipher->encrypt((string) ($payload['refresh_token'] ?? '')),
            'scopes_text' => trim((string) ($payload['scopes_text'] ?? '')),
            'token_expires_at' => $payload['token_expires_at'] ?? null,
            'status' => trim((string) ($payload['status'] ?? 'connected')),
            'metadata_json' => json_encode($payload['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update('social_connections', $data, 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db->insert('social_connections', array_merge($data, [
            'user_id' => $userId,
            'platform_slug' => $platform,
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function disconnectConnection(int $userId, string $platform): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->ensureTables();
        $this->db->update('social_connections', [
            'access_token_enc' => null,
            'refresh_token_enc' => null,
            'token_expires_at' => null,
            'status' => 'revoked',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'user_id = :user_id AND platform_slug = :platform', [
            'user_id' => $userId,
            'platform' => $platform,
        ]);
    }

    public function saveDraft(int $userId, array $draft): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        $this->ensureTables();

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
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recentDrafts(int $userId, int $limit = 10): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $this->ensureTables();
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
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteFormatPreset(int $userId, int $presetId): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->ensureTables();
        $this->db->delete('social_format_presets', 'id = :id AND user_id = :user_id', [
            'id' => $presetId,
            'user_id' => $userId,
        ]);
    }

    private function ensureTables(): void
    {
        if ($this->ensured || !$this->db->connected()) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS social_connections (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                platform_slug VARCHAR(80) NOT NULL,
                account_name VARCHAR(190) NULL,
                platform_user_id VARCHAR(190) NULL,
                access_token_enc LONGTEXT NULL,
                refresh_token_enc LONGTEXT NULL,
                scopes_text TEXT NULL,
                token_expires_at DATETIME NULL,
                status ENUM(\'connected\', \'manual\', \'revoked\') NOT NULL DEFAULT \'connected\',
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY ux_social_user_platform (user_id, platform_slug),
                INDEX idx_social_connection_user (user_id),
                CONSTRAINT fk_social_connections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS social_content_drafts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(220) NOT NULL,
                goal VARCHAR(140) NULL,
                pillar VARCHAR(140) NULL,
                frequency VARCHAR(60) NULL,
                channels_json LONGTEXT NULL,
                base_text LONGTEXT NULL,
                hooks_json LONGTEXT NULL,
                hashtags_json LONGTEXT NULL,
                cta_text TEXT NULL,
                variants_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_social_drafts_user (user_id, created_at),
                CONSTRAINT fk_social_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS social_format_presets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                platform_slug VARCHAR(80) NOT NULL,
                format_type ENUM(\'post\', \'carousel\') NOT NULL DEFAULT \'post\',
                preset_name VARCHAR(150) NOT NULL,
                width_px SMALLINT UNSIGNED NOT NULL,
                height_px SMALLINT UNSIGNED NOT NULL,
                aspect_ratio VARCHAR(20) NOT NULL,
                safe_area_text VARCHAR(140) NULL,
                color_hex VARCHAR(7) NULL,
                notes TEXT NULL,
                source_links_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_social_format_user (user_id, platform_slug, format_type),
                CONSTRAINT fk_social_format_presets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    private function cipher(): TokenCipher
    {
        $security = (array) $this->config->get('security', []);
        $app = (array) $this->config->get('app', []);

        return new TokenCipher($security, $app);
    }
}
