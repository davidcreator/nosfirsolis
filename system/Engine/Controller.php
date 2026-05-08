<?php

namespace System\Engine;

use System\Library\HostGuard;

abstract class Controller
{
    use TemporalClockTrait;

    public function __construct(protected Registry $registry)
    {
    }

    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layout/main'): void
    {
        $data['app_name'] = $this->config->get('app.name', 'Solis');
        $data['current_route'] = $this->request->route();
        $data['current_user'] = $this->auth ? $this->auth->user() : null;
        $data['message_success'] = flash('success');
        $data['message_error'] = flash('error');
        $data['language_code'] = method_exists($this->language, 'code') ? $this->language->code() : 'en-us';
        $data['t'] = fn (string $key, string $default = '', array $replacements = []): string => $this->t($key, $default, $replacements);

        $output = $this->view->render($template, $data, $layout);
        $this->response->setOutput($output);
    }

    protected function redirectToRoute(string $route): never
    {
        $this->response->redirect(route_url($route));
    }

    protected function requestServer(): array
    {
        if (!isset($this->request) || !is_object($this->request)) {
            return [];
        }

        $server = $this->request->server ?? [];
        return is_array($server) ? $server : [];
    }

    protected function requestScriptDirectory(): string
    {
        $server = $this->requestServer();
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '');
        $scriptDir = str_replace('\\', '/', dirname($scriptName));

        if ($scriptDir === '.' || $scriptDir === '/') {
            return '';
        }

        return '/' . trim($scriptDir, '/');
    }

    protected function requestRootDirectory(): string
    {
        $scriptDir = $this->requestScriptDirectory();
        $rootDir = preg_replace('#/(admin|client|install)$#', '', $scriptDir);
        if (!is_string($rootDir) || $rootDir === '') {
            return '';
        }

        return '/' . trim($rootDir, '/');
    }

    protected function requestIsHttps(): bool
    {
        $trustedProxies = (array) $this->config->get('security.trusted_proxies', []);
        if (function_exists('nosfir_request_is_https')) {
            return (bool) \nosfir_request_is_https($trustedProxies);
        }

        $server = $this->requestServer();
        if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
            return true;
        }

        return (int) ($server['SERVER_PORT'] ?? 0) === 443;
    }

    protected function requestScheme(): string
    {
        return $this->requestIsHttps() ? 'https' : 'http';
    }

    protected function effectiveRequestHost(): string
    {
        return HostGuard::effectiveHost(
            $this->requestServer(),
            (array) $this->config->get('security.allowed_hosts', []),
            (string) $this->config->get('app.base_url', '')
        );
    }

    protected function requestHostAuthority(): string
    {
        $host = $this->effectiveRequestHost();
        if ($host === '') {
            return 'localhost';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '[' . $host . ']';
        }

        return $host;
    }

    protected function absoluteRouteUrl(string $route): string
    {
        return $this->requestScheme() . '://' . $this->requestHostAuthority() . route_url($route);
    }

    protected function areaUrl(string $area): string
    {
        $area = trim($area, '/');
        $rootDir = $this->requestRootDirectory();

        return rtrim($this->requestScheme() . '://' . $this->requestHostAuthority() . $rootDir, '/')
            . '/'
            . $area;
    }

    protected function nowUnixTime(): int
    {
        return $this->clockUnixNow();
    }

    protected function nowMicrotime(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    protected function formatDateTime(string $format = 'Y-m-d H:i:s', ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            return $this->clockFormat($format);
        }

        return $this->clockFormatAt($timestamp, $format);
    }

    protected function parseDateToTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $value, $matches) !== 1) {
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

    protected function monthEndDate(string $date): ?string
    {
        $timestamp = $this->parseDateToTimestamp($date);
        if ($timestamp === null) {
            return null;
        }

        return $this->formatDateTime('Y-m-t', $timestamp);
    }

    protected function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round(($this->nowMicrotime() - $startedAt) * 1000));
    }

    protected function requireAuth(string $permission = ''): void
    {
        if (!$this->auth || !$this->auth->check()) {
            $loginRoute = $this->config->get('routes.login_redirect', 'auth/login');
            $this->response->redirect(route_url($loginRoute));
        }

        if ($permission !== '' && !$this->auth->hasPermission($permission)) {
            $this->response->setStatusCode(403);
            $this->render('partials/forbidden', [
                'title' => $this->t('common.access_denied_title', 'Access denied'),
                'message' => $this->t('common.access_denied_message', 'Your user does not have permission to access this resource.'),
            ]);
            $this->response->send();
            exit;
        }
    }

    protected function t(string $key, string $default = '', array $replacements = []): string
    {
        $text = $this->language ? $this->language->get($key, $default) : $default;
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
