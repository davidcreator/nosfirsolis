<?php

namespace System\Library;

trait SecurityRuntimeTrait
{
    private function ensureTables(): void
    {
        if ($this->ensured) {
            return;
        }

        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return;
        }

        if (!$this->securityTablesExist()) {
            throw new \RuntimeException(
                'Schema de seguranca ausente. Execute a migracao operacional.'
            );
        }

        $this->ensured = true;
    }

    private function clientIp(): string
    {
        $server = $this->requestServerContext();
        $remoteAddr = (string) ($server['REMOTE_ADDR'] ?? '');
        $remoteAddr = filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';

        if (!$this->shouldTrustForwardedHeaders($remoteAddr)) {
            return $remoteAddr;
        }

        $cfIp = $this->firstValidIpFromHeader((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cfIp !== null) {
            return $cfIp;
        }

        $forwardedFor = $this->firstValidIpFromHeader((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== null) {
            return $forwardedFor;
        }

        return $remoteAddr;
    }

    private function userAgent(): string
    {
        $server = $this->requestServerContext();
        $agent = (string) ($server['HTTP_USER_AGENT'] ?? '');

        return mb_substr($agent, 0, 255);
    }

    private function requestServerContext(): array
    {
        $request = $this->registry->get('request');
        if (!is_object($request) || !isset($request->server) || !is_array($request->server)) {
            return [];
        }

        return $request->server;
    }

    private function securityTablesExist(): bool
    {
        if ($this->securityTablesAvailable !== null) {
            return $this->securityTablesAvailable;
        }

        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            $this->securityTablesAvailable = false;
            return false;
        }

        $this->securityTablesAvailable = $this->tableExists('security_login_attempts')
            && $this->tableExists('security_audit_logs');

        return $this->securityTablesAvailable;
    }

    private function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        if (preg_match('/^[a-z0-9_]+$/i', $table) !== 1) {
            return false;
        }

        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return false;
        }

        // PDO native prepared statements do not support placeholders in `SHOW TABLES LIKE`
        // for all MySQL/MariaDB configurations. Keep a validated literal table name here.
        $row = $db->fetch("SHOW TABLES LIKE '{$table}'");

        return is_array($row) && $row !== [];
    }

    private function shouldTrustForwardedHeaders(string $remoteAddr): bool
    {
        $config = $this->registry->get('config');
        $trusted = (array) ($config ? $config->get('security.trusted_proxies', []) : []);
        if (empty($trusted)) {
            return false;
        }

        foreach ($trusted as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            if ($this->ipMatchesRule($remoteAddr, $rule)) {
                return true;
            }
        }

        return false;
    }

    private function firstValidIpFromHeader(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        $parts = explode(',', $value);
        foreach ($parts as $part) {
            $ip = trim($part);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    private function ipMatchesRule(string $ip, string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return hash_equals($rule, $ip);
        }

        return $this->ipInCidr($ip, $rule);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefixRaw] = array_pad(explode('/', $cidr, 2), 2, '');
        $network = trim($network);
        if ($network === '' || $prefixRaw === '' || !ctype_digit($prefixRaw)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($network);
        if ($ipBin === false || $networkBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $prefix = (int) $prefixRaw;
        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($networkBin, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBin[$fullBytes]);
        $networkByte = ord($networkBin[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}
