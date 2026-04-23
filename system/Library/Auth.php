<?php

namespace System\Library;

use System\Engine\Registry;
use System\Library\SecurityService;

class Auth
{
    private const SUPPORTED_LANGUAGE_CODES = ['en-us', 'pt-br'];

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
        $userId = (int) $session->get('user_id', 0);

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

        $email = strtolower(trim($email));
        $gate = $this->security()->canAttemptLogin($this->area, $email);
        if (empty($gate['allowed'])) {
            $this->setError('blocked', (string) ($gate['message'] ?? $this->t('common.auth_blocked', 'Acesso temporariamente bloqueado por seguranca.')));
            $this->security()->audit('login_blocked', 'warning', null, $this->area, [
                'email' => $email,
                'reason' => $gate['reason'] ?? 'unknown',
                'retry_after' => (int) ($gate['retry_after'] ?? 0),
            ]);
            return false;
        }

        $user = $db->fetch(
            'SELECT u.*, ug.permissions_json
             FROM users u
             LEFT JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.email = :email
               AND u.status = 1
             LIMIT 1',
            ['email' => $email]
        );
        if (!$user) {
            $this->security()->registerLoginAttempt($this->area, $email, false, null, 'user_not_found');
            $this->setError('invalid_credentials', $this->t('common.auth_invalid_credentials', 'Credenciais invalidas.'));
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->security()->registerLoginAttempt($this->area, $email, false, (int) $user['id'], 'invalid_password');
            $this->setError('invalid_credentials', $this->t('common.auth_invalid_credentials', 'Credenciais invalidas.'));
            return false;
        }

        if (!$this->canAccessArea($user)) {
            $this->security()->registerLoginAttempt($this->area, $email, false, (int) $user['id'], 'area_not_allowed');
            $this->setError('area_not_allowed', $this->accessDeniedMessage());
            return false;
        }

        $session = $this->registry->get('session');
        $session->regenerate(true);
        $session->set('user_id', (int) $user['id']);
        $session->set('session_fingerprint', $this->sessionFingerprint((int) $user['id']));
        $session->set('session_started_at', time());
        $session->set('language_code', $this->resolveUserLanguageCode($user));

        $this->security()->registerLoginAttempt($this->area, $email, true, (int) $user['id'], 'login_success');
        $this->security()->audit('login_success', 'info', (int) $user['id'], $this->area, [
            'email' => $email,
        ]);

        $db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $user['id']]);
        $this->user = null;
        $this->setError('', '');

        return true;
    }

    public function logout(): void
    {
        $session = $this->registry->get('session');
        $userId = (int) $session->get('user_id', 0);

        if ($userId > 0) {
            $this->security()->audit('logout', 'info', $userId, $this->area, []);
        }

        $session->remove('user_id');
        $session->remove('session_fingerprint');
        $session->remove('session_started_at');
        $session->remove('language_code');
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
        return self::SUPPORTED_LANGUAGE_CODES;
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
                    'updated_at' => date('Y-m-d H:i:s'),
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
        $storedFingerprint = (string) $session->get('session_fingerprint', '');
        $startedAt = (int) $session->get('session_started_at', 0);

        if ($storedFingerprint === '') {
            $session->set('session_fingerprint', $this->sessionFingerprint($userId));
            $session->set('session_started_at', time());
            return true;
        }

        if (!hash_equals($storedFingerprint, $this->sessionFingerprint($userId))) {
            $this->security()->audit('session_fingerprint_mismatch', 'critical', $userId, $this->area, []);
            $this->forceLogoutSession();
            return false;
        }

        $ttlMinutes = (int) $this->registry->get('config')->get('security.auth.session_ttl_minutes', 720);
        $ttlMinutes = max(15, $ttlMinutes);
        if ($startedAt > 0 && (time() - $startedAt) > ($ttlMinutes * 60)) {
            $this->security()->audit('session_expired', 'warning', $userId, $this->area, ['ttl_minutes' => $ttlMinutes]);
            $this->forceLogoutSession();
            return false;
        }

        return true;
    }

    private function sessionFingerprint(int $userId): string
    {
        $request = $this->registry->get('request');
        $server = is_object($request) ? $request->server : $_SERVER;
        $ip = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
        $userAgent = (string) ($server['HTTP_USER_AGENT'] ?? 'unknown');

        return hash('sha256', implode('|', [
            $this->area,
            $userId,
            $ip,
            $userAgent,
        ]));
    }

    private function forceLogoutSession(): void
    {
        $session = $this->registry->get('session');
        $session->remove('user_id');
        $session->remove('session_fingerprint');
        $session->remove('session_started_at');
        $session->remove('language_code');
        $this->user = null;
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
        $languageCode = strtolower(trim($languageCode));
        $languageCode = str_replace('_', '-', $languageCode);

        return in_array($languageCode, self::SUPPORTED_LANGUAGE_CODES, true) ? $languageCode : null;
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

            $db->execute("ALTER TABLE users ADD COLUMN language_code VARCHAR(10) NOT NULL DEFAULT 'en-us' AFTER avatar");
            $column = $db->fetch("SHOW COLUMNS FROM users LIKE 'language_code'");
            $this->languageColumnAvailable = (bool) $column;
            return $this->languageColumnAvailable;
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
