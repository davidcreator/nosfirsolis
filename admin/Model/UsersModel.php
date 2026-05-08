<?php

namespace Admin\Model;

class UsersModel extends AbstractCrudModel
{
    protected string $table = 'users';
    protected array $fillable = [
        'user_group_id',
        'name',
        'email',
        'recovery_email',
        'password_hash',
        'avatar',
        'status',
    ];

    public function existsByEmail(string $email): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        return is_array($row) && $row !== [];
    }

    public function allWithGroup(): array
    {
        $sql = 'SELECT u.*, ug.name AS group_name, ug.hierarchy_level AS group_hierarchy_level
                FROM users u
                LEFT JOIN user_groups ug ON ug.id = u.user_group_id
                ORDER BY u.id DESC';

        return $this->db->fetchAll($sql);
    }

    public function findWithGroup(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->db->fetch(
            'SELECT u.*, ug.name AS group_name, ug.hierarchy_level AS group_hierarchy_level
             FROM users u
             LEFT JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.id = :user_id
             LIMIT 1',
            ['user_id' => $userId]
        );
    }
}
