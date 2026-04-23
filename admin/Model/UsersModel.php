<?php

namespace Admin\Model;

class UsersModel extends AbstractCrudModel
{
    protected string $table = 'users';
    protected array $fillable = [
        'user_group_id',
        'name',
        'email',
        'password_hash',
        'avatar',
        'status',
    ];

    public function allWithGroup(): array
    {
        $sql = 'SELECT u.*, ug.name AS group_name, ug.hierarchy_level AS group_hierarchy_level
                FROM users u
                LEFT JOIN user_groups ug ON ug.id = u.user_group_id
                ORDER BY u.id DESC';

        return $this->db->fetchAll($sql);
    }
}
