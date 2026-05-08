<?php

namespace System\Library;

trait AutomationServiceSchemaAndValidationTrait
{
    public function ensureTables(): void
    {
        if ($this->ensured || !$this->db()?->connected()) {
            return;
        }

        $requiredTables = ['automations_webhooks', 'automation_dispatch_logs'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->schemaAvailable = false;
            error_log(
                '[Solis] Schema de automacoes ausente. Execute a migracao operacional. '
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

    private function isValidWebhookEndpoint(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '') {
            return false;
        }

        if ($this->allowPrivateWebhookEndpoints()) {
            return true;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $resolved = @gethostbynamel($host);
        if (!is_array($resolved) || empty($resolved)) {
            return false;
        }

        foreach ($resolved as $ip) {
            if (is_string($ip) && $this->isPublicIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private function allowPrivateWebhookEndpoints(): bool
    {
        $config = $this->registry->get('config');
        if (!is_object($config)) {
            return false;
        }

        return (bool) $config->get('security.automation.allow_private_webhook_endpoints', false);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
