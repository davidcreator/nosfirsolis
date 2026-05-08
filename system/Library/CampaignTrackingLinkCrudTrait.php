<?php

namespace System\Library;

trait CampaignTrackingLinkCrudTrait
{
    public function createTrackedLink(int $userId, array $data): ?array
    {
        if (!$this->db()?->connected() || $userId <= 0) {
            return null;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return null;
        }
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

        $timestamp = $this->clockDateTimeNow();
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
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findById($insertId, $userId);
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
        if (!$this->schemaAvailable) {
            return $summary;
        }

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
        if (!$this->schemaAvailable) {
            return null;
        }
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

        $timestamp = $this->clockDateTimeNow();
        $this->db()->update('campaign_tracking_links', [
            'clicks' => (int) ($row['clicks'] ?? 0) + 1,
            'last_clicked_at' => $timestamp,
            'updated_at' => $timestamp,
        ], 'id = :id', ['id' => (int) $row['id']]);

        return (string) ($row['tracking_url'] ?? '');
    }

    public function archiveById(int $id, int $userId): void
    {
        if (!$this->db()?->connected() || $id <= 0 || $userId <= 0) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db()->update('campaign_tracking_links', [
            'status' => 'archived',
            'updated_at' => $this->clockDateTimeNow(),
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
}
