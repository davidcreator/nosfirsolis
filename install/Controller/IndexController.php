<?php

namespace Install\Controller;

use System\Engine\Controller;
use System\Library\HostGuard;

class IndexController extends Controller
{
    public function index(): void
    {
        if ($this->isInstalled() && !$this->hasReinstallPermission()) {
            $this->response->setStatusCode(403);
            $this->render('installer/locked', [
                'title' => $this->t('install.locked_title', 'Instalador protegido'),
                'message' => $this->t('install.locked_message', 'O sistema já está instalado. Reinstalação exige permissão administrativa e chave de autorização.'),
            ]);
            return;
        }

        $report = $this->loader->model('installer')->environmentReport();
        $reinstallKey = (string) $this->request->get('reinstall_key', '');
        $defaultEnvironment = $this->defaultEnvironment();
        $selectedEnvironment = old('app_env', $defaultEnvironment);

        $this->render('installer/index', [
            'title' => $this->t('install.title', 'Instalador do Sistema'),
            'report' => $report,
            'all_ok' => $this->allChecksPass($report),
            'reinstall_key' => $reinstallKey,
            'values' => [
                'db_host' => old('db_host', '127.0.0.1'),
                'db_port' => old('db_port', '3306'),
                'db_name' => old('db_name', ''),
                'db_user' => old('db_user', ''),
                'db_pass' => old('db_pass', ''),
                'admin_name' => old('admin_name', 'Administrador'),
                'admin_email' => old('admin_email', ''),
                'timezone' => old('timezone', 'America/Sao_Paulo'),
                'language_code' => old('language_code', 'en-us'),
                'app_env' => $selectedEnvironment,
                'allowed_hosts' => old('allowed_hosts', $this->defaultAllowedHosts($selectedEnvironment)),
            ],
        ]);
    }

    public function install(): void
    {
        if (!$this->request->isPost()) {
            $this->redirectToRoute('index/index');
        }

        if (!verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('install.flash_invalid_csrf', 'Token CSRF inválido.'));
            $this->redirectToRoute('index/index');
        }

        $allowReinstall = false;
        if ($this->isInstalled()) {
            $allowReinstall = $this->hasReinstallPermission();
            if (!$allowReinstall) {
                flash('error', $this->t('install.flash_reinstall_blocked', 'Reinstalação bloqueada sem permissão válida.'));
                $this->redirectToRoute('index/index');
            }
        }

        $payload = [
            'db_host' => trim((string) $this->request->post('db_host')),
            'db_port' => trim((string) $this->request->post('db_port')),
            'db_name' => trim((string) $this->request->post('db_name')),
            'db_user' => trim((string) $this->request->post('db_user')),
            'db_pass' => (string) $this->request->post('db_pass'),
            'admin_name' => trim((string) $this->request->post('admin_name')),
            'admin_email' => trim((string) $this->request->post('admin_email')),
            'admin_password' => (string) $this->request->post('admin_password'),
            'timezone' => trim((string) $this->request->post('timezone', 'America/Sao_Paulo')),
            'language_code' => trim((string) $this->request->post('language_code', 'en-us')),
            'app_env' => trim((string) $this->request->post('app_env', $this->defaultEnvironment())),
            'allowed_hosts' => trim((string) $this->request->post('allowed_hosts', $this->defaultAllowedHosts($this->defaultEnvironment()))),
            'allow_reinstall' => $allowReinstall,
        ];

        $result = $this->loader->model('installer')->install($payload);

        if (!$result['success']) {
            flash('error', $result['message']);
            $this->redirectToRoute('index/index');
        }

        flash('success', $this->t('install.flash_success', 'Instalação concluída. Acesse o painel do cliente para iniciar.'));
        $this->response->redirect($this->clientUrl());
    }

    private function allChecksPass(array $report): bool
    {
        foreach ($report as $check) {
            if (empty($check['ok'])) {
                return false;
            }
        }

        return true;
    }

    private function clientUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $requestHost = HostGuard::requestHost($_SERVER);
        $host = $requestHost !== ''
            ? $requestHost
            : HostGuard::effectiveHost(
                $_SERVER,
                (array) $this->config->get('security.allowed_hosts', []),
                (string) $this->config->get('app.base_url', '')
            );
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/(admin|client|install)$#', '', $scriptDir);

        return rtrim($scheme . '://' . $host . $rootDir, '/') . '/client';
    }

    private function defaultEnvironment(): string
    {
        $requestHost = HostGuard::requestHost($_SERVER);
        $localHosts = ['localhost', '127.0.0.1', '::1'];

        if (
            in_array($requestHost, $localHosts, true)
            || str_ends_with($requestHost, '.local')
            || str_ends_with($requestHost, '.test')
        ) {
            return 'development';
        }

        return 'production';
    }

    private function defaultAllowedHosts(string $environment): string
    {
        $environment = strtolower(trim($environment));
        $hosts = [];

        if ($environment === 'development') {
            $hosts[] = 'localhost';
            $hosts[] = '127.0.0.1';
            $hosts[] = '::1';
        }

        $requestHost = HostGuard::requestHost($_SERVER);
        if ($requestHost !== '') {
            $hosts[] = $requestHost;
        }

        $normalized = [];
        foreach ($hosts as $host) {
            $normalizedHost = HostGuard::normalizeHost($host);
            if ($normalizedHost !== '') {
                $normalized[$normalizedHost] = true;
            }
        }

        return implode(',', array_keys($normalized));
    }

    private function isInstalled(): bool
    {
        return (bool) $this->config->get('app.installed', false);
    }

    private function hasReinstallPermission(): bool
    {
        $allow = (bool) $this->config->get('security.allow_reinstall', false);
        if (!$allow) {
            return false;
        }

        if (!$this->auth || !$this->auth->check()) {
            return false;
        }

        $permission = (string) $this->config->get('security.reinstall_permission', 'admin.install.reinstall');
        if ($permission !== '' && !$this->auth->hasPermission($permission)) {
            return false;
        }

        $reinstallKey = (string) $this->config->get('security.reinstall_key', '');
        if ($reinstallKey === '') {
            return false;
        }

        $provided = (string) $this->request->get('reinstall_key', (string) $this->request->post('reinstall_key', ''));

        return $provided !== '' && hash_equals($reinstallKey, $provided);
    }
}
