<?php

namespace System\Library;

trait SocialPublishingSchemaTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $requiredTables = ['social_publications', 'social_publication_logs'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema social publishing ausente. Execute a migracao operacional. '
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

        $row = $this->db()?->fetch("SHOW TABLES LIKE '{$table}'");
        return (bool) $row;
    }
}
