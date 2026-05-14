<?php

namespace Admin\Model;

use System\Engine\Model;

class PlansCampaignsModel extends Model
{
    public function summary(): array
    {
        if (!$this->db->connected()) {
            return [
                'campaigns_total' => 0,
                'campaigns_active' => 0,
                'plans_total' => 0,
                'plans_active' => 0,
                'plan_items_total' => 0,
                'clients_with_plans' => 0,
            ];
        }

        $campaignsTotal = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM campaigns')['total'] ?? 0));
        $campaignsActive = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM campaigns WHERE status = \'active\'')['total'] ?? 0));
        $plansTotal = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_plans')['total'] ?? 0));
        $plansActive = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_plans WHERE status = \'active\'')['total'] ?? 0));
        $planItemsTotal = (int) (($this->db->fetch('SELECT COUNT(*) AS total FROM content_plan_items')['total'] ?? 0));
        $clientsWithPlans = (int) (($this->db->fetch('SELECT COUNT(DISTINCT user_id) AS total FROM content_plans')['total'] ?? 0));

        return [
            'campaigns_total' => $campaignsTotal,
            'campaigns_active' => $campaignsActive,
            'plans_total' => $plansTotal,
            'plans_active' => $plansActive,
            'plan_items_total' => $planItemsTotal,
            'clients_with_plans' => $clientsWithPlans,
        ];
    }

    public function campaignOptions(): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, name
             FROM campaigns
             ORDER BY name ASC'
        );
    }

    public function campaignsCount(array $filters = []): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        [$whereSql, $params] = $this->buildCampaignsWhere($filters);
        $sql = 'SELECT COUNT(*) AS total FROM campaigns c';
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $row = $this->db->fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    public function campaignsWithUsage(int $limit = 200, array $filters = [], int $offset = 0): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $limit = max(5, min(500, $limit));
        $offset = max(0, $offset);
        [$whereSql, $params] = $this->buildCampaignsWhere($filters);

        $sql = 'SELECT c.*,
                       COUNT(DISTINCT cp.id) AS plans_count,
                       COUNT(cpi.id) AS items_count
                FROM campaigns c
                LEFT JOIN content_plans cp ON cp.campaign_id = c.id
                LEFT JOIN content_plan_items cpi ON cpi.content_plan_id = cp.id';

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= '
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function plansCount(array $filters = []): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        [$whereSql, $params] = $this->buildPlansWhere($filters);
        $sql = 'SELECT COUNT(DISTINCT cp.id) AS total
                FROM content_plans cp
                LEFT JOIN users u ON u.id = cp.user_id
                LEFT JOIN campaigns c ON c.id = cp.campaign_id';

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $row = $this->db->fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    public function plansWithUsage(int $limit = 250, array $filters = [], int $offset = 0): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $limit = max(5, min(600, $limit));
        $offset = max(0, $offset);
        [$whereSql, $params] = $this->buildPlansWhere($filters);

        $sql = 'SELECT cp.*,
                       u.name AS user_name,
                       u.email AS user_email,
                       c.name AS campaign_name,
                       COUNT(cpi.id) AS total_items,
                       SUM(CASE WHEN cpi.status = \'published\' THEN 1 ELSE 0 END) AS published_items
                FROM content_plans cp
                LEFT JOIN users u ON u.id = cp.user_id
                LEFT JOIN campaigns c ON c.id = cp.campaign_id
                LEFT JOIN content_plan_items cpi ON cpi.content_plan_id = cp.id';

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= '
                GROUP BY cp.id
                ORDER BY cp.id DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function usersForAiAssignmentsCount(array $filters = []): int
    {
        if (!$this->db->connected()) {
            return 0;
        }

        [$whereSql, $params] = $this->buildUsersWhere($filters);
        $sql = 'SELECT COUNT(*) AS total
                FROM users u
                LEFT JOIN settings s ON s.key_name = CONCAT(\'plans_ai_user_\', u.id)';
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $row = $this->db->fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    public function usersForAiAssignments(int $limit = 300, array $filters = [], int $offset = 0): array
    {
        if (!$this->db->connected()) {
            return [];
        }

        $limit = max(5, min(800, $limit));
        $offset = max(0, $offset);
        [$whereSql, $params] = $this->buildUsersWhere($filters);

        $sql = 'SELECT u.id,
                       MAX(u.name) AS name,
                       MAX(u.email) AS email,
                       MAX(u.status) AS status,
                       MAX(COALESCE(s.value_text, \'\')) AS assigned_manager_id,
                       MAX(CASE WHEN s.id IS NULL THEN 0 ELSE 1 END) AS has_custom_manager,
                       COUNT(cp.id) AS plans_count,
                       SUM(CASE WHEN cp.status = \'active\' THEN 1 ELSE 0 END) AS active_plans_count,
                       COUNT(DISTINCT CASE WHEN cp.campaign_id IS NOT NULL THEN cp.campaign_id END) AS campaigns_count,
                       MAX(cp.updated_at) AS last_plan_update
                FROM users u
                LEFT JOIN settings s ON s.key_name = CONCAT(\'plans_ai_user_\', u.id)
                LEFT JOIN content_plans cp ON cp.user_id = u.id';

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= '
                GROUP BY u.id
                ORDER BY status DESC, name ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function updateCampaignGovernance(int $campaignId, array $data): bool
    {
        if ($campaignId <= 0 || !$this->db->connected()) {
            return false;
        }

        $exists = $this->db->fetch('SELECT id FROM campaigns WHERE id = :id LIMIT 1', ['id' => $campaignId]);
        if (!$exists) {
            return false;
        }

        $payload = [
            'status' => (string) ($data['status'] ?? 'planned'),
            'objective' => $this->normalizeNullableText((string) ($data['objective'] ?? ''), 160),
            'start_date' => $this->normalizeNullableDate((string) ($data['start_date'] ?? '')),
            'end_date' => $this->normalizeNullableDate((string) ($data['end_date'] ?? '')),
            'updated_at' => $this->modelClockDateTimeNow(),
        ];

        $this->db->update('campaigns', $payload, 'id = :id', ['id' => $campaignId]);
        return true;
    }

    public function updatePlanGovernance(int $planId, array $data): bool
    {
        if ($planId <= 0 || !$this->db->connected()) {
            return false;
        }

        $exists = $this->db->fetch('SELECT id FROM content_plans WHERE id = :id LIMIT 1', ['id' => $planId]);
        if (!$exists) {
            return false;
        }

        $campaignId = isset($data['campaign_id']) && (int) $data['campaign_id'] > 0
            ? (int) $data['campaign_id']
            : null;

        $payload = [
            'status' => (string) ($data['status'] ?? 'draft'),
            'campaign_id' => $campaignId,
            'notes' => $this->normalizeNullableText((string) ($data['notes'] ?? ''), 2000),
            'updated_at' => $this->modelClockDateTimeNow(),
        ];

        $this->db->update('content_plans', $payload, 'id = :id', ['id' => $planId]);
        return true;
    }

    private function normalizeNullableDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);

        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function normalizeNullableText(string $value, int $maxLength): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, max(1, $maxLength));
    }

    private function buildCampaignsWhere(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $search = trim((string) ($filters['q'] ?? ''));

        $where = [];
        $params = [];

        if (in_array($status, ['planned', 'active', 'completed', 'archived'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }

        if ($search !== '') {
            $where[] = '(c.name LIKE :search OR COALESCE(c.objective, \'\') LIKE :search OR COALESCE(c.description, \'\') LIKE :search)';
            $params['search'] = '%' . mb_substr($search, 0, 120) . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildPlansWhere(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $search = trim((string) ($filters['q'] ?? ''));
        $campaignId = (int) ($filters['campaign_id'] ?? 0);

        $where = [];
        $params = [];

        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $where[] = 'cp.status = :status';
            $params['status'] = $status;
        }

        if ($campaignId > 0) {
            $where[] = 'cp.campaign_id = :campaign_id';
            $params['campaign_id'] = $campaignId;
        }

        if ($search !== '') {
            $where[] = '(cp.name LIKE :search OR COALESCE(cp.notes, \'\') LIKE :search OR COALESCE(u.name, \'\') LIKE :search OR COALESCE(u.email, \'\') LIKE :search OR COALESCE(c.name, \'\') LIKE :search)';
            $params['search'] = '%' . mb_substr($search, 0, 120) . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildUsersWhere(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $search = trim((string) ($filters['q'] ?? ''));
        $source = strtolower(trim((string) ($filters['source'] ?? 'all')));

        $where = [];
        $params = [];

        if ($status === 'active') {
            $where[] = 'u.status = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.status = 0';
        }

        if ($search !== '') {
            $where[] = '(u.name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . mb_substr($search, 0, 120) . '%';
        }

        if ($source === 'custom') {
            $where[] = 's.id IS NOT NULL';
        } elseif ($source === 'default') {
            $where[] = 's.id IS NULL';
        }

        return [implode(' AND ', $where), $params];
    }
}
