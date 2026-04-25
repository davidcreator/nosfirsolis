<?php

namespace Client\Controller;

class AuthController extends BaseController
{
    public function login(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/login', [
            'title' => $this->t('auth.title_login', '{app} | Login', ['app' => $appName]),
        ]);
    }

    public function authenticate(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisição inválida.'));
            $this->redirectAfterLoginFailure();
        }

        $email = trim((string) $this->request->post('email'));
        $password = (string) $this->request->post('password');

        if (!$this->auth->attempt($email, $password)) {
            $message = trim($this->auth->lastErrorMessage());
            flash('error', $message !== '' ? $message : $this->t('auth.flash_invalid_credentials', 'Credenciais inválidas.'));
            $this->redirectAfterLoginFailure();
        }

        flash('success', $this->t(
            'auth.flash_login_success',
            'Bem-vindo ao {app}.',
            ['app' => (string) $this->config->get('app.name', 'Solis')]
        ));
        $this->redirectToRoute('dashboard/index');
    }

    public function logout(): void
    {
        $this->ensurePostWithCsrf();
        $this->auth->logout();
        flash('success', $this->t('auth.flash_logout_success', 'Sessão encerrada.'));
        $this->redirectToRoute('auth/login');
    }

    private function redirectAfterLoginFailure(): never
    {
        if ($this->shouldReturnToLanding()) {
            $this->response->redirect($this->landingUrl());
        }

        $this->redirectToRoute('auth/login');
    }

    private function shouldReturnToLanding(): bool
    {
        $returnTo = strtolower(trim((string) $this->request->post(
            'return_to',
            (string) $this->request->get('return_to', '')
        )));

        return $returnTo === 'landing';
    }

    private function landingUrl(): string
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $rootDir = preg_replace('#/client$#', '', rtrim($scriptDir, '/'));

        if (!is_string($rootDir) || $rootDir === '') {
            return '/';
        }

        return $rootDir . '/';
    }
}

