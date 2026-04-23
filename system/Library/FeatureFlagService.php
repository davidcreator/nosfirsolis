<?php

namespace System\Library;

use System\Engine\Registry;

class FeatureFlagService
{
    private bool $ensured = false;
    private bool $hierarchyColumnChecked = false;
    private bool $hierarchyColumnAvailable = false;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS feature_flags (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                flag_key VARCHAR(120) NOT NULL UNIQUE,
                label VARCHAR(180) NOT NULL,
                description TEXT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                target_area ENUM(\'all\', \'admin\', \'client\') NOT NULL DEFAULT \'all\',
                rollout_strategy ENUM(\'all\', \'admins_only\', \'clients_only\', \'min_hierarchy\', \'permission\') NOT NULL DEFAULT \'all\',
                min_hierarchy_level INT UNSIGNED NULL,
                required_permission VARCHAR(160) NULL,
                payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_feature_flags_area (enabled, target_area)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        foreach ($this->defaultFlags() as $flag) {
            $existing = $this->db()->fetch(
                'SELECT id FROM feature_flags WHERE flag_key = :flag_key LIMIT 1',
                ['flag_key' => $flag['flag_key']]
            );

            if ($existing) {
                continue;
            }

            $this->db()->insert('feature_flags', [
                'flag_key' => $flag['flag_key'],
                'label' => $flag['label'],
                'description' => $flag['description'],
                'enabled' => (int) $flag['enabled'],
                'target_area' => $flag['target_area'],
                'rollout_strategy' => $flag['rollout_strategy'],
                'min_hierarchy_level' => $flag['min_hierarchy_level'],
                'required_permission' => $flag['required_permission'],
                'payload_json' => json_encode($flag['payload'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->ensured = true;
    }

    public function all(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();

        return $this->db()->fetchAll(
            'SELECT *
             FROM feature_flags
             ORDER BY flag_key ASC'
        );
    }

    public function save(array $data): int
    {
        if (!$this->db()?->connected()) {
            return 0;
        }

        $this->ensureTables();

        $flagKey = strtolower(trim((string) ($data['flag_key'] ?? '')));
        if ($flagKey === '') {
            return 0;
        }

        $flagKey = preg_replace('/[^a-z0-9_.-]/', '', $flagKey) ?: '';
        if ($flagKey === '') {
            return 0;
        }

        $targetArea = strtolower(trim((string) ($data['target_area'] ?? 'all')));
        if (!in_array($targetArea, ['all', 'admin', 'client'], true)) {
            $targetArea = 'all';
        }

        $rollout = strtolower(trim((string) ($data['rollout_strategy'] ?? 'all')));
        $allowedRollout = ['all', 'admins_only', 'clients_only', 'min_hierarchy', 'permission'];
        if (!in_array($rollout, $allowedRollout, true)) {
            $rollout = 'all';
        }

        $minHierarchy = trim((string) ($data['min_hierarchy_level'] ?? ''));
        $minHierarchyValue = null;
        if ($rollout === 'min_hierarchy' && ctype_digit($minHierarchy)) {
            $minHierarchyValue = max(1, min(999, (int) $minHierarchy));
        }

        $requiredPermission = trim((string) ($data['required_permission'] ?? ''));
        if ($rollout !== 'permission') {
            $requiredPermission = '';
        }

        $payload = $data['payload'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        $row = $this->db()->fetch(
            'SELECT id FROM feature_flags WHERE flag_key = :flag_key LIMIT 1',
            ['flag_key' => $flagKey]
        );

        $normalized = [
            'label' => trim((string) ($data['label'] ?? $flagKey)),
            'description' => trim((string) ($data['description'] ?? '')),
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'target_area' => $targetArea,
            'rollout_strategy' => $rollout,
            'min_hierarchy_level' => $minHierarchyValue,
            'required_permission' => $requiredPermission !== '' ? $requiredPermission : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($row) {
            $this->db()->update('feature_flags', $normalized, 'id = :id', ['id' => (int) $row['id']]);
            return (int) $row['id'];
        }

        return $this->db()->insert('feature_flags', array_merge($normalized, [
            'flag_key' => $flagKey,
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function delete(int $id): void
    {
        if (!$this->db()?->connected() || $id <= 0) {
            return;
        }

        $this->ensureTables();
        $this->db()->delete('feature_flags', 'id = :id', ['id' => $id]);
    }

    public function isEnabled(string $flagKey, ?array $user = null, ?string $area = null): bool
    {
        if (!$this->db()?->connected()) {
            return true;
        }

        $this->ensureTables();
        $flagKey = strtolower(trim($flagKey));
        if ($flagKey === '') {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT *
             FROM feature_flags
             WHERE flag_key = :flag_key
             LIMIT 1',
            ['flag_key' => $flagKey]
        );

        if (!$row) {
            return true;
        }

        if ((int) ($row['enabled'] ?? 0) !== 1) {
            return false;
        }

        $runtimeArea = $area !== null && $area !== '' ? strtolower($area) : strtolower((string) (defined('AREA') ? AREA : 'client'));
        $targetArea = strtolower((string) ($row['target_area'] ?? 'all'));
        if ($targetArea !== 'all' && $targetArea !== $runtimeArea) {
            return false;
        }

        $strategy = strtolower((string) ($row['rollout_strategy'] ?? 'all'));
        return $this->allowByStrategy($strategy, $row, $user, $runtimeArea);
    }

    public function resolvedMap(?array $user = null, ?string $area = null): array
    {
        $map = [];
        foreach ($this->all() as $row) {
            $key = (string) ($row['flag_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $map[$key] = $this->isEnabled($key, $user, $area);
        }

        return $map;
    }

    private function allowByStrategy(string $strategy, array $row, ?array $user, string $area): bool
    {
        if ($strategy === 'all') {
            return true;
        }

        if ($strategy === 'admins_only') {
            $permissions = $this->userPermissions($user);
            return $this->hasAnyPermission($permissions, ['*', 'admin.*']) || $area === 'admin';
        }

        if ($strategy === 'clients_only') {
            $permissions = $this->userPermissions($user);
            return $this->hasAnyPermission($permissions, ['*', 'client.*']) || $area === 'client';
        }

        if ($strategy === 'permission') {
            $permissions = $this->userPermissions($user);
            $requiredPermission = trim((string) ($row['required_permission'] ?? ''));
            if ($requiredPermission === '') {
                return false;
            }

            if (in_array('*', $permissions, true)) {
                return true;
            }

            if (in_array($requiredPermission, $permissions, true)) {
                return true;
            }

            $requiredAreaPrefix = strtok($requiredPermission, '.') ?: '';
            if ($requiredAreaPrefix !== '' && in_array($requiredAreaPrefix . '.*', $permissions, true)) {
                return true;
            }

            return false;
        }

        if ($strategy === 'min_hierarchy') {
            $hierarchy = $this->userHierarchyLevel($user);
            if ($hierarchy <= 0) {
                return false;
            }
            $limit = (int) ($row['min_hierarchy_level'] ?? 0);
            if ($limit <= 0) {
                return false;
            }

            return $hierarchy <= $limit;
        }

        return false;
    }

    private function userPermissions(?array $user): array
    {
        if (!is_array($user)) {
            return [];
        }

        $raw = $user['permissions_json'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        $permissions = [];
        foreach ($raw as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            $permission = trim($permission);
            if ($permission === '') {
                continue;
            }
            $permissions[] = $permission;
        }

        return array_values(array_unique($permissions));
    }

    private function userHierarchyLevel(?array $user): int
    {
        if (!is_array($user)) {
            return 0;
        }

        if (isset($user['group_hierarchy_level'])) {
            return (int) $user['group_hierarchy_level'];
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || !$this->db()?->connected()) {
            return 0;
        }

        if (!$this->hasUserGroupHierarchyColumn()) {
            return 50;
        }

        $row = $this->db()->fetch(
            'SELECT ug.hierarchy_level
             FROM users u
             INNER JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.id = :user_id
             LIMIT 1',
            ['user_id' => $userId]
        );

        return (int) ($row['hierarchy_level'] ?? 0);
    }

    private function hasUserGroupHierarchyColumn(): bool
    {
        if ($this->hierarchyColumnChecked) {
            return $this->hierarchyColumnAvailable;
        }

        $this->hierarchyColumnChecked = true;

        if (!$this->db()?->connected()) {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'user_groups'
                   AND COLUMN_NAME = 'hierarchy_level'
                 LIMIT 1"
            );

            $this->hierarchyColumnAvailable = $row !== null;
        } catch (\Throwable) {
            $this->hierarchyColumnAvailable = false;
        }

        return $this->hierarchyColumnAvailable;
    }

    private function hasAnyPermission(array $permissions, array $expected): bool
    {
        foreach ($expected as $value) {
            if (in_array($value, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function defaultFlags(): array
    {
        return [
            [
                'flag_key' => 'social.publish_hub',
                'label' => 'Hub de Publicacao Social',
                'description' => 'Fila de publicacao multi-canal e despacho de posts.',
                'enabled' => 1,
                'target_area' => 'client',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => ['dry_run_default' => true],
            ],
            [
                'flag_key' => 'automation.webhooks',
                'label' => 'Automacoes por Webhook',
                'description' => 'Disparo de eventos operacionais para sistemas externos.',
                'enabled' => 1,
                'target_area' => 'all',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => [],
            ],
            [
                'flag_key' => 'tracking.campaign_links',
                'label' => 'Rastreamento de Campanhas',
                'description' => 'Gerador de URLs rastreaveis com short links e metricas.',
                'enabled' => 1,
                'target_area' => 'client',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => [],
            ],
            [
                'flag_key' => 'dashboard.executive',
                'label' => 'Dashboard Executivo',
                'description' => 'Indicadores executivos e consolidacao de operacao.',
                'enabled' => 1,
                'target_area' => 'client',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => [],
            ],
            [
                'flag_key' => 'observability.telemetry',
                'label' => 'Observabilidade',
                'description' => 'Logs estruturados, eventos e trilhas operacionais.',
                'enabled' => 1,
                'target_area' => 'all',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => [],
            ],
            [
                'flag_key' => 'jobs.monitoring',
                'label' => 'Monitoramento de Jobs',
                'description' => 'Check-ins de jobs e alertas de execucao.',
                'enabled' => 1,
                'target_area' => 'all',
                'rollout_strategy' => 'all',
                'min_hierarchy_level' => null,
                'required_permission' => null,
                'payload' => [],
            ],
            [
                'flag_key' => 'governance.feature_flags',
                'label' => 'Governanca de Feature Flags',
                'description' => 'Permite administrar feature flags no painel admin.',
                'enabled' => 1,
                'target_area' => 'admin',
                'rollout_strategy' => 'permission',
                'min_hierarchy_level' => null,
                'required_permission' => 'admin.operations',
                'payload' => [],
            ],
        ];
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }
}
