<?php

namespace System\Library;

use System\Engine\Registry;

class SocialPublishingService
{
    private bool $ensured = false;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS social_publications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                plan_id INT UNSIGNED NULL,
                plan_item_id INT UNSIGNED NULL,
                platform_slug VARCHAR(80) NOT NULL,
                connection_id INT UNSIGNED NULL,
                title VARCHAR(220) NULL,
                message_text LONGTEXT NULL,
                media_url VARCHAR(1000) NULL,
                payload_json LONGTEXT NULL,
                status ENUM(\'queued\', \'processing\', \'published\', \'failed\', \'manual_review\') NOT NULL DEFAULT \'queued\',
                provider_post_id VARCHAR(190) NULL,
                scheduled_at DATETIME NULL,
                published_at DATETIME NULL,
                error_message VARCHAR(255) NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_social_publications_user (user_id, status, scheduled_at),
                INDEX idx_social_publications_item (plan_item_id),
                CONSTRAINT fk_social_publications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_social_publications_plan FOREIGN KEY (plan_id) REFERENCES content_plans(id) ON DELETE SET NULL,
                CONSTRAINT fk_social_publications_item FOREIGN KEY (plan_item_id) REFERENCES content_plan_items(id) ON DELETE SET NULL,
                CONSTRAINT fk_social_publications_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS social_publication_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                publication_id INT UNSIGNED NOT NULL,
                log_level ENUM(\'info\', \'warning\', \'error\') NOT NULL DEFAULT \'info\',
                message VARCHAR(255) NOT NULL,
                context_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_social_publication_logs_pub (publication_id, created_at),
                CONSTRAINT fk_social_publication_logs_pub FOREIGN KEY (publication_id) REFERENCES social_publications(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    public function queuePublication(int $userId, array $data): int
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return 0;
        }

        $this->ensureTables();
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
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
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

    public function publishNow(int $userId, int $publicationId): array
    {
        $result = [
            'ok' => false,
            'status' => 'failed',
            'message' => 'Publicacao nao encontrada.',
            'publication_id' => $publicationId,
        ];

        if (!$this->db()?->connected() || $userId <= 0 || $publicationId <= 0) {
            return $result;
        }

        $this->ensureTables();

        $publication = $this->db()->fetch(
            'SELECT *
             FROM social_publications
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1',
            [
                'id' => $publicationId,
                'user_id' => $userId,
            ]
        );
        if (!$publication) {
            return $result;
        }

        if ((string) ($publication['status'] ?? '') === 'published') {
            return [
                'ok' => true,
                'status' => 'published',
                'message' => 'Publicacao ja estava publicada.',
                'publication_id' => $publicationId,
            ];
        }

        $platformSlug = (string) ($publication['platform_slug'] ?? '');
        $dryRun = (bool) $this->config()?->get('integrations.social_publisher.dry_run', true);

        $this->setStatus($publicationId, 'processing', null);
        $this->log($publicationId, 'info', 'Processando publicacao.', ['platform_slug' => $platformSlug, 'dry_run' => $dryRun]);

        if ($dryRun) {
            $providerPostId = 'mock-' . $publicationId . '-' . date('YmdHis');
            $this->setPublished($publicationId, $providerPostId);
            $this->log($publicationId, 'info', 'Publicacao concluida em modo dry-run.', [
                'provider_post_id' => $providerPostId,
            ]);

            return [
                'ok' => true,
                'status' => 'published',
                'message' => 'Publicacao concluida em modo dry-run.',
                'publication_id' => $publicationId,
            ];
        }

        $connection = $this->connectionById((int) ($publication['connection_id'] ?? 0), $userId);
        if (!$connection) {
            $this->setStatus($publicationId, 'manual_review', 'Conexao da plataforma nao encontrada.');
            $this->log($publicationId, 'warning', 'Publicacao movida para revisao manual por falta de conexao.', []);

            return [
                'ok' => false,
                'status' => 'manual_review',
                'message' => 'Conexao da plataforma nao encontrada.',
                'publication_id' => $publicationId,
            ];
        }

        $publishAttempt = $this->publishToProvider($publication, $connection);
        if (!empty($publishAttempt['ok'])) {
            $providerPostId = trim((string) ($publishAttempt['provider_post_id'] ?? ''));
            if ($providerPostId === '') {
                $providerPostId = 'provider-' . $publicationId . '-' . date('YmdHis');
            }

            $this->setPublished($publicationId, $providerPostId);
            $this->log($publicationId, 'info', 'Publicacao enviada para provedor.', $publishAttempt);

            return [
                'ok' => true,
                'status' => 'published',
                'message' => 'Publicacao enviada com sucesso.',
                'publication_id' => $publicationId,
            ];
        }

        $reason = trim((string) ($publishAttempt['error'] ?? 'Falha no envio para o provedor.'));
        $this->setStatus($publicationId, 'failed', $reason);
        $this->log($publicationId, 'error', 'Falha de publicacao.', $publishAttempt);

        return [
            'ok' => false,
            'status' => 'failed',
            'message' => $reason,
            'publication_id' => $publicationId,
        ];
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
                'now_at' => date('Y-m-d H:i:s'),
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

    private function publishToProvider(array $publication, array $connection): array
    {
        $platform = strtolower((string) ($publication['platform_slug'] ?? ''));

        if ($platform === 'linkedin') {
            return $this->publishLinkedIn($publication, $connection);
        }

        return [
            'ok' => false,
            'error' => 'Provedor oficial ainda nao implementado para esta plataforma. Use dry-run ou revisao manual.',
            'provider' => $platform,
        ];
    }

    private function publishLinkedIn(array $publication, array $connection): array
    {
        $token = $this->decryptToken((string) ($connection['access_token_enc'] ?? ''));
        if ($token === '') {
            return [
                'ok' => false,
                'error' => 'Access token da conexao LinkedIn esta ausente.',
            ];
        }

        $platformUserId = trim((string) ($connection['platform_user_id'] ?? ''));
        if ($platformUserId === '' || !str_starts_with($platformUserId, 'urn:li:')) {
            return [
                'ok' => false,
                'error' => 'platform_user_id do LinkedIn deve ser um URN (urn:li:person ou urn:li:organization).',
            ];
        }

        $messageText = trim((string) ($publication['message_text'] ?? ''));
        if ($messageText === '') {
            $messageText = trim((string) ($publication['title'] ?? 'Publication via Solis'));
        }
        if ($messageText === '') {
            $messageText = 'Publication via Solis';
        }

        $linkedInVersion = trim((string) $this->config()?->get('integrations.social_publisher.linkedin_version', date('Ym')));
        if (!preg_match('/^\d{6}$/', $linkedInVersion)) {
            $linkedInVersion = date('Ym');
        }

        $payload = [
            'author' => $platformUserId,
            'commentary' => $messageText,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $response = $this->httpJsonRequest(
            'POST',
            'https://api.linkedin.com/rest/posts',
            [
                'Authorization: Bearer ' . $token,
                'X-Restli-Protocol-Version: 2.0.0',
                'Linkedin-Version: ' . $linkedInVersion,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($response['error'] ?? 'Falha HTTP no LinkedIn'),
                'http_status' => $response['http_status'] ?? null,
                'body' => $response['body'] ?? '',
            ];
        }

        $providerPostId = '';
        $headers = (array) ($response['headers'] ?? []);
        foreach ($headers as $headerLine) {
            if (stripos((string) $headerLine, 'x-restli-id:') === 0) {
                $providerPostId = trim(substr((string) $headerLine, strlen('x-restli-id:')));
                break;
            }
        }

        return [
            'ok' => true,
            'provider_post_id' => $providerPostId,
            'http_status' => $response['http_status'] ?? null,
            'body' => $response['body'] ?? '',
        ];
    }

    private function setPublished(int $publicationId, string $providerPostId): void
    {
        $this->db()->query(
            'UPDATE social_publications
             SET status = \'published\',
                 provider_post_id = :provider_post_id,
                 published_at = :published_at,
                 error_message = NULL,
                 attempt_count = attempt_count + 1,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'provider_post_id' => mb_substr($providerPostId, 0, 190),
                'published_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $publicationId,
            ]
        );
    }

    private function setStatus(int $publicationId, string $status, ?string $errorMessage): void
    {
        $allowed = ['queued', 'processing', 'published', 'failed', 'manual_review'];
        if (!in_array($status, $allowed, true)) {
            $status = 'failed';
        }

        $this->db()->query(
            'UPDATE social_publications
             SET status = :status,
                 error_message = :error_message,
                 attempt_count = attempt_count + 1,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'status' => $status,
                'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 255) : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $publicationId,
            ]
        );
    }

    private function log(int $publicationId, string $level, string $message, array $context = []): void
    {
        if ($publicationId <= 0) {
            return;
        }

        $allowed = ['info', 'warning', 'error'];
        if (!in_array($level, $allowed, true)) {
            $level = 'info';
        }

        $this->db()->insert('social_publication_logs', [
            'publication_id' => $publicationId,
            'log_level' => $level,
            'message' => mb_substr(trim($message), 0, 255),
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
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

    private function decryptToken(string $encrypted): string
    {
        $cipher = new TokenCipher(
            (array) $this->config()?->get('security', []),
            (array) $this->config()?->get('app', [])
        );

        return $cipher->decrypt($encrypted);
    }

    private function httpJsonRequest(string $method, string $url, array $headers, ?string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($ch, CURLOPT_HEADER, true);

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if ($raw === false) {
                return [
                    'ok' => false,
                    'http_status' => null,
                    'error' => $error !== '' ? $error : 'curl_error',
                    'body' => '',
                    'headers' => [],
                ];
            }

            $headerRaw = substr((string) $raw, 0, $headerSize);
            $bodyRaw = substr((string) $raw, $headerSize);
            $headerLines = preg_split('/\r\n|\r|\n/', (string) $headerRaw) ?: [];

            return [
                'ok' => $status >= 200 && $status < 300,
                'http_status' => $status,
                'error' => $status >= 200 && $status < 300 ? null : 'http_' . $status,
                'body' => $bodyRaw,
                'headers' => $headerLines,
            ];
        }

        return [
            'ok' => false,
            'http_status' => null,
            'error' => 'curl_not_available',
            'body' => '',
            'headers' => [],
        ];
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function config(): ?\System\Engine\Config
    {
        $config = $this->registry->get('config');
        return $config instanceof \System\Engine\Config ? $config : null;
    }
}
