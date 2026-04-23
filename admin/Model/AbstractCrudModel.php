<?php

namespace Admin\Model;

use System\Engine\Model;

abstract class AbstractCrudModel extends Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    public function all(array $filters = [], string $orderBy = 'id DESC'): array
    {
        $sql = 'SELECT * FROM `' . $this->table . '`';
        [$whereSql, $params] = $this->buildWhere($filters);

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= ' ORDER BY ' . $orderBy;

        return $this->db->fetchAll($sql, $params);
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM `' . $this->table . '`';
        [$whereSql, $params] = $this->buildWhere($filters);

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $row = $this->db->fetch($sql, $params);

        return (int) ($row['total'] ?? 0);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function create(array $data): int
    {
        $payload = $this->sanitizeData($data);
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->insert($this->table, $payload);
    }

    public function updateById(int $id, array $data): int
    {
        $payload = $this->sanitizeData($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update(
            $this->table,
            $payload,
            '`' . $this->primaryKey . '` = :id',
            ['id' => $id]
        );
    }

    public function deleteById(int $id): int
    {
        return $this->db->delete(
            $this->table,
            '`' . $this->primaryKey . '` = :id',
            ['id' => $id]
        );
    }

    public function options(string $labelField = 'name'): array
    {
        $sql = 'SELECT `' . $this->primaryKey . '` AS id, `' . $labelField . '` AS name FROM `' . $this->table . '` ORDER BY `' . $labelField . '` ASC';

        return $this->db->fetchAll($sql);
    }

    protected function sanitizeData(array $data): array
    {
        $payload = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        return $payload;
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $param = 'w_' . $field;
            $clauses[] = '`' . $field . '` = :' . $param;
            $params[$param] = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }
}
