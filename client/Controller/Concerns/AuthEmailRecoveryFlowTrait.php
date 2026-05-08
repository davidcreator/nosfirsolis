<?php

namespace Client\Controller\Concerns;

trait AuthEmailRecoveryFlowTrait
{
    private bool $emailRecoveryStorageChecked = false;

    public function forgotEmail(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/forgot_email', [
            'title' => $this->t('auth.title_forgot_email', '{app} | Lembrar e-mail de acesso', ['app' => $appName]),
        ]);
    }

    public function sendEmailRecovery(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisicao invalida.'));
            $this->redirectToRoute('auth/forgotemail');
        }

        $recoveryEmail = strtolower(trim((string) $this->request->post('recovery_email', '')));
        if (!filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('auth.flash_recovery_email_invalid', 'Informe um e-mail de recuperacao valido.'));
            $this->redirectToRoute('auth/forgotemail');
        }

        if (!$this->authModel()->databaseConnected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotemail');
        }

        $now = $this->formatDateTime();
        $requestIp = $this->requestClientIp();
        $userAgent = $this->requestUserAgent();
        $matchesCount = 0;

        try {
            $this->assertEmailRecoveryStorageReady();
            $this->assertRecoveryRequestStorageReady();

            if (!$this->canSendRecoveryRequest('email_recovery', $recoveryEmail, $requestIp)) {
                $this->authModel()->registerRecoveryRequest(
                    'email_recovery',
                    $recoveryEmail,
                    0,
                    $requestIp,
                    $userAgent,
                    $now
                );
                flash('error', $this->t(
                    'auth.flash_email_recovery_rate_limited',
                    'Muitas solicitacoes recentes. Aguarde alguns minutos e tente novamente.'
                ));
                $this->redirectToRoute('auth/forgotemail');
            }

            $accounts = $this->authModel()->resolveEmailRecoveryUsersByRecoveryEmail($recoveryEmail, 10);
            $matchesCount = count($accounts);
            if ($matchesCount > 0) {
                $emailSent = $this->sendEmailAccessReminder($recoveryEmail, $accounts);
                if (!$emailSent) {
                    error_log('[Solis] Falha ao enviar lembrete de e-mail de acesso para: ' . $recoveryEmail);
                }
            }

            $this->authModel()->registerRecoveryRequest(
                'email_recovery',
                $recoveryEmail,
                $matchesCount,
                $requestIp,
                $userAgent,
                $now
            );
        } catch (\Throwable) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotemail');
        }

        flash('success', $this->t(
            'auth.flash_email_recovery_sent',
            'Se o e-mail de recuperacao estiver vinculado a alguma conta, enviaremos um lembrete de acesso.'
        ));
        $this->redirectToRoute('auth/login');
    }

    private function assertEmailRecoveryStorageReady(): void
    {
        if ($this->emailRecoveryStorageChecked) {
            return;
        }

        if (!$this->authModel()->usersRecoveryEmailColumnExists()) {
            throw new \RuntimeException(
                'Coluna users.recovery_email ausente. Execute a migracao operacional para habilitar lembrete de e-mail.'
            );
        }

        $this->emailRecoveryStorageChecked = true;
    }
}
