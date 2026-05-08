<?php

namespace System\Library;

trait JobMonitorSchemaTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $requiredTables = ['job_monitors', 'job_checkins', 'job_alerts'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema de monitoramento de jobs ausente. Execute a migracao operacional. '
                . 'missing=' . implode(',', $missing)
            );
            $this->ensured = true;
            return;
        }

        $this->ensured = true;

        foreach ($this->defaultMonitors() as $monitor) {
            $this->upsertMonitor($monitor);
        }
    }

    private function defaultMonitors(): array
    {
        return [
            [
                'job_key' => 'plans.status_updates',
                'name' => 'Atualizacao de status de planos',
                'description' => 'Saude de atualizacao individual e em lote de itens de plano.',
                'expected_interval_minutes' => 240,
                'max_runtime_seconds' => 20,
                'enabled' => 1,
            ],
            [
                'job_key' => 'social.publisher_queue',
                'name' => 'Fila de publicacao social',
                'description' => 'Despacho de publicacoes do hub social.',
                'expected_interval_minutes' => 180,
                'max_runtime_seconds' => 30,
                'enabled' => 1,
            ],
            [
                'job_key' => 'automation.webhook_dispatch',
                'name' => 'Despacho de webhooks',
                'description' => 'Envio de eventos para automacoes externas.',
                'expected_interval_minutes' => 120,
                'max_runtime_seconds' => 30,
                'enabled' => 1,
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        $row = $this->db()?->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }
}
