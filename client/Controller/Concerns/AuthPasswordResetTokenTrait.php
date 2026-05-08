<?php

namespace Client\Controller\Concerns;

trait AuthPasswordResetTokenTrait
{
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
