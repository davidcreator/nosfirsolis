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
        $data['app_name'] = $this->normalizeAppNameForDisplay((string) $this->config->get('app.name', 'Solis'));
        $data['current_route'] = $this->request->route();
        $data['current_user'] = $this->auth ? $this->auth->user() : null;
        $data['message_success'] = flash('success');
        $data['message_error'] = flash('error');
        $data['language_code'] = method_exists($this->language, 'code') ? $this->language->code() : 'en-us';
        $data['supported_languages'] = $this->supportedLanguages();
        $data['t'] = fn (string $key, string $default = '', array $replacements = []): string => $this->t($key, $default, $replacements);

        $output = $this->view->render($template, $data, $layout);
        $this->response->setOutput($output);
    }

    private function normalizeAppNameForDisplay(string $appName): string
    {
        $appName = preg_replace('/\s+/', ' ', trim($appName)) ?? '';

        if ($appName === '') {
            return 'Solis';
        }

        if (preg_match('/^nosfir\s*solis$/i', $appName) === 1 || preg_match('/^nosfirsolis$/i', $appName) === 1) {
            return 'Solis';
        }

        $withoutPrefix = preg_replace('/^nosfir[\s\-\_\|:]+/i', '', $appName);
        $withoutPrefix = is_string($withoutPrefix) ? trim($withoutPrefix) : '';

        if ($withoutPrefix === '') {
            return 'Solis';
        }

        return $withoutPrefix;
    }

    protected function supportedLanguages(): array
    {
        $supported = (array) $this->config->get('app.languages.supported', []);
        $fallbackCode = $this->normalizedLanguageCode(
            (string) $this->config->get('app.languages.fallback', 'en-us')
        ) ?? 'en-us';

        $resolved = [];
        foreach ($supported as $code => $metadata) {
            $normalizedCode = $this->normalizedLanguageCode((string) $code);
            if ($normalizedCode === null) {
                continue;
            }

            $entry = is_array($metadata) ? $metadata : [];
            $resolved[$normalizedCode] = [
                'code' => $normalizedCode,
                'label' => trim((string) ($entry['label'] ?? strtoupper($normalizedCode))),
                'native_label' => trim((string) ($entry['native_label'] ?? $entry['label'] ?? strtoupper($normalizedCode))),
                'locale' => trim((string) ($entry['locale'] ?? str_replace('-', '_', $normalizedCode))),
            ];
        }

        if ($resolved === []) {
            $resolved = [
                'en-us' => [
                    'code' => 'en-us',
                    'label' => 'English (US)',
                    'native_label' => 'English (US)',
                    'locale' => 'en_US',
                ],
                'pt-br' => [
                    'code' => 'pt-br',
                    'label' => 'Portuguese (Brazil)',
                    'native_label' => 'Portugues (Brasil)',
                    'locale' => 'pt_BR',
                ],
            ];
        }

        if (!isset($resolved[$fallbackCode])) {
            $resolved[$fallbackCode] = [
                'code' => $fallbackCode,
                'label' => strtoupper($fallbackCode),
                'native_label' => strtoupper($fallbackCode),
                'locale' => str_replace('-', '_', $fallbackCode),
            ];
        }

        ksort($resolved);
        return array_values($resolved);
    }

    protected function defaultLanguageCode(): string
    {
        $configuredDefault = $this->normalizedLanguageCode((string) $this->config->get('app.default_language', ''));
        $supportedCodes = array_map(
            static fn (array $language): string => (string) ($language['code'] ?? ''),
            $this->supportedLanguages()
        );
        $supportedCodes = array_values(array_filter($supportedCodes, static fn (string $code): bool => $code !== ''));

        if ($configuredDefault !== null && in_array($configuredDefault, $supportedCodes, true)) {
            return $configuredDefault;
        }

        $fallback = $this->normalizedLanguageCode((string) $this->config->get('app.languages.fallback', 'en-us'));
        if ($fallback !== null && in_array($fallback, $supportedCodes, true)) {
            return $fallback;
        }

        return $supportedCodes[0] ?? 'en-us';
    }

    protected function normalizedLanguageCode(string $languageCode): ?string
    {
        $languageCode = strtolower(trim($languageCode));
        $languageCode = str_replace('_', '-', $languageCode);

        return preg_match('/^[a-z]{2}-[a-z]{2}$/', $languageCode) === 1 ? $languageCode : null;
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
