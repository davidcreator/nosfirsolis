<?php

namespace Admin\Model;

class SettingsModel extends AbstractCrudModel
{
    protected string $table = 'settings';
    protected array $fillable = ['key_name', 'value_text', 'autoload', 'status'];

    public function getValue(string $key, ?string $default = null): ?string
    {
        $row = $this->db->fetch('SELECT value_text FROM settings WHERE key_name = :key LIMIT 1', ['key' => $key]);

        return $row['value_text'] ?? $default;
    }

    public function setValue(string $key, string $value): void
    {
        $row = $this->db->fetch('SELECT id FROM settings WHERE key_name = :key LIMIT 1', ['key' => $key]);

        if ($row) {
            $this->db->update('settings', [
                'value_text' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $row['id']]);
            return;
        }

        $this->db->insert('settings', [
            'key_name' => $key,
            'value_text' => $value,
            'autoload' => 1,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
