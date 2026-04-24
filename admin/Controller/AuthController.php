<?php

namespace Admin\Controller;

class AuthController extends BaseController
{
    public function login(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/login', [
            'title' => $this->t('auth.title_login', '{app} | Admin Login', ['app' => $appName]),
        ]);
    }

    public function authenticate(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisição inválida.'));
            $this->redirectToRoute('auth/login');
        }

        $email = trim((string) $this->request->post('email'));
        $password = (string) $this->request->post('password');

        if (!$this->auth->attempt($email, $password)) {
            $message = trim($this->auth->lastErrorMessage());
            flash('error', $message !== '' ? $message : $this->t('auth.flash_invalid_credentials', 'Credenciais inválidas.'));
            $this->redirectToRoute('auth/login');
        }

        flash('success', $this->t(
            'auth.flash_login_success',
            'Acesso liberado ao painel administrativo do {app}.',
            ['app' => (string) $this->config->get('app.name', 'Solis')]
        ));
        $this->redirectToRoute('dashboard/index');
    }

    public function logout(): void
    {
        $this->requirePostAndCsrf();
        $this->auth->logout();
        flash('success', $this->t('auth.flash_logout_success', 'Sessão encerrada com sucesso.'));
        $this->redirectToRoute('auth/login');
    }
}
