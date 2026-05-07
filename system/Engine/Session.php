<?php

namespace System\Engine;

class Session
{
    public function __construct(string $name, string $savePath, array $security = [])
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!is_dir($savePath)) {
            mkdir($savePath, 0775, true);
        }

        $trustedProxies = array_map(
            static fn ($proxy): string => strtolower(trim((string) $proxy)),
            (array) ($security['trusted_proxies'] ?? [])
        );

        $isHttps = $this->requestIsHttps($trustedProxies);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name($name);
        session_save_path($savePath);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) ($params['path'] ?? '/'),
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }

        session_destroy();
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
        }
    }

    private function requestIsHttps(array $trustedProxies = []): bool
    {
        if (function_exists('nosfir_request_is_https')) {
            return \nosfir_request_is_https($trustedProxies);
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $remoteAddr = strtolower(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        if ($remoteAddr === '' || !in_array($remoteAddr, $trustedProxies, true)) {
            return false;
        }

        $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $parts = explode(',', $forwardedProto);
            $proto = strtolower(trim((string) ($parts[0] ?? '')));
            if ($proto === 'https') {
                return true;
            }
        }

        $forwarded = trim((string) ($_SERVER['HTTP_FORWARDED'] ?? ''));
        if ($forwarded !== '' && preg_match('/proto=https/i', $forwarded) === 1) {
            return true;
        }

        return false;
    }
}
