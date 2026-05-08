<?php

namespace System\Library;

trait FeatureFlagCrudTrait
{
    public function all(): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }

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
        if (!$this->schemaAvailable) {
            return 0;
        }

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

        $timestamp = $this->clockDateTimeNow();
        $normalized = [
            'label' => trim((string) ($data['label'] ?? $flagKey)),
            'description' => trim((string) ($data['description'] ?? '')),
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'target_area' => $targetArea,
            'rollout_strategy' => $rollout,
            'min_hierarchy_level' => $minHierarchyValue,
            'required_permission' => $requiredPermission !== '' ? $requiredPermission : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'updated_at' => $timestamp,
        ];

        if ($row) {
            $this->db()->update('feature_flags', $normalized, 'id = :id', ['id' => (int) $row['id']]);
            return (int) $row['id'];
        }

        return $this->db()->insert('feature_flags', array_merge($normalized, [
            'flag_key' => $flagKey,
            'created_at' => $timestamp,
        ]));
    }

    public function delete(int $id): void
    {
        if (!$this->db()?->connected() || $id <= 0) {
            return;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return;
        }
        $this->db()->delete('feature_flags', 'id = :id', ['id' => $id]);
    }
}
