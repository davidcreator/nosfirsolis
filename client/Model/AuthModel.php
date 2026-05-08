<?php

namespace Client\Model;

use System\Engine\Model;

class AuthModel extends Model
{
    public function databaseConnected(): bool
    {
        return $this->db->connected();
    }

    public function runInTransaction(callable $operation): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function userExistsByEmail(string $email): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        return is_array($row) && $row !== [];
    }

    public function createUser(array $payload): int
    {
        return $this->db->insert('users', $payload);
    }

    public function passwordResetTableExists(): bool
    {
        $row = $this->db->fetch(
            'SHOW TABLES LIKE :table_name',
            ['table_name' => 'password_resets']
        );

        return is_array($row) && $row !== [];
    }

    public function purgeExpiredPasswordResets(string $now): void
    {
        $this->db->execute(
            'DELETE FROM password_resets
             WHERE expires_at < :now
                OR (used_at IS NOT NULL AND updated_at < DATE_SUB(:now_two, INTERVAL 1 DAY))',
            [
                'now' => $now,
                'now_two' => $now,
            ]
        );
    }

    public function resolvePasswordRecoveryUser(string $email): ?array
    {
        $user = $this->db->fetch(
            'SELECT u.id, u.name, u.email, ug.permissions_json
             FROM users u
             LEFT JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.email = :email
               AND u.status = 1
             LIMIT 1',
            ['email' => $email]
        );

        if (!$user) {
            return null;
        }

        $permissionsJson = (string) ($user['permissions_json'] ?? '[]');
        if (!$this->hasClientAccessPermission($permissionsJson)) {
            return null;
        }

        return $user;
    }

    public function markOpenPasswordResetsAsUsed(int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE password_resets
             SET used_at = :used_at, updated_at = :updated_at
             WHERE user_id = :user_id
               AND used_at IS NULL',
            [
                'used_at' => $now,
                'updated_at' => $now,
                'user_id' => $userId,
            ]
        );
    }

    public function createPasswordReset(
        int $userId,
        string $email,
        string $tokenHash,
        string $expiresAt,
        string $now
    ): void {
        $this->db->insert('password_resets', [
            'user_id' => $userId,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findValidPasswordResetByTokenHash(string $tokenHash, string $now): ?array
    {
        return $this->db->fetch(
            'SELECT id, user_id, email, expires_at
             FROM password_resets
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at >= :now
             LIMIT 1',
            [
                'token_hash' => $tokenHash,
                'now' => $now,
            ]
        );
    }

    public function applyPasswordReset(int $userId, int $resetId, string $passwordHash, string $now): void
    {
        $this->db->beginTransaction();

        try {
            $rowsUpdated = $this->db->update(
                'users',
                [
                    'password_hash' => $passwordHash,
                    'updated_at' => $now,
                ],
                'id = :id AND status = 1',
                ['id' => $userId]
            );

            if ($rowsUpdated <= 0) {
                throw new \RuntimeException('Usuario nao encontrado para redefinicao.');
            }

            $this->db->update(
                'password_resets',
                [
                    'used_at' => $now,
                    'updated_at' => $now,
                ],
                'id = :id',
                ['id' => $resetId]
            );

            $this->db->execute(
                'UPDATE password_resets
                 SET used_at = :used_at, updated_at = :updated_at
                 WHERE user_id = :user_id
                   AND used_at IS NULL
                   AND id <> :id',
                [
                    'used_at' => $now,
                    'updated_at' => $now,
                    'user_id' => $userId,
                    'id' => $resetId,
                ]
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function resolveClientGroupId(): int
    {
        $group = $this->db->fetch(
            "SELECT id
             FROM user_groups
             WHERE name = 'Clientes'
             LIMIT 1"
        );
        if ($group) {
            return (int) ($group['id'] ?? 0);
        }

        $group = $this->db->fetch(
            "SELECT id
             FROM user_groups
             WHERE permissions_json LIKE '%client.%'
             ORDER BY hierarchy_level DESC, id ASC
             LIMIT 1"
        );

        return (int) ($group['id'] ?? 0);
    }

    private function hasClientAccessPermission(string $permissionsJson): bool
    {
        $permissions = json_decode($permissionsJson, true);
        if (!is_array($permissions)) {
            return false;
        }

        if (in_array('*', $permissions, true) || in_array('client.*', $permissions, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            if (str_starts_with($permission, 'client.')) {
                return true;
            }
        }

        return false;
    }
}
