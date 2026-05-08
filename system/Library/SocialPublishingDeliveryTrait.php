<?php

namespace System\Library;

trait SocialPublishingDeliveryTrait
{
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
        if (!$this->schemaAvailable) {
            return $result;
        }

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
            $providerPostId = 'mock-' . $publicationId . '-' . $this->clockFormat('YmdHis');
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
                $providerPostId = 'provider-' . $publicationId . '-' . $this->clockFormat('YmdHis');
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
        if ($token === null || $token === '') {
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

        $linkedInVersion = trim((string) $this->config()?->get('integrations.social_publisher.linkedin_version', $this->clockFormat('Ym')));
        if (!preg_match('/^\d{6}$/', $linkedInVersion)) {
            $linkedInVersion = $this->clockFormat('Ym');
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
        $timestamp = $this->clockDateTimeNow();
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
                'published_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'updated_at' => $this->clockDateTimeNow(),
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
            'created_at' => $this->clockDateTimeNow(),
        ]);
    }

    private function decryptToken(string $encrypted): ?string
    {
        $cipher = new TokenCipher(
            (array) $this->config()?->get('security', []),
            (array) $this->config()?->get('app', [])
        );

        $decrypted = $cipher->decrypt($encrypted);
        if (!is_string($decrypted)) {
            return null;
        }

        $decrypted = trim($decrypted);
        return $decrypted !== '' ? $decrypted : null;
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
}
