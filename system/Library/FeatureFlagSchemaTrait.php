<?php

namespace System\Library;

trait FeatureFlagSchemaTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        if (!$this->tableExists('feature_flags')) {
            $this->schemaAvailable = false;
            error_log('[Solis] Tabela feature_flags ausente. Execute a migracao operacional.');
            $this->ensured = true;
            return;
        }

        $timestamp = $this->clockDateTimeNow();
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
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $this->ensured = true;
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

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        $row = $this->db()?->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }
}
