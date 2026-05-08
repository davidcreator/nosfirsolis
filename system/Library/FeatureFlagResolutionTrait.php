<?php

namespace System\Library;

trait FeatureFlagResolutionTrait
{
    public function isEnabled(string $flagKey, ?array $user = null, ?string $area = null): bool
    {
        if (!$this->db()?->connected()) {
            return true;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return true;
        }
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
}
