<?php

namespace Client\Controller\Concerns;

trait AuthPasswordResetFlowTrait
{
    use AuthRequestMetadataTrait;

    private bool $emailRecoveryStorageChecked = false;

    public function forgotPassword(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/forgot_password', [
            'title' => $this->t('auth.title_forgot_password', '{app} | Recuperar senha', ['app' => $appName]),
        ]);
    }

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

    public function sendPasswordReset(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisicao invalida.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        $email = strtolower(trim((string) $this->request->post('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('auth.flash_password_recovery_email_invalid', 'Informe um e-mail valido para recuperacao.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        if (!$this->authModel()->databaseConnected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        $now = $this->formatDateTime();
        $requestIp = $this->requestClientIp();
        $userAgent = $this->requestUserAgent();
        $matchesCount = 0;

        try {
            $this->assertPasswordResetStorageReady();
            $this->assertRecoveryRequestStorageReady();
            $this->purgeExpiredPasswordResets();

            if (!$this->canSendRecoveryRequest('password_reset', $email, $requestIp)) {
                $this->authModel()->registerRecoveryRequest(
                    'password_reset',
                    $email,
                    0,
                    $requestIp,
                    $userAgent,
                    $now
                );
                flash('error', $this->t(
                    'auth.flash_password_recovery_rate_limited',
                    'Muitas solicitacoes recentes. Aguarde alguns minutos e tente novamente.'
                ));
                $this->redirectToRoute('auth/forgotpassword');
            }

            $user = $this->resolvePasswordRecoveryUser($email);
            if ($user !== null) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = $this->formatDateTime('Y-m-d H:i:s', $this->nowUnixTime() + $this->passwordResetTtlSeconds());
                $matchesCount = 1;

                $this->authModel()->markOpenPasswordResetsAsUsed((int) $user['id'], $now);
                $this->authModel()->createPasswordReset(
                    (int) $user['id'],
                    (string) $user['email'],
                    $tokenHash,
                    $expiresAt,
                    $now
                );

                $resetLink = $this->buildPasswordResetLink($token);
                $emailSent = $this->sendPasswordRecoveryEmail(
                    (string) $user['email'],
                    (string) $user['name'],
                    $resetLink,
                    $expiresAt
                );

                if (!$emailSent) {
                    error_log('[Solis] Falha ao enviar e-mail de recuperacao para: ' . (string) $user['email']);
                }
            }

            $this->authModel()->registerRecoveryRequest(
                'password_reset',
                $email,
                $matchesCount,
                $requestIp,
                $userAgent,
                $now
            );
        } catch (\Throwable) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        flash('success', $this->t(
            'auth.flash_password_recovery_sent',
            'Se o e-mail estiver cadastrado, voce recebera as instrucoes para redefinir a senha.'
        ));
        $this->redirectToRoute('auth/login');
    }

    public function resetPassword(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $token = trim((string) $this->request->get('token', ''));
        if (!$this->isValidPasswordResetToken($token)) {
            flash('error', $this->t('auth.flash_password_reset_invalid_token', 'Link de recuperacao invalido ou expirado.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        if (!$this->authModel()->databaseConnected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        try {
            $this->assertPasswordResetStorageReady();
            $this->purgeExpiredPasswordResets();
            $reset = $this->findValidPasswordReset($token);
        } catch (\Throwable) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        if ($reset === null) {
            flash('error', $this->t('auth.flash_password_reset_invalid_token', 'Link de recuperacao invalido ou expirado.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/reset_password', [
            'title' => $this->t('auth.title_reset_password', '{app} | Redefinir senha', ['app' => $appName]),
            'reset_token' => $token,
            'reset_email_masked' => $this->maskEmail((string) ($reset['email'] ?? '')),
        ]);
    }

    public function updatePassword(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisicao invalida.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        $token = trim((string) $this->request->post('token', ''));
        $password = (string) $this->request->post('password', '');
        $passwordConfirmation = (string) $this->request->post('password_confirmation', '');

        if (!$this->isValidPasswordResetToken($token)) {
            flash('error', $this->t('auth.flash_password_reset_invalid_token', 'Link de recuperacao invalido ou expirado.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        if (strlen($password) < 8) {
            flash('error', $this->t('auth.flash_register_password_short', 'A senha deve conter no minimo 8 caracteres.'));
            $this->response->redirect(route_url('auth/resetpassword') . '?token=' . urlencode($token));
        }

        if (!hash_equals($password, $passwordConfirmation)) {
            flash('error', $this->t('auth.flash_register_password_mismatch', 'A confirmacao da senha nao confere.'));
            $this->response->redirect(route_url('auth/resetpassword') . '?token=' . urlencode($token));
        }

        if (!$this->authModel()->databaseConnected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        try {
            $this->assertPasswordResetStorageReady();
            $this->purgeExpiredPasswordResets();
            $reset = $this->findValidPasswordReset($token);

            if ($reset === null) {
                flash('error', $this->t('auth.flash_password_reset_invalid_token', 'Link de recuperacao invalido ou expirado.'));
                $this->redirectToRoute('auth/forgotpassword');
            }

            $now = $this->formatDateTime();
            $this->authModel()->applyPasswordReset(
                (int) $reset['user_id'],
                (int) $reset['id'],
                password_hash($password, PASSWORD_DEFAULT),
                $now
            );
        } catch (\Throwable) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->response->redirect(route_url('auth/resetpassword') . '?token=' . urlencode($token));
        }

        flash('success', $this->t('auth.flash_password_reset_success', 'Senha redefinida com sucesso. Faca login com a nova senha.'));
        $this->redirectToRoute('auth/login');
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

    private function assertPasswordResetStorageReady(): void
    {
        if ($this->passwordResetStorageChecked) {
            return;
        }

        if ($this->authModel()->passwordResetTableExists()) {
            $this->passwordResetStorageChecked = true;
            return;
        }

        $runtimeMutationsEnabled = (bool) $this->config->get('security.runtime_schema_mutations', false);
        $message = $runtimeMutationsEnabled
            ? 'Tabela password_resets ausente. Execute o instalador/migracoes para criar o schema.'
            : 'Tabela password_resets ausente e security.runtime_schema_mutations=false.';

        throw new \RuntimeException($message);
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

    private function assertRecoveryRequestStorageReady(): void
    {
        if ($this->authModel()->authRecoveryRequestsTableExists()) {
            return;
        }

        throw new \RuntimeException(
            'Tabela auth_recovery_requests ausente. Execute a migracao operacional para habilitar rate limit de recuperacao.'
        );
    }

    private function canSendRecoveryRequest(string $requestType, string $identifierEmail, string $requestIp): bool
    {
        $requestType = strtolower(trim($requestType));
        if (!in_array($requestType, ['password_reset', 'email_recovery'], true)) {
            return false;
        }

        $configKey = $requestType === 'email_recovery'
            ? 'security.auth.email_recovery_max_requests_per_hour'
            : 'security.auth.password_recovery_max_requests_per_hour';

        $maxPerHour = (int) $this->config->get($configKey, 5);
        $maxPerHour = max(1, min(50, $maxPerHour));
        $windowStart = $this->formatDateTime('Y-m-d H:i:s', $this->nowUnixTime() - 3600);
        $recent = $this->authModel()->countRecentRecoveryRequests(
            $requestType,
            strtolower(trim($identifierEmail)),
            $requestIp,
            $windowStart
        );

        return $recent < $maxPerHour;
    }

    private function purgeExpiredPasswordResets(): void
    {
        $now = $this->formatDateTime();
        $this->authModel()->purgeExpiredPasswordResets($now);
    }

    private function resolvePasswordRecoveryUser(string $email): ?array
    {
        return $this->authModel()->resolvePasswordRecoveryUser($email);
    }

    private function findValidPasswordReset(string $token): ?array
    {
        if (!$this->isValidPasswordResetToken($token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        return $this->authModel()->findValidPasswordResetByTokenHash(
            $tokenHash,
            $this->formatDateTime()
        );
    }

    private function isValidPasswordResetToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function passwordResetTtlSeconds(): int
    {
        $ttlMinutes = (int) $this->config->get('security.auth.password_reset_ttl_minutes', 60);
        $ttlMinutes = max(10, min(1440, $ttlMinutes));

        return $ttlMinutes * 60;
    }
}
