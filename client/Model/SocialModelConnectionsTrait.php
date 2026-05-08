<?php

namespace Client\Model;

trait SocialModelConnectionsTrait
{
    public function connectionsByUser(int $userId): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }

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
        if (!$this->schemaAvailable) {
            return;
        }
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
            'updated_at' => $this->modelClockDateTimeNow(),
        ];

        if ($existing) {
            $this->db->update('social_connections', $data, 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }

        $this->db->insert('social_connections', array_merge($data, [
            'user_id' => $userId,
            'platform_slug' => $platform,
            'created_at' => $this->modelClockDateTimeNow(),
        ]));
    }

    public function disconnectConnection(int $userId, string $platform): void
    {
        if (!$this->db->connected()) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db->update('social_connections', [
            'access_token_enc' => null,
            'refresh_token_enc' => null,
            'token_expires_at' => null,
            'status' => 'revoked',
            'updated_at' => $this->modelClockDateTimeNow(),
        ], 'user_id = :user_id AND platform_slug = :platform', [
            'user_id' => $userId,
            'platform' => $platform,
        ]);
    }
}
