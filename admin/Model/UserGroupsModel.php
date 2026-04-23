<?php

namespace Admin\Model;

class UserGroupsModel extends AbstractCrudModel
{
    protected string $table = 'user_groups';
    protected array $fillable = [
        'name',
        'description',
        'hierarchy_level',
        'permissions_json',
        'status',
    ];

    private bool $hierarchyEnsured = false;

    public function ensureHierarchySchema(): void
    {
        if ($this->hierarchyEnsured || !$this->db->connected()) {
            return;
        }

        $column = $this->db->fetch("SHOW COLUMNS FROM user_groups LIKE 'hierarchy_level'");
        if (!$column) {
            $this->db->execute('ALTER TABLE user_groups ADD COLUMN hierarchy_level INT UNSIGNED NOT NULL DEFAULT 50 AFTER description');
        }

        $this->db->execute(
            "UPDATE user_groups
             SET hierarchy_level = 10
             WHERE LOWER(name) = 'administradores'
               AND hierarchy_level = 50"
        );

        $this->db->execute(
            "UPDATE user_groups
             SET hierarchy_level = 90
             WHERE LOWER(name) = 'clientes'
               AND hierarchy_level = 50"
        );

        $this->hierarchyEnsured = true;
    }

    public function allWithHierarchy(): array
    {
        $this->ensureHierarchySchema();

        return $this->db->fetchAll(
            'SELECT id, name, description, hierarchy_level, permissions_json, status, created_at, updated_at
             FROM user_groups
             ORDER BY hierarchy_level ASC, name ASC'
        );
    }

    public function optionsForHierarchy(int $currentLevel): array
    {
        $this->ensureHierarchySchema();
        $currentLevel = $this->normalizeLevel($currentLevel);

        return $this->db->fetchAll(
            'SELECT id, name, hierarchy_level
             FROM user_groups
             WHERE status = 1
               AND hierarchy_level >= :current_level
             ORDER BY hierarchy_level ASC, name ASC',
            ['current_level' => $currentLevel]
        );
    }

    public function hierarchyLevelByUser(int $userId): int
    {
        $this->ensureHierarchySchema();

        if ($userId <= 0) {
            return 50;
        }

        $row = $this->db->fetch(
            'SELECT ug.hierarchy_level
             FROM users u
             INNER JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.id = :user_id
             LIMIT 1',
            ['user_id' => $userId]
        );

        return $this->normalizeLevel((int) ($row['hierarchy_level'] ?? 50));
    }

    public function updateHierarchyLevel(int $groupId, int $level): int
    {
        $this->ensureHierarchySchema();

        return $this->updateById($groupId, [
            'hierarchy_level' => $this->normalizeLevel($level),
        ]);
    }

    public function options(string $labelField = 'name'): array
    {
        return $this->optionsForHierarchy(1);
    }

    private function normalizeLevel(int $level): int
    {
        return max(1, min(999, $level));
    }
}
