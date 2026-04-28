<?php

namespace System\Library;

use System\Engine\Registry;

class CampaignTrackingService
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
            'CREATE TABLE IF NOT EXISTS campaign_tracking_links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                campaign_id INT UNSIGNED NULL,
                plan_item_id INT UNSIGNED NULL,
                channel_slug VARCHAR(80) NULL,
                destination_url VARCHAR(1000) NOT NULL,
                tracking_url VARCHAR(1600) NOT NULL,
                short_code VARCHAR(24) NOT NULL UNIQUE,
                short_url VARCHAR(1000) NOT NULL,
                external_short_url VARCHAR(1000) NULL,
                short_provider VARCHAR(40) NOT NULL DEFAULT \'internal\',
                utm_source VARCHAR(120) NULL,
                utm_medium VARCHAR(120) NULL,
                utm_campaign VARCHAR(160) NULL,
                utm_content VARCHAR(160) NULL,
                utm_term VARCHAR(160) NULL,
                mtm_campaign VARCHAR(160) NULL,
                mtm_keyword VARCHAR(160) NULL,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                last_clicked_at DATETIME NULL,
                status ENUM(\'active\', \'archived\') NOT NULL DEFAULT \'active\',
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_tracking_user (user_id, created_at),
                INDEX idx_tracking_campaign (campaign_id),
                INDEX idx_tracking_item (plan_item_id),
                CONSTRAINT fk_tracking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_tracking_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_plan_item FOREIGN KEY (plan_item_id) REFERENCES content_plan_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    public function createTrackedLink(int $userId, array $data): ?array
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return null;
        }

        $this->ensureTables();
        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_tracking_links');
        if (empty($feature['allowed'])) {
            return null;
        }

        $quota = $subscription->evaluateQuota($userId, 'max_tracking_links_per_month', 1);
        if (empty($quota['allowed'])) {
            return null;
        }

        $destination = trim((string) ($data['destination_url'] ?? ''));
        if (!$this->isValidUrl($destination)) {
            return null;
        }

        $utmSource = $this->normalizeText($data['utm_source'] ?? '');
        $utmMedium = $this->normalizeText($data['utm_medium'] ?? '');
        $utmCampaign = $this->normalizeText($data['utm_campaign'] ?? '');
        $utmContent = $this->normalizeText($data['utm_content'] ?? '');
        $utmTerm = $this->normalizeText($data['utm_term'] ?? '');
        $mtmCampaign = $this->normalizeText($data['mtm_campaign'] ?? '');
        $mtmKeyword = $this->normalizeText($data['mtm_keyword'] ?? '');

        $trackingUrl = $this->appendQueryParams($destination, array_filter([
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'mtm_campaign' => $mtmCampaign,
            'mtm_keyword' => $mtmKeyword,
        ], static fn ($value): bool => $value !== null && $value !== ''));

        $shortCode = $this->generateShortCode();
        $shortUrl = $this->buildInternalShortUrl($shortCode);

        $externalShortUrl = null;
        $shortProvider = 'internal';
        $bitly = $this->shortenWithBitly($trackingUrl);
        if ($bitly !== null) {
            $externalShortUrl = $bitly;
            $shortProvider = 'bitly';
        }

        $insertId = $this->db()->insert('campaign_tracking_links', [
            'user_id' => $userId,
            'campaign_id' => !empty($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            'plan_item_id' => !empty($data['plan_item_id']) ? (int) $data['plan_item_id'] : null,
            'channel_slug' => $this->normalizeText($data['channel_slug'] ?? ''),
            'destination_url' => mb_substr($destination, 0, 1000),
            'tracking_url' => mb_substr($trackingUrl, 0, 1600),
            'short_code' => $shortCode,
            'short_url' => mb_substr($shortUrl, 0, 1000),
            'external_short_url' => $externalShortUrl !== null ? mb_substr($externalShortUrl, 0, 1000) : null,
            'short_provider' => $shortProvider,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'mtm_campaign' => $mtmCampaign,
            'mtm_keyword' => $mtmKeyword,
            'status' => 'active',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findById($insertId, $userId);
    }

    public function listByUser(int $userId, int $limit = 100): array
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return [];
        }

        $this->ensureTables();
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT ctl.*, c.name AS campaign_name, cpi.title AS plan_item_title
             FROM campaign_tracking_links ctl
             LEFT JOIN campaigns c ON c.id = ctl.campaign_id
             LEFT JOIN content_plan_items cpi ON cpi.id = ctl.plan_item_id
             WHERE ctl.user_id = :user_id
             ORDER BY ctl.id DESC
             LIMIT ' . $limit,
            ['user_id' => $userId]
        );
    }

    public function summaryByUser(int $userId): array
    {
        $summary = [
            'total_links' => 0,
            'total_clicks' => 0,
            'top_campaigns' => [],
            'top_channels' => [],
        ];

        if (!$this->db()?->connected() || $userId <= 0) {
            return $summary;
        }

        $this->ensureTables();

        $totals = $this->db()->fetch(
            'SELECT COUNT(*) AS total_links, COALESCE(SUM(clicks), 0) AS total_clicks
             FROM campaign_tracking_links
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        $summary['total_links'] = (int) ($totals['total_links'] ?? 0);
        $summary['total_clicks'] = (int) ($totals['total_clicks'] ?? 0);

        $summary['top_campaigns'] = $this->db()->fetchAll(
            'SELECT COALESCE(c.name, \'Sem campanha\') AS label, SUM(ctl.clicks) AS total_clicks
             FROM campaign_tracking_links ctl
             LEFT JOIN campaigns c ON c.id = ctl.campaign_id
             WHERE ctl.user_id = :user_id
             GROUP BY label
             ORDER BY total_clicks DESC
             LIMIT 5',
            ['user_id' => $userId]
        );

        $summary['top_channels'] = $this->db()->fetchAll(
            'SELECT COALESCE(NULLIF(channel_slug, \'\'), \'indefinido\') AS label, SUM(clicks) AS total_clicks
             FROM campaign_tracking_links
             WHERE user_id = :user_id
             GROUP BY label
             ORDER BY total_clicks DESC
             LIMIT 5',
            ['user_id' => $userId]
        );

        return $summary;
    }

    public function resolveRedirect(string $shortCode): ?string
    {
        if (!$this->db()?->connected()) {
            return null;
        }

        $this->ensureTables();
        $shortCode = trim($shortCode);
        if ($shortCode === '') {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT id, tracking_url, status, clicks
             FROM campaign_tracking_links
             WHERE short_code = :short_code
             LIMIT 1',
            ['short_code' => $shortCode]
        );
        if (!$row || (string) ($row['status'] ?? 'active') !== 'active') {
            return null;
        }

        $this->db()->update('campaign_tracking_links', [
            'clicks' => (int) ($row['clicks'] ?? 0) + 1,
            'last_clicked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $row['id']]);

        return (string) ($row['tracking_url'] ?? '');
    }

    public function archiveById(int $id, int $userId): void
    {
        if (!$this->db()?->connected() || $id <= 0 || $userId <= 0) {
            return;
        }

        $this->ensureTables();
        $this->db()->update('campaign_tracking_links', [
            'status' => 'archived',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND user_id = :user_id', [
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    public function availableCampaigns(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT id, name
             FROM campaigns
             ORDER BY name ASC'
        );
    }

    public function availablePlanItems(int $userId, int $limit = 100): array
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return [];
        }

        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT cpi.id, cpi.title, cpi.planned_date, cp.name AS plan_name
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cp.user_id = :user_id
             ORDER BY cpi.id DESC
             LIMIT ' . $limit,
            ['user_id' => $userId]
        );
    }

    private function findById(int $id, int $userId): ?array
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM campaign_tracking_links
             WHERE id = :id AND user_id = :user_id
             LIMIT 1',
            [
                'id' => $id,
                'user_id' => $userId,
            ]
        );
    }

    private function generateShortCode(): string
    {
        $alphabet = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < 8; $i++) {
                $candidate .= $alphabet[random_int(0, $max)];
            }

            $existing = $this->db()->fetch(
                'SELECT id FROM campaign_tracking_links WHERE short_code = :short_code LIMIT 1',
                ['short_code' => $candidate]
            );
            if (!$existing) {
                return $candidate;
            }
        }

        return bin2hex(random_bytes(6));
    }

    private function appendQueryParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $currentParams = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $currentParams);
        }
        foreach ($params as $key => $value) {
            $currentParams[$key] = $value;
        }
        $parts['query'] = http_build_query($currentParams);

        $result = '';
        if (!empty($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }
        if (!empty($parts['user'])) {
            $result .= $parts['user'];
            if (!empty($parts['pass'])) {
                $result .= ':' . $parts['pass'];
            }
            $result .= '@';
        }
        if (!empty($parts['host'])) {
            $result .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $result .= ':' . $parts['port'];
        }
        $result .= $parts['path'] ?? '';
        if (!empty($parts['query'])) {
            $result .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $result .= '#' . $parts['fragment'];
        }

        return $result !== '' ? $result : $url;
    }

    private function buildInternalShortUrl(string $shortCode): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = HostGuard::effectiveHost(
            $_SERVER,
            (array) $this->config()?->get('security.allowed_hosts', []),
            (string) $this->config()?->get('app.base_url', '')
        );

        return $scheme . '://' . $host . route_url('tracking/redirect/' . rawurlencode($shortCode));
    }

    private function shortenWithBitly(string $url): ?string
    {
        $token = trim((string) $this->config()?->get('integrations.tracking.bitly_access_token', ''));
        if ($token === '') {
            return null;
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init('https://api-ssl.bitly.com/v4/shorten');
        $payload = json_encode(['long_url' => $url], JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $bitlink = trim((string) ($decoded['link'] ?? ''));
        if (!$this->isValidUrl($bitlink)) {
            return null;
        }

        return $bitlink;
    }

    private function isValidUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 160);
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
