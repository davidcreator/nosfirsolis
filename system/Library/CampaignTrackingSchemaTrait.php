<?php

namespace System\Library;

trait CampaignTrackingSchemaTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        if (!$this->tableExists('campaign_tracking_links')) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Tabela campaign_tracking_links ausente. '
                . 'Execute a migracao operacional.'
            );
        }

        $this->ensured = true;
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
