<?php

namespace System\Engine;

use System\Library\Auth;
use System\Library\Database;
use System\Library\HostGuard;
use System\Library\Language;
use System\Library\SecurityService;

class Application
{
    private Registry $registry;

    public function __construct(private readonly string $area)
    {
        $this->registry = new Registry();

        $this->bootstrap();
    }

    public function run(): void
    {
        $config = $this->registry->get('config');
        $installed = (bool) $config->get('app.installed', false);

        if (!$installed && $this->area !== 'install') {
            $this->registry->get('response')->redirect($this->installUrl());
        }

        if ($installed && $this->area === 'install' && !$this->canAccessInstalledInstaller()) {
            $this->registry->get('response')->redirect($this->clientUrl());
        }

        $router = new Router($this->registry, $this->area);
        $router->dispatch();
        $this->registry->get('response')->send();
    }

    private function bootstrap(): void
    {
        $config = new Config();
        $config->load('app', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Config');
        $config->load('database', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Config');

        $routes = $config->load('routes_' . $this->area, DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Config');
        $config->set('routes', $routes);

        $rootConfigFile = DIR_ROOT . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($rootConfigFile)) {
            $rootConfig = require $rootConfigFile;
            if (is_array($rootConfig)) {
                $config->mergeConfig($rootConfig);
            }
        }

        $areaConfigFile = DIR_ROOT . DIRECTORY_SEPARATOR . $this->area . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($areaConfigFile)) {
            $areaConfig = require $areaConfigFile;
            if (is_array($areaConfig)) {
                $config->mergeConfig($areaConfig);
            }
        }

        // Compatibilidade com configuração legada em system/Storage/config.php.
        $storageConfigFile = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($storageConfigFile)) {
            $runtime = require $storageConfigFile;
            if (is_array($runtime)) {
                $config->mergeConfig($runtime);
            }
        }

        date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));
        $this->guardAllowedHost($config);

        $sessionName = (string) $config->get('app.session_name', 'nsplanner');
        $sessionPath = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'sessions';
        $session = new Session($sessionName, $sessionPath);

        $request = new Request();
        $response = new Response();
        $database = null;

        try {
            $database = new Database((array) $config->get('database', []));
        } catch (\Throwable $exception) {
            $database = new Database([]);
        }

        $language = new Language(
            $this->area,
            $this->resolveLanguageCode($session, $config),
            'en-us'
        );
        $language->load('common');

        $this->registry->set('config', $config);
        $this->registry->set('request', $request);
        $this->registry->set('response', $response);
        $this->registry->set('session', $session);
        $this->registry->set('db', $database);
        $this->registry->set('language', $language);
        $this->registry->set('loader', new Loader($this->registry, $this->area));
        $this->registry->set('view', new View(DIR_ROOT . DIRECTORY_SEPARATOR . $this->area . DIRECTORY_SEPARATOR . 'View'));
        $this->registry->set('security', new SecurityService($this->registry));
        $this->registry->set('auth', new Auth($this->registry, $this->area));
    }

    private function installUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->runtimeHost();
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/(admin|client|install)$#', '', $scriptDir);

        return rtrim($scheme . '://' . $host . $rootDir, '/') . '/install';
    }

    private function clientUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->runtimeHost();
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/(admin|client|install)$#', '', $scriptDir);

        return rtrim($scheme . '://' . $host . $rootDir, '/') . '/client';
    }

    private function canAccessInstalledInstaller(): bool
    {
        $config = $this->registry->get('config');
        $allowReinstall = (bool) $config->get('security.allow_reinstall', false);

        if (!$allowReinstall) {
            return false;
        }

        $auth = $this->registry->get('auth');
        $requiredPermission = (string) $config->get('security.reinstall_permission', 'admin.install.reinstall');

        if (!$auth || !$auth->check()) {
            return false;
        }

        if ($requiredPermission !== '' && !$auth->hasPermission($requiredPermission)) {
            return false;
        }

        $reinstallKey = (string) $config->get('security.reinstall_key', '');
        if ($reinstallKey === '') {
            return false;
        }

        $request = $this->registry->get('request');
        $provided = (string) $request->get('reinstall_key', (string) $request->post('reinstall_key', ''));

        return $provided !== '' && hash_equals($reinstallKey, $provided);
    }

    private function guardAllowedHost(Config $config): void
    {
        $allowedHosts = (array) $config->get('security.allowed_hosts', []);
        $baseUrl = (string) $config->get('app.base_url', '');

        if (HostGuard::isAllowedRequestHost($_SERVER, $allowedHosts, $baseUrl)) {
            return;
        }

        if (!headers_sent()) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo 'Bad Request: host não permitido.';
        exit;
    }

    private function runtimeHost(): string
    {
        $config = $this->registry->get('config');
        if (!$config instanceof Config) {
            return HostGuard::effectiveHost($_SERVER, [], '');
        }

        return HostGuard::effectiveHost(
            $_SERVER,
            (array) $config->get('security.allowed_hosts', []),
            (string) $config->get('app.base_url', '')
        );
    }

    private function resolveLanguageCode(Session $session, Config $config): string
    {
        $default = $this->normalizeLanguageCode((string) $config->get('app.default_language', 'en-us')) ?? 'en-us';
        $sessionCode = $this->normalizeLanguageCode((string) $session->get('language_code', ''));

        return $sessionCode ?? $default;
    }

    private function normalizeLanguageCode(string $languageCode): ?string
    {
        $languageCode = strtolower(trim($languageCode));
        $languageCode = str_replace('_', '-', $languageCode);

        return in_array($languageCode, ['en-us', 'pt-br'], true) ? $languageCode : null;
    }
}
