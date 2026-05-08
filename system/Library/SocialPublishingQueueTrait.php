<?php

namespace System\Library;

trait SocialPublishingQueueTrait
{
    public function queuePublication(int $userId, array $data): int
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }
        $platformSlug = strtolower(trim((string) ($data['platform_slug'] ?? '')));
        if ($platformSlug === '') {
            return 0;
        }

        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_publish_hub');
        if (empty($feature['allowed'])) {
            return 0;
        }

        $quota = $subscription->evaluateQuota($userId, 'max_social_publications_per_month', 1);
        if (empty($quota['allowed'])) {
            return 0;
        }

        $connection = $this->connectionByUserAndPlatform($userId, $platformSlug);
        $status = 'queued';
        $errorMessage = null;
        if (!$connection) {
            $status = 'manual_review';
            $errorMessage = 'Conta da plataforma nao conectada para este usuario.';
        }

        $timestamp = $this->clockDateTimeNow();
        $insertId = $this->db()->insert('social_publications', [
            'user_id' => $userId,
            'plan_id' => !empty($data['plan_id']) ? (int) $data['plan_id'] : null,
            'plan_item_id' => !empty($data['plan_item_id']) ? (int) $data['plan_item_id'] : null,
            'platform_slug' => $platformSlug,
            'connection_id' => $connection ? (int) $connection['id'] : null,
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'message_text' => trim((string) ($data['message_text'] ?? '')) ?: null,
            'media_url' => trim((string) ($data['media_url'] ?? '')) ?: null,
            'payload_json' => json_encode((array) ($data['payload'] ?? []), JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'provider_post_id' => null,
            'scheduled_at' => $this->normalizeDatetime($data['scheduled_at'] ?? null),
            'published_at' => null,
            'error_message' => $errorMessage,
            'attempt_count' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->log($insertId, $status === 'queued' ? 'info' : 'warning', 'Publicacao adicionada ao hub.', [
            'platform_slug' => $platformSlug,
            'status' => $status,
        ]);

        return $insertId;
    }

    public function queueFromPlanItem(int $userId, int $planItemId, array $platforms = [], array $overrides = []): int
    {
        if (!$this->db()?->connected() || $userId <= 0 || $planItemId <= 0) {
            return 0;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return 0;
        }

        $item = $this->db()->fetch(
            'SELECT cpi.*, cp.id AS plan_id, cp.user_id
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cpi.id = :item_id
               AND cp.user_id = :user_id
             LIMIT 1',
            [
                'item_id' => $planItemId,
                'user_id' => $userId,
            ]
        );
        if (!$item) {
            return 0;
        }

        if (empty($platforms)) {
            $channels = json_decode((string) ($item['channels_json'] ?? '[]'), true);
            if (is_array($channels)) {
                $platforms = $channels;
            }
        }
        $platforms = $this->normalizePlatforms($platforms);
        if (empty($platforms)) {
            return 0;
        }

        $queued = 0;
        foreach ($platforms as $platformSlug) {
            $publicationId = $this->queuePublication($userId, [
                'plan_id' => (int) ($item['plan_id'] ?? 0),
                'plan_item_id' => $planItemId,
                'platform_slug' => $platformSlug,
                'title' => (string) ($item['title'] ?? ''),
                'message_text' => (string) ($overrides['message_text'] ?? $item['description'] ?? ''),
                'media_url' => (string) ($overrides['media_url'] ?? ''),
                'payload' => [
                    'origin' => 'plan_item_auto',
                    'format_type' => (string) ($item['format_type'] ?? ''),
                    'status' => (string) ($item['status'] ?? ''),
                ],
                'scheduled_at' => $overrides['scheduled_at'] ?? null,
            ]);
            if ($publicationId > 0) {
                $queued++;
            }
        }

        return $queued;
    }

    public function processDueQueue(int $userId, int $limit = 20): array
    {
        $summary = [
            'total' => 0,
            'published' => 0,
            'failed' => 0,
            'manual_review' => 0,
        ];

        if (!$this->db()?->connected() || $userId <= 0) {
            return $summary;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return $summary;
        }
        $limit = max(1, min(200, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT id
             FROM social_publications
             WHERE user_id = :user_id
               AND status IN (\'queued\', \'failed\')
               AND (scheduled_at IS NULL OR scheduled_at <= :now_at)
             ORDER BY id ASC
            LIMIT ' . $limit,
            [
                'user_id' => $userId,
                'now_at' => $this->clockDateTimeNow(),
            ]
        );

        foreach ($rows as $row) {
            $summary['total']++;
            $attempt = $this->publishNow($userId, (int) ($row['id'] ?? 0));
            $status = (string) ($attempt['status'] ?? 'failed');
            if (isset($summary[$status])) {
                $summary[$status]++;
            } elseif ($status === 'published') {
                $summary['published']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function listByUser(int $userId, int $limit = 100): array
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT sp.*, cpi.title AS plan_item_title
             FROM social_publications sp
             LEFT JOIN content_plan_items cpi ON cpi.id = sp.plan_item_id
             WHERE sp.user_id = :user_id
             ORDER BY sp.id DESC
             LIMIT ' . $limit,
            ['user_id' => $userId]
        );
    }

    public function logsByPublication(int $publicationId, int $limit = 20): array
    {
        if (!$this->db()?->connected() || $publicationId <= 0) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(200, $limit));

        return $this->db()->fetchAll(
            'SELECT *
             FROM social_publication_logs
             WHERE publication_id = :publication_id
             ORDER BY id DESC
             LIMIT ' . $limit,
            ['publication_id' => $publicationId]
        );
    }

    private function normalizePlatforms(array $platforms): array
    {
        $normalized = [];
        foreach ($platforms as $platform) {
            $slug = strtolower(trim((string) $platform));
            if ($slug === '') {
                continue;
            }
            $normalized[$slug] = true;
        }

        return array_keys($normalized);
    }

    private function normalizeDatetime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);
        $hour = (int) ($matches[4] ?? 0);
        $minute = (int) ($matches[5] ?? 0);
        $second = (int) ($matches[6] ?? 0);

        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    private function connectionByUserAndPlatform(int $userId, string $platformSlug): ?array
    {
        return $this->db()->fetch(
            'SELECT *
             FROM social_connections
             WHERE user_id = :user_id
               AND platform_slug = :platform_slug
               AND status IN (\'connected\', \'manual\')
             LIMIT 1',
            [
                'user_id' => $userId,
                'platform_slug' => $platformSlug,
            ]
        );
    }

    private function connectionById(int $connectionId, int $userId): ?array
    {
        if ($connectionId <= 0) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM social_connections
             WHERE id = :id
               AND user_id = :user_id
               AND status IN (\'connected\', \'manual\')
             LIMIT 1',
            [
                'id' => $connectionId,
                'user_id' => $userId,
            ]
        );
    }
}
