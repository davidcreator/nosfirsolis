<?php

namespace Client\Model;

use System\Engine\Model;
use System\Library\AutomationService;
use System\Library\CampaignTrackingService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;
use System\Library\SocialPublishingService;

class DashboardModel extends Model
{
    public function overview(int $userId): array
    {
        $stats = [
            'plans_total' => 0,
            'items_total' => 0,
            'campaigns_active' => 0,
            'suggestions_total' => 0,
            'upcoming_items' => [],
            'executive' => [
                'tracking_links_total' => 0,
                'tracking_clicks_total' => 0,
                'publications_total' => 0,
                'publications_queued' => 0,
                'publications_published' => 0,
                'publications_failed' => 0,
                'webhooks_active' => 0,
                'job_alerts_open' => 0,
                'observability_errors_24h' => 0,
                'top_campaigns_clicks' => [],
            ],
        ];

        if (!$this->db->connected()) {
            return $stats;
        }

        $stats['plans_total'] = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_plans WHERE user_id = :user_id', ['user_id' => $userId])['total']) ?? 0);
        $stats['items_total'] = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_plan_items cpi INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id WHERE cp.user_id = :user_id', ['user_id' => $userId])['total']) ?? 0);
        $stats['campaigns_active'] = (int) (($this->db->fetch("SELECT COUNT(*) AS total FROM campaigns WHERE status = 'active'")['total']) ?? 0);
        $stats['suggestions_total'] = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_suggestions WHERE status = 1')['total']) ?? 0);

        $stats['upcoming_items'] = $this->db->fetchAll(
            'SELECT cpi.*, cp.name AS plan_name
             FROM content_plan_items cpi
             INNER JOIN content_plans cp ON cp.id = cpi.content_plan_id
             WHERE cp.user_id = :user_id
               AND cpi.planned_date >= CURDATE()
             ORDER BY cpi.planned_date ASC
             LIMIT 10',
            ['user_id' => $userId]
        );

        $tracking = new CampaignTrackingService($this->registry);
        $tracking->ensureTables();
        $pub = new SocialPublishingService($this->registry);
        $pub->ensureTables();
        $automation = new AutomationService($this->registry);
        $automation->ensureTables();
        $jobs = new JobMonitorService($this->registry);
        $jobs->ensureTables();
        $obs = new ObservabilityService($this->registry);
        $obs->ensureTables();

        $trackingTotals = $this->db->fetch(
            'SELECT COUNT(*) AS total_links, COALESCE(SUM(clicks), 0) AS total_clicks
             FROM campaign_tracking_links
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        $stats['executive']['tracking_links_total'] = (int) ($trackingTotals['total_links'] ?? 0);
        $stats['executive']['tracking_clicks_total'] = (int) ($trackingTotals['total_clicks'] ?? 0);

        $publicationTotals = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = \'queued\' THEN 1 ELSE 0 END) AS queued_total,
                    SUM(CASE WHEN status = \'published\' THEN 1 ELSE 0 END) AS published_total,
                    SUM(CASE WHEN status IN (\'failed\', \'manual_review\') THEN 1 ELSE 0 END) AS failed_total
             FROM social_publications
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        $stats['executive']['publications_total'] = (int) ($publicationTotals['total'] ?? 0);
        $stats['executive']['publications_queued'] = (int) ($publicationTotals['queued_total'] ?? 0);
        $stats['executive']['publications_published'] = (int) ($publicationTotals['published_total'] ?? 0);
        $stats['executive']['publications_failed'] = (int) ($publicationTotals['failed_total'] ?? 0);

        $webhooksActive = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM automations_webhooks
             WHERE enabled = 1'
        );
        $stats['executive']['webhooks_active'] = (int) ($webhooksActive['total'] ?? 0);

        $alertsOpen = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM job_alerts
             WHERE status = \'open\''
        );
        $stats['executive']['job_alerts_open'] = (int) ($alertsOpen['total'] ?? 0);

        $errors24h = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM observability_events
             WHERE level IN (\'error\', \'critical\')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
        $stats['executive']['observability_errors_24h'] = (int) ($errors24h['total'] ?? 0);

        $stats['executive']['top_campaigns_clicks'] = $this->db->fetchAll(
            'SELECT COALESCE(c.name, \'Sem campanha\') AS label, SUM(ctl.clicks) AS total_clicks
             FROM campaign_tracking_links ctl
             LEFT JOIN campaigns c ON c.id = ctl.campaign_id
             WHERE ctl.user_id = :user_id
             GROUP BY label
             ORDER BY total_clicks DESC
             LIMIT 5',
            ['user_id' => $userId]
        );

        return $stats;
    }
}
