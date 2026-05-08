<?php

namespace Client\Model;

use System\Library\TokenCipher;

trait SocialModelSchemaTrait
{
    private function ensureTables(): void
    {
        if ($this->ensured || !$this->db->connected()) {
            return;
        }

        $requiredTables = ['social_connections', 'social_content_drafts', 'social_format_presets'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema social ausente para SocialModel. Execute a migracao operacional. '
                . 'missing=' . implode(',', $missing)
            );
        }

        $this->ensured = true;
    }

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        $row = $this->db->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }

    private function cipher(): TokenCipher
    {
        $security = (array) $this->config->get('security', []);
        $app = (array) $this->config->get('app', []);

        return new TokenCipher($security, $app);
    }
}
