<?php

namespace System\Library;

use System\Engine\Registry;
use System\Library\SecurityService;

class Auth
{
    use TemporalClockTrait;

    private const DEFAULT_SUPPORTED_LANGUAGE_CODES = ['en-us', 'pt-br'];

    private ?array $user = null;
    private string $lastErrorCode = '';
    private string $lastErrorMessage = '';
    private ?bool $languageColumnAvailable = null;

    public function __construct(private readonly Registry $registry, private readonly string $area)
    {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $session = $this->registry->get('session');
        $db = $this->registry->get('db');
        $userId = $this->sessionUserId($session);

        if ($userId <= 0 || !$db || !$db->connected()) {
            return null;
        }

        if (!$this->sessionIntegrityValid($userId)) {
            return null;
        }

        $sql = 'SELECT u.*, ug.name AS group_name, ug.permissions_json
                FROM users u
                LEFT JOIN user_groups ug ON ug.id = u.user_group_id
                WHERE u.id = :id AND u.status = 1
                LIMIT 1';

        $this->user = $db->fetch($sql, ['id' => $userId]);
        if (is_array($this->user)) {
            $this->syncSessionLanguageFromUser($this->user);
        }

        return $this->user;
    }

    public function attempt(string $email, string $password): bool
    {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            $this->setError('db_unavailable', $this->t('common.auth_db_unavailable', 'Servico de autenticacao indisponivel no momento.'));
            return false;
        }

        $identifier = $this->normalizeAuthIdentifier($email);
        if ($identifier === '') {
            $this->setError('invalid_credentials', $this->t('common.auth_invalid_credentials', 'Credenciais invalidas.'));
            return false;
        }

        $gate = $this->security()->canAttemptLogin($this->area, $identifier);
        if (empty($gate['allowed'])) {
            $this->setError('blocked', (string) ($gate['message'] ?? $this->t('common.auth_blocked', 'Acesso temporariamente bloqueado por seguranca.')));
            $this->security()->audit('login_blocked', 'warning', null, $this->area, [
                'email' => $identifier,
                'reason' => $gate['reason'] ?? 'unknown',
                'retry_after' => (int) ($gate['retry_after'] ?? 0),
            ]);
            return false;
        }

        $user = $this->resolveUserForAuthentication($identifier);
        if (!$user) {
            $this->security()->registerLoginAttempt($this->area, $identifier, false, null, 'user_not_found');
            $this->setError('invalid_credentials', $this->t('common.auth_invalid_credentials', 'Credenciais invalidas.'));
            return false;
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        $passwordMatches = $passwordHash !== '' && password_verify($password, $passwordHash);
        if (!$passwordMatches) {
            $normalizedPassword = $this->normalizeAuthPassword($password);
            if ($normalizedPassword !== $password && $normalizedPassword !== '') {
                $passwordMatches = password_verify($normalizedPassword, $passwordHash);
            }
        }

        if (!$passwordMatches) {
            $this->security()->registerLoginAttempt($this->area, $identifier, false, (int) $user['id'], 'invalid_password');
            $this->setError('invalid_credentials', $this->t('common.auth_invalid_credentials', 'Credenciais invalidas.'));
            return false;
        }

        if (!$this->canAccessArea($user)) {
            $this->security()->registerLoginAttempt($this->area, $identifier, false, (int) $user['id'], 'area_not_allowed');
            $this->setError('area_not_allowed', $this->accessDeniedMessage());
            return false;
        }

        $session = $this->registry->get('session');
        $session->regenerate(true);
        $session->set($this->sessionUserKey(), (int) $user['id']);
        $session->set($this->sessionFingerprintKey(), $this->sessionFingerprint((int) $user['id']));
        $session->set($this->sessionStartedAtKey(), $this->clockUnixNow());
        $this->clearLegacySessionKeys($session);
        $session->set('language_code', $this->resolveUserLanguageCode($user));

        $this->security()->registerLoginAttempt($this->area, $identifier, true, (int) $user['id'], 'login_success');
        $this->security()->audit('login_success', 'info', (int) $user['id'], $this->area, [
            'email' => $identifier,
        ]);

        $db->update('users', ['last_login_at' => $this->clockDateTimeNow()], 'id = :id', ['id' => (int) $user['id']]);
        $this->user = null;
        $this->setError('', '');

        return true;
    }

    private function resolveUserForAuthentication(string $identifier): ?array
    {
        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            return null;
        }

