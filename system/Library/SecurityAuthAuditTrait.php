<?php

namespace System\Library;

trait SecurityAuthAuditTrait
{
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

            $nowTs = $this->clockUnixNow();
            $windowStart = $this->clockDateTimeFromUnix($nowTs - ($windowMinutes * 60));
            $ip = $this->clientIp();

            $ipBlock = $this->blockRemainingSeconds(
                $area,
                'ip_address = :ip',
                ['ip' => $ip, 'window_start' => $windowStart],
                $maxPerIp,
                $blockMinutes,
                $nowTs
            );

            $normalizedEmail = strtolower(trim($email));
            $emailBlock = 0;
            if ($normalizedEmail !== '') {
                $emailBlock = $this->blockRemainingSeconds(
                    $area,
                    'email = :email',
                    ['email' => $normalizedEmail, 'window_start' => $windowStart],
                    $maxPerUser,
                    $blockMinutes,
                    $nowTs
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
            if ($this->authFailOpenOnSecurityError()) {
                error_log(
                    '[Solis] SecurityService fail-open ativo em erro interno de controle de login. '
                    . 'Detalhe=' . $exception->getMessage()
                );
                return ['allowed' => true, 'retry_after' => 0, 'reason' => 'fail_open', 'message' => ''];
            }

            error_log(
                '[Solis] SecurityService bloqueou tentativa de login por erro interno. '
                . 'Detalhe=' . $exception->getMessage()
            );
            return [
                'allowed' => false,
                'retry_after' => 300,
                'reason' => 'security_service_unavailable',
                'message' => $this->t(
                    'common.auth_security_temporarily_unavailable',
                    'Autenticacao temporariamente indisponivel por seguranca. Tente novamente em alguns minutos.'
                ),
            ];
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
                'attempted_at' => $this->clockDateTimeNow(),
            ]);
        } catch (\Throwable $exception) {
            if (!$this->authFailOpenOnSecurityError()) {
                error_log(
                    '[Solis] SecurityService falhou ao registrar tentativa de login em modo fail-closed. '
                    . 'Detalhe=' . $exception->getMessage()
                );
            }
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
                'created_at' => $this->clockDateTimeNow(),
            ]);
        } catch (\Throwable $exception) {
            if (!$this->authFailOpenOnSecurityError()) {
                error_log(
                    '[Solis] SecurityService falhou ao gravar auditoria em modo fail-closed. '
                    . 'Detalhe=' . $exception->getMessage()
                );
            }
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
        int $blockMinutes,
        int $nowTs
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

        $lastFailedTs = $this->parseDateTimeToUnix($lastFailed);
        if ($lastFailedTs === null) {
            return 0;
        }

        $blockedUntil = $lastFailedTs + ($blockMinutes * 60);
        return max(0, $blockedUntil - $nowTs);
    }

    private function authConfig(): array
    {
        $config = $this->registry->get('config');

        return (array) ($config ? $config->get('security.auth', []) : []);
    }

    private function authFailOpenOnSecurityError(): bool
    {
        $authConfig = $this->authConfig();
        $value = $authConfig['fail_open_on_security_error'] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
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

    private function parseDateTimeToUnix(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);
        $hour = (int) ($matches[4] ?? 0);
        $minute = (int) ($matches[5] ?? 0);
        $second = (int) ($matches[6] ?? 0);

        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return mktime($hour, $minute, $second, $month, $day, $year);
    }

}
