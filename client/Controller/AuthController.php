<?php

namespace Client\Controller;

use System\Library\SubscriptionService;

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
            flash('error', $this->t('auth.flash_invalid_request', 'Requisicao invalida.'));
            $this->redirectAfterLoginFailure();
        }

        $email = trim((string) $this->request->post('email'));
        $password = (string) $this->request->post('password');

        if (!$this->auth->attempt($email, $password)) {
            $message = trim($this->auth->lastErrorMessage());
            flash('error', $message !== '' ? $message : $this->t('auth.flash_invalid_credentials', 'Credenciais invalidas.'));
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
        flash('success', $this->t('auth.flash_logout_success', 'Sessao encerrada.'));
        $this->redirectToRoute('auth/login');
    }

    public function register(): void
    {
        if ($this->auth->check()) {
            $this->redirectToRoute('dashboard/index');
        }

        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('auth/register', [
            'title' => $this->t('auth.title_register', '{app} | Criar conta', ['app' => $appName]),
        ]);
    }

    public function createAccount(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('auth.flash_invalid_request', 'Requisicao invalida.'));
            $this->redirectToRoute('auth/register');
        }

        $name = trim((string) $this->request->post('name', ''));
        $email = strtolower(trim((string) $this->request->post('email', '')));
        $password = (string) $this->request->post('password', '');
        $passwordConfirmation = (string) $this->request->post('password_confirmation', '');

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            flash('error', $this->t('auth.flash_register_name_invalid', 'Informe um nome valido (3 a 120 caracteres).'));
            $this->redirectToRoute('auth/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('auth.flash_register_email_invalid', 'Informe um e-mail valido.'));
            $this->redirectToRoute('auth/register');
        }

        if (strlen($password) < 8) {
            flash('error', $this->t('auth.flash_register_password_short', 'A senha deve conter no minimo 8 caracteres.'));
            $this->redirectToRoute('auth/register');
        }

        if (!hash_equals($password, $passwordConfirmation)) {
            flash('error', $this->t('auth.flash_register_password_mismatch', 'A confirmacao da senha nao confere.'));
            $this->redirectToRoute('auth/register');
        }

        if (!$this->db->connected()) {
            flash('error', $this->t('auth.flash_register_unavailable', 'Cadastro temporariamente indisponivel.'));
            $this->redirectToRoute('auth/register');
        }

        $existing = $this->db->fetch(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
        if ($existing) {
            flash('error', $this->t('auth.flash_register_email_exists', 'Este e-mail ja esta cadastrado.'));
            $this->redirectToRoute('auth/register');
        }

        $groupId = $this->resolveClientGroupId();
        if ($groupId <= 0) {
            flash('error', $this->t('auth.flash_register_group_missing', 'Grupo de clientes nao encontrado. Contate o suporte.'));
            $this->redirectToRoute('auth/register');
        }

        $createdUserId = 0;
        $timestamp = date('Y-m-d H:i:s');
        $subscription = new SubscriptionService($this->registry);

        try {
            $subscription->ensureTables();
            $this->db->beginTransaction();
            $createdUserId = $this->db->insert('users', [
                'user_group_id' => $groupId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'avatar' => null,
                'language_code' => 'pt-br',
                'status' => 1,
                'last_login_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $subscription->ensureUserSubscription($createdUserId);

            $this->db->commit();
        } catch (\Throwable) {
            $this->db->rollBack();
            flash('error', $this->t('auth.flash_register_unavailable', 'Cadastro temporariamente indisponivel.'));
            $this->redirectToRoute('auth/register');
        }

        if ($createdUserId <= 0) {
            flash('error', $this->t('auth.flash_register_unavailable', 'Cadastro temporariamente indisponivel.'));
            $this->redirectToRoute('auth/register');
        }

        if ($this->auth->attempt($email, $password)) {
            flash('success', $this->t(
                'auth.flash_register_success',
                'Conta criada com sucesso. Seu plano Basico Gratuito ja esta ativo.'
            ));
            $this->redirectToRoute('dashboard/index');
        }

        flash('success', $this->t(
            'auth.flash_register_success_login',
            'Conta criada com sucesso. Faca login para acessar seu plano Basico Gratuito.'
        ));
        $this->redirectToRoute('auth/login');
    }

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

        if (!$this->db->connected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        try {
            $this->ensurePasswordResetTable();
            $this->purgeExpiredPasswordResets();

            $user = $this->resolvePasswordRecoveryUser($email);
            if ($user !== null) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $now = date('Y-m-d H:i:s');
                $expiresAt = date('Y-m-d H:i:s', time() + $this->passwordResetTtlSeconds());

                $this->db->execute(
                    'UPDATE password_resets
                     SET used_at = :used_at, updated_at = :updated_at
                     WHERE user_id = :user_id
                       AND used_at IS NULL',
                    [
                        'used_at' => $now,
                        'updated_at' => $now,
                        'user_id' => (int) $user['id'],
                    ]
                );

                $this->db->insert('password_resets', [
                    'user_id' => (int) $user['id'],
                    'email' => (string) $user['email'],
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                    'used_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

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

        if (!$this->db->connected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        try {
            $this->ensurePasswordResetTable();
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

        if (!$this->db->connected()) {
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->redirectToRoute('auth/forgotpassword');
        }

        try {
            $this->ensurePasswordResetTable();
            $this->purgeExpiredPasswordResets();
            $reset = $this->findValidPasswordReset($token);

            if ($reset === null) {
                flash('error', $this->t('auth.flash_password_reset_invalid_token', 'Link de recuperacao invalido ou expirado.'));
                $this->redirectToRoute('auth/forgotpassword');
            }

            $now = date('Y-m-d H:i:s');

            $this->db->beginTransaction();
            $rowsUpdated = $this->db->update(
                'users',
                [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'updated_at' => $now,
                ],
                'id = :id AND status = 1',
                ['id' => (int) $reset['user_id']]
            );

            if ($rowsUpdated <= 0) {
                throw new \RuntimeException('Usuario nao encontrado para redefinicao.');
            }

            $this->db->update(
                'password_resets',
                [
                    'used_at' => $now,
                    'updated_at' => $now,
                ],
                'id = :id',
                ['id' => (int) $reset['id']]
            );

            $this->db->execute(
                'UPDATE password_resets
                 SET used_at = :used_at, updated_at = :updated_at
                 WHERE user_id = :user_id
                   AND used_at IS NULL
                   AND id <> :id',
                [
                    'used_at' => $now,
                    'updated_at' => $now,
                    'user_id' => (int) $reset['user_id'],
                    'id' => (int) $reset['id'],
                ]
            );

            $this->db->commit();
        } catch (\Throwable) {
            $this->db->rollBack();
            flash('error', $this->t('auth.flash_password_recovery_unavailable', 'Recuperacao de senha temporariamente indisponivel.'));
            $this->response->redirect(route_url('auth/resetpassword') . '?token=' . urlencode($token));
        }

        flash('success', $this->t('auth.flash_password_reset_success', 'Senha redefinida com sucesso. Faca login com a nova senha.'));
        $this->redirectToRoute('auth/login');
    }

    private function ensurePasswordResetTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_password_resets_user (user_id),
                INDEX idx_password_resets_email (email),
                INDEX idx_password_resets_token (token_hash),
                INDEX idx_password_resets_expires (expires_at),
                CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function purgeExpiredPasswordResets(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'DELETE FROM password_resets
             WHERE expires_at < :now
                OR (used_at IS NOT NULL AND updated_at < DATE_SUB(:now_two, INTERVAL 1 DAY))',
            [
                'now' => $now,
                'now_two' => $now,
            ]
        );
    }

    private function resolvePasswordRecoveryUser(string $email): ?array
    {
        $user = $this->db->fetch(
            'SELECT u.id, u.name, u.email, ug.permissions_json
             FROM users u
             LEFT JOIN user_groups ug ON ug.id = u.user_group_id
             WHERE u.email = :email
               AND u.status = 1
             LIMIT 1',
            ['email' => $email]
        );

        if (!$user) {
            return null;
        }

        $permissionsJson = (string) ($user['permissions_json'] ?? '[]');
        if (!$this->hasClientAccessPermission($permissionsJson)) {
            return null;
        }

        return $user;
    }

    private function hasClientAccessPermission(string $permissionsJson): bool
    {
        $permissions = json_decode($permissionsJson, true);
        if (!is_array($permissions)) {
            return false;
        }

        if (in_array('*', $permissions, true) || in_array('client.*', $permissions, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            if (str_starts_with($permission, 'client.')) {
                return true;
            }
        }

        return false;
    }

    private function findValidPasswordReset(string $token): ?array
    {
        if (!$this->isValidPasswordResetToken($token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        return $this->db->fetch(
            'SELECT id, user_id, email, expires_at
             FROM password_resets
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at >= :now
             LIMIT 1',
            [
                'token_hash' => $tokenHash,
                'now' => date('Y-m-d H:i:s'),
            ]
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

    private function buildPasswordResetLink(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/[\r\n]+/', '', $host) ?? 'localhost';

        return $scheme . '://' . $host . route_url('auth/resetpassword') . '?token=' . urlencode($token);
    }

    private function sendPasswordRecoveryEmail(string $toEmail, string $toName, string $resetLink, string $expiresAt): bool
    {
        if (!function_exists('mail')) {
            return false;
        }

        $appName = (string) $this->config->get('app.name', 'Solis');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', $host) ?? 'localhost';
        $host = preg_replace('/[\r\n]+/', '', $host) ?? 'localhost';

        $fromEmail = (string) $this->config->get('security.auth.password_reset_from_email', 'no-reply@' . $host);
        $fromName = (string) $this->config->get('security.auth.password_reset_from_name', $appName);
        $expiryLabel = date('d/m/Y H:i', strtotime($expiresAt));
        $safeName = trim($toName) !== '' ? $toName : $toEmail;

        $subject = $this->t(
            'auth.mail_password_reset_subject',
            'Recuperacao de senha - {app}',
            ['app' => $appName]
        );

        $body = $this->t(
            'auth.mail_password_reset_body',
            "Ola {name},\n\nRecebemos uma solicitacao para redefinir a senha da sua conta em {app}.\n\nUse este link para criar uma nova senha:\n{link}\n\nEste link expira em: {expires_at}\n\nSe voce nao solicitou a redefinicao, ignore este e-mail.\n",
            [
                'name' => $safeName,
                'app' => $appName,
                'link' => $resetLink,
                'expires_at' => $expiryLabel,
            ]
        );

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->formatEmailHeader($fromName, $fromEmail),
            'Reply-To: ' . $fromEmail,
        ];

        return (bool) @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }

    private function formatEmailHeader(string $name, string $email): string
    {
        $safeEmail = preg_replace('/[\r\n]+/', '', trim($email)) ?? '';
        $safeName = trim(preg_replace('/[\r\n]+/', '', $name) ?? '');

        if ($safeName === '') {
            return $safeEmail;
        }

        return sprintf('"%s" <%s>', addslashes($safeName), $safeEmail);
    }

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $localLength = strlen($localPart);

        if ($localLength <= 2) {
            $maskedLocal = substr($localPart, 0, 1) . '*';
        } else {
            $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', max(1, $localLength - 2));
        }

        return $maskedLocal . '@' . $domain;
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

    private function resolveClientGroupId(): int
    {
        $group = $this->db->fetch(
            "SELECT id
             FROM user_groups
             WHERE name = 'Clientes'
             LIMIT 1"
        );
        if ($group) {
            return (int) ($group['id'] ?? 0);
        }

        $group = $this->db->fetch(
            "SELECT id
             FROM user_groups
             WHERE permissions_json LIKE '%client.%'
             ORDER BY hierarchy_level DESC, id ASC
             LIMIT 1"
        );

        return (int) ($group['id'] ?? 0);
    }
}