        // Admin accepts e-mail, recovery e-mail, or username to reduce lockouts.
        if ($this->area === 'admin') {
            return $db->fetch(
                'SELECT u.*, ug.permissions_json
                 FROM users u
                 LEFT JOIN user_groups ug ON ug.id = u.user_group_id
                 WHERE u.status = 1
                   AND (
                     LOWER(u.email) = :identifier_email
                     OR LOWER(COALESCE(u.recovery_email, \'\')) = :identifier_recovery
                     OR LOWER(u.name) = :identifier_name
                   )
                 ORDER BY
                   CASE
                     WHEN LOWER(u.email) = :identifier_order_email THEN 0
                     WHEN LOWER(COALESCE(u.recovery_email, \'\')) = :identifier_order_recovery THEN 1
                     ELSE 2
                   END,
                   u.id ASC
                 LIMIT 1',
                [
                    'identifier_email' => $identifier,
                    'identifier_recovery' => $identifier,
                    'identifier_name' => $identifier,
                    'identifier_order_email' => $identifier,
                    'identifier_order_recovery' => $identifier,
                ]
            );
        }

        return $db->fetch(
            'SELECT u.*, ug.permissions_json
             FROM users u
             LEFT JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.status = 1
               AND (
                   LOWER(u.email) = :identifier_email
                   OR LOWER(COALESCE(u.recovery_email, \'\')) = :identifier_recovery
               )
             ORDER BY
               CASE
                 WHEN LOWER(u.email) = :identifier_order_email THEN 0
                 ELSE 1
               END,
               u.id ASC
             LIMIT 1',
            [
                'identifier_email' => $identifier,
                'identifier_recovery' => $identifier,
                'identifier_order_email' => $identifier,
            ]
        );
    }

    public function logout(): void
    {
        $session = $this->registry->get('session');
        $userId = $this->sessionUserId($session);

        if ($userId > 0) {
            $this->security()->audit('logout', 'info', $userId, $this->area, []);
        }

        $session->remove($this->sessionUserKey());
        $session->remove($this->sessionFingerprintKey());
        $session->remove($this->sessionStartedAtKey());
        $this->user = null;
    }

    public function hasPermission(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $permissions = json_decode((string) ($user['permissions_json'] ?? '[]'), true);
        if (!is_array($permissions)) {
            $permissions = [];
        }

        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($this->area . '.*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function supportedLanguageCodes(): array
    {
        $config = $this->registry->get('config');
        $supported = is_object($config) && method_exists($config, 'get')
            ? (array) $config->get('app.languages.supported', [])
            : [];

        $codes = [];
        foreach ($supported as $code => $_metadata) {
            $normalized = $this->sanitizeLanguageCode((string) $code);
            if ($normalized === null) {
                continue;
            }

            $codes[$normalized] = true;
        }

        if ($codes === []) {
            return self::DEFAULT_SUPPORTED_LANGUAGE_CODES;
        }

        $result = array_keys($codes);
        sort($result);

        return $result;
    }

    public function updateLanguagePreference(string $languageCode): bool
    {
        $normalized = $this->normalizeLanguageCode($languageCode);
        if ($normalized === null) {
            return false;
        }

        $session = $this->registry->get('session');
        $session->set('language_code', $normalized);

        $user = $this->user();
        if (!$user) {
            return true;
        }

        $db = $this->registry->get('db');
        if ($db && $db->connected() && $this->usersLanguageColumnAvailable()) {
            try {
                $db->update('users', [
                    'language_code' => $normalized,
                    'updated_at' => $this->clockDateTimeNow(),
                ], 'id = :id', ['id' => (int) $user['id']]);
                $this->user['language_code'] = $normalized;
            } catch (\Throwable) {
                // Mantem fallback em sessao quando persistencia falhar.
            }
        }

        return true;
    }

    public function lastErrorCode(): string
    {
        return $this->lastErrorCode;
    }

    public function lastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    private function setError(string $code, string $message): void
    {
        $this->lastErrorCode = $code;
        $this->lastErrorMessage = $message;
    }

    private function sessionIntegrityValid(int $userId): bool
    {
        $session = $this->registry->get('session');
        $storedFingerprint = (string) $session->get($this->sessionFingerprintKey(), '');
        $startedAt = (int) $session->get($this->sessionStartedAtKey(), 0);

        if ($storedFingerprint === '') {
            $session->set($this->sessionFingerprintKey(), $this->sessionFingerprint($userId));
            $session->set($this->sessionStartedAtKey(), $this->clockUnixNow());
            return true;
        }

        if (!hash_equals($storedFingerprint, $this->sessionFingerprint($userId))) {
            if ($this->migrateLegacySessionFingerprint($storedFingerprint, $userId)) {
                $session->set($this->sessionFingerprintKey(), $this->sessionFingerprint($userId));
                return true;
            }

            $this->security()->audit('session_fingerprint_mismatch', 'critical', $userId, $this->area, []);
            $this->forceLogoutSession();
            return false;
        }

        $ttlMinutes = (int) $this->registry->get('config')->get('security.auth.session_ttl_minutes', 720);
        $ttlMinutes = max(15, $ttlMinutes);
        if ($startedAt > 0 && ($this->clockUnixNow() - $startedAt) > ($ttlMinutes * 60)) {
            $this->security()->audit('session_expired', 'warning', $userId, $this->area, ['ttl_minutes' => $ttlMinutes]);
            $this->forceLogoutSession();
            return false;
        }

        return true;
    }

    private function sessionFingerprint(int $userId): string
    {
        $parts = [
            $this->area,
            $userId,
        ];

        if ($this->bindSessionToIp()) {
            $parts[] = $this->sessionClientIp();
        }

        if ($this->bindSessionToUserAgent()) {
            $parts[] = $this->sessionUserAgent();
        }

        return hash('sha256', implode('|', $parts));
    }

    private function bindSessionToIp(): bool
    {
        return (bool) $this->registry->get('config')->get('security.auth.bind_session_to_ip', false);
    }

    private function bindSessionToUserAgent(): bool
    {
        return (bool) $this->registry->get('config')->get('security.auth.bind_session_to_user_agent', false);
    }

    private function sessionClientIp(): string
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

    private function sessionUserAgent(): string
    {
        $server = $this->requestServerContext();
        $userAgent = trim((string) ($server['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return 'unknown';
        }

        return mb_substr($userAgent, 0, 255);
    }

    private function requestServerContext(): array
    {
        $request = $this->registry->get('request');
        if (!is_object($request) || !isset($request->server) || !is_array($request->server)) {
            return [];
        }

        return $request->server;
    }

    private function shouldTrustForwardedHeaders(string $remoteAddr): bool
    {
        $config = $this->registry->get('config');
        $trustedProxies = (array) ($config ? $config->get('security.trusted_proxies', []) : []);
        if ($trustedProxies === []) {
            return false;
        }

        foreach ($trustedProxies as $rule) {
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
            return hash_equals(strtolower(trim($rule)), strtolower(trim($ip)));
        }

        return $this->ipInCidr($ip, $rule);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefixRaw] = array_pad(explode('/', $cidr, 2), 2, '');
        $network = trim($network);
        $prefixRaw = trim($prefixRaw);
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

    private function migrateLegacySessionFingerprint(string $storedFingerprint, int $userId): bool
    {
        $storedFingerprint = trim($storedFingerprint);
        if ($storedFingerprint === '') {
            return false;
        }

        $server = $this->requestServerContext();
        $legacyUserAgent = (string) ($server['HTTP_USER_AGENT'] ?? 'unknown');
        $legacyRemoteAddr = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
        $resolvedClientIp = $this->sessionClientIp();

        $candidates = [
            hash('sha256', implode('|', [$this->area, $userId, $legacyRemoteAddr, $legacyUserAgent])),
            hash('sha256', implode('|', [$this->area, $userId, $resolvedClientIp, $legacyUserAgent])),
        ];

        foreach ($candidates as $candidate) {
            if (hash_equals($storedFingerprint, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function forceLogoutSession(): void
    {
        $session = $this->registry->get('session');
        $session->remove($this->sessionUserKey());
        $session->remove($this->sessionFingerprintKey());
        $session->remove($this->sessionStartedAtKey());
        $this->user = null;
    }

    private function sessionUserId(mixed $session): int
    {
        $userId = (int) $session->get($this->sessionUserKey(), 0);
        if ($userId > 0) {
            return $userId;
        }

        $legacyUserId = (int) $session->get('user_id', 0);
        if ($legacyUserId <= 0) {
            return 0;
        }

        $legacyFingerprint = (string) $session->get('session_fingerprint', '');
        if ($legacyFingerprint === '') {
            return 0;
        }

        $currentFingerprint = $this->sessionFingerprint($legacyUserId);
        if (
            !hash_equals($legacyFingerprint, $currentFingerprint)
            && !$this->migrateLegacySessionFingerprint($legacyFingerprint, $legacyUserId)
        ) {
            return 0;
        }

        if (!hash_equals($legacyFingerprint, $currentFingerprint)) {
            $legacyFingerprint = $currentFingerprint;
        }

        $session->set($this->sessionUserKey(), $legacyUserId);
        $session->set($this->sessionFingerprintKey(), $legacyFingerprint);
        $session->set(
            $this->sessionStartedAtKey(),
            (int) $session->get('session_started_at', $this->clockUnixNow())
        );
        $this->clearLegacySessionKeys($session);

        return $legacyUserId;
    }

    private function clearLegacySessionKeys(mixed $session): void
    {
        $session->remove('user_id');
        $session->remove('session_fingerprint');
        $session->remove('session_started_at');
    }

    private function sessionUserKey(): string
    {
        return $this->area . '_user_id';
    }

    private function sessionFingerprintKey(): string
    {
        return $this->area . '_session_fingerprint';
    }

    private function sessionStartedAtKey(): string
    {
        return $this->area . '_session_started_at';
    }

    private function security(): SecurityService
    {
        $service = $this->registry->get('security');
        if ($service instanceof SecurityService) {
            return $service;
        }

        return new SecurityService($this->registry);
    }

    private function canAccessArea(array $user): bool
    {
        $permissions = json_decode((string) ($user['permissions_json'] ?? '[]'), true);
        if (!is_array($permissions)) {
            $permissions = [];
        }

        if (in_array('*', $permissions, true) || in_array($this->area . '.*', $permissions, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            if (str_starts_with($permission, $this->area . '.')) {
                return true;
            }
        }

        return false;
    }

    private function syncSessionLanguageFromUser(array $user): void
    {
        $session = $this->registry->get('session');
        $session->set('language_code', $this->resolveUserLanguageCode($user));
    }

    private function resolveUserLanguageCode(array $user): string
    {
        $normalized = $this->normalizeLanguageCode((string) ($user['language_code'] ?? ''));
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->defaultLanguageCode();
    }

    private function defaultLanguageCode(): string
    {
        $config = $this->registry->get('config');
        $configured = is_object($config) && method_exists($config, 'get')
            ? (string) $config->get('app.default_language', 'en-us')
            : 'en-us';

        return $this->normalizeLanguageCode($configured) ?? 'en-us';
    }

    private function normalizeLanguageCode(string $languageCode): ?string
    {
        $languageCode = $this->sanitizeLanguageCode($languageCode);
        if ($languageCode === null) {
            return null;
        }

        return in_array($languageCode, $this->supportedLanguageCodes(), true) ? $languageCode : null;
    }

    private function sanitizeLanguageCode(string $languageCode): ?string
    {
        $languageCode = strtolower(trim($languageCode));
        $languageCode = str_replace('_', '-', $languageCode);

        return preg_match('/^[a-z]{2}-[a-z]{2}$/', $languageCode) === 1 ? $languageCode : null;
    }

    private function normalizeAuthIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }

        // Remove invisible separators that may come from mobile keyboards/autofill.
        $identifier = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $identifier) ?? $identifier;
        // Collapse any unicode whitespace inside/borders to avoid hidden mismatch.
        $identifier = preg_replace('/\s+/u', ' ', $identifier) ?? $identifier;
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            // Mobile keyboards/autocorrect may inject spaces/punctuation around e-mail.
            $identifier = str_replace(' ', '', $identifier);
            $identifier = rtrim($identifier, ".,;:");
        }

        return strtolower($identifier);
    }

    private function normalizeAuthPassword(string $password): string
    {
        if ($password === '') {
            return '';
        }

        // Remove invisible separators commonly introduced by mobile/autofill copy-paste.
        $password = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $password) ?? $password;
        // Remove leading/trailing unicode whitespace/control chars without altering middle chars.
        $password = preg_replace('/^[\p{Z}\p{C}\s]+|[\p{Z}\p{C}\s]+$/u', '', $password) ?? $password;

        return $password;
    }

    private function usersLanguageColumnAvailable(): bool
    {
        if ($this->languageColumnAvailable !== null) {
            return $this->languageColumnAvailable;
        }

        $db = $this->registry->get('db');
        if (!$db || !$db->connected()) {
            $this->languageColumnAvailable = false;
            return false;
        }

        try {
            $column = $db->fetch("SHOW COLUMNS FROM users LIKE 'language_code'");
            if ($column) {
                $this->languageColumnAvailable = true;
                return true;
            }

            error_log(
                '[Solis] Coluna users.language_code ausente. '
                . 'Execute a migracao operacional para manter persistencia de idioma no perfil.'
            );
            $this->languageColumnAvailable = false;
            return false;
        } catch (\Throwable) {
            $this->languageColumnAvailable = false;
            return false;
        }
    }

    private function accessDeniedMessage(): string
    {
        if ($this->area === 'admin') {
            return $this->t('common.auth_access_denied_admin', 'Seu usuario nao possui acesso administrativo.');
        }

        if ($this->area === 'client') {
            return $this->t('common.auth_access_denied_client', 'Seu usuario nao possui acesso a area do cliente.');
        }

        return $this->t('common.auth_access_denied_area', 'Seu usuario nao possui acesso a esta area.');
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
