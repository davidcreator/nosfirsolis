<?php

namespace System\Library;

use System\Engine\Registry;

class SecurityService
{
    private bool $ensured = false;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function canAttemptLogin(string $area, string $email): array
    {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return ['allowed' => true, 'retry_after' => 0, 'reason' => 'no_db', 'message' => ''];
        }

        try {
            $this->ensureTables();

            $authConfig = $this->authConfig();
            $windowMinutes = (int) ($authConfig['window_minutes'] ?? 15);
            $blockMinutes = (int) ($authConfig['block_minutes'] ?? 20);
            $maxPerIp = (int) ($authConfig['max_attempts_per_ip'] ?? 12);
            $maxPerUser = (int) ($authConfig['max_attempts_per_user'] ?? 6);

            $windowMinutes = max(1, $windowMinutes);
            $blockMinutes = max(1, $blockMinutes);
            $maxPerIp = max(1, $maxPerIp);
            $maxPerUser = max(1, $maxPerUser);

            $windowStart = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));
            $ip = $this->clientIp();

            $ipBlock = $this->blockRemainingSeconds(
                $area,
                'ip_address = :ip',
                ['ip' => $ip, 'window_start' => $windowStart],
                $maxPerIp,
                $blockMinutes
            );

            $normalizedEmail = strtolower(trim($email));
            $emailBlock = 0;
            if ($normalizedEmail !== '') {
                $emailBlock = $this->blockRemainingSeconds(
                    $area,
                    'email = :email',
                    ['email' => $normalizedEmail, 'window_start' => $windowStart],
                    $maxPerUser,
                    $blockMinutes
                );
            }

            $retryAfter = max($ipBlock, $emailBlock);
            if ($retryAfter > 0) {
                $minutes = (int) ceil($retryAfter / 60);
                return [
                    'allowed' => false,
                    'retry_after' => $retryAfter,
                    'reason' => $ipBlock >= $emailBlock ? 'ip' : 'email',
                    'message' => $this->t(
                        'common.auth_too_many_attempts',
                        'Muitas tentativas detectadas. Tente novamente em aproximadamente {minutes} minuto(s).',
                        ['minutes' => $minutes]
                    ),
                ];
            }
        } catch (\Throwable $exception) {
            return ['allowed' => true, 'retry_after' => 0, 'reason' => 'fail_open', 'message' => ''];
        }

        return ['allowed' => true, 'retry_after' => 0, 'reason' => 'ok', 'message' => ''];
    }

    public function registerLoginAttempt(
        string $area,
        string $email,
        bool $success,
        ?int $userId = null,
        string $reason = ''
    ): void {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return;
        }

        try {
            $this->ensureTables();
            $db->insert('security_login_attempts', [
                'area' => $area,
                'email' => strtolower(trim($email)),
                'ip_address' => $this->clientIp(),
                'user_agent' => $this->userAgent(),
                'success' => $success ? 1 : 0,
                'reason_code' => trim($reason),
                'user_id' => $userId,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            // fail open
        }
    }

    public function audit(
        string $eventType,
        string $severity = 'info',
        ?int $userId = null,
        string $area = 'client',
        array $payload = []
    ): void {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return;
        }

        try {
            $this->ensureTables();

            $allowedSeverities = ['info', 'warning', 'critical'];
            if (!in_array($severity, $allowedSeverities, true)) {
                $severity = 'info';
            }

            $db->insert('security_audit_logs', [
                'event_type' => $eventType,
                'severity' => $severity,
                'area' => $area,
                'user_id' => $userId,
                'ip_address' => $this->clientIp(),
                'user_agent' => $this->userAgent(),
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            // fail open
        }
    }

    public function recentEvents(int $userId, string $area = 'client', int $limit = 20): array
    {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return [];
        }

        try {
            $this->ensureTables();

            return $db->fetchAll(
                'SELECT event_type, severity, area, ip_address, created_at, payload_json
                 FROM security_audit_logs
                 WHERE user_id = :user_id AND area = :area
                 ORDER BY id DESC
                 LIMIT ' . max(1, min(100, $limit)),
                [
                    'user_id' => $userId,
                    'area' => $area,
                ]
            );
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function blockRemainingSeconds(
        string $area,
        string $whereFragment,
        array $params,
        int $limit,
        int $blockMinutes
    ): int {
        $db = $this->registry->get('db');
        $query = $db->fetch(
            'SELECT COUNT(*) AS total, MAX(attempted_at) AS last_failed
             FROM security_login_attempts
             WHERE area = :area
               AND success = 0
               AND attempted_at >= :window_start
               AND ' . $whereFragment,
            array_merge(['area' => $area], $params)
        );

        $count = (int) ($query['total'] ?? 0);
        $lastFailed = (string) ($query['last_failed'] ?? '');

        if ($count < $limit || $lastFailed === '') {
            return 0;
        }

        $blockedUntil = strtotime($lastFailed . ' +' . $blockMinutes . ' minutes');
        if ($blockedUntil === false) {
            return 0;
        }

        return max(0, $blockedUntil - time());
    }

    private function ensureTables(): void
    {
        if ($this->ensured) {
            return;
        }

        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return;
        }

        $db->execute(
            'CREATE TABLE IF NOT EXISTS security_login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                area VARCHAR(20) NOT NULL,
                email VARCHAR(190) NULL,
                ip_address VARCHAR(64) NOT NULL,
                user_agent VARCHAR(255) NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                reason_code VARCHAR(80) NULL,
                user_id INT UNSIGNED NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_security_login_ip (area, ip_address, attempted_at),
                INDEX idx_security_login_email (area, email, attempted_at),
                INDEX idx_security_login_user (user_id),
                CONSTRAINT fk_security_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->execute(
            'CREATE TABLE IF NOT EXISTS security_audit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(80) NOT NULL,
                severity ENUM(\'info\', \'warning\', \'critical\') NOT NULL DEFAULT \'info\',
                area VARCHAR(20) NOT NULL,
                user_id INT UNSIGNED NULL,
                ip_address VARCHAR(64) NOT NULL,
                user_agent VARCHAR(255) NULL,
                payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_security_audit_user (user_id, area, created_at),
                INDEX idx_security_audit_event (event_type, created_at),
                CONSTRAINT fk_security_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensured = true;
    }

    private function clientIp(): string
    {
        $request = $this->registry->get('request');
        $server = is_object($request) ? $request->server : $_SERVER;
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
        $request = $this->registry->get('request');
        $server = is_object($request) ? $request->server : $_SERVER;
        $agent = (string) ($server['HTTP_USER_AGENT'] ?? '');

        return mb_substr($agent, 0, 255);
    }

    private function authConfig(): array
    {
        $config = $this->registry->get('config');

        return (array) ($config ? $config->get('security.auth', []) : []);
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

    private function t(string $key, string $default, array $replacements = []): string
    {
        $language = $this->registry->get('language');
        $text = is_object($language) && method_exists($language, 'get')
            ? (string) $language->get($key, $default)
            : $default;

        if ($replacements === []) {
            return $text;
        }

        $tokens = [];
        foreach ($replacements as $name => $value) {
            $tokens['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $tokens);
    }
}
