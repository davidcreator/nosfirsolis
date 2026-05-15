<?php

namespace Client\Controller;

use Client\Controller\Concerns\AuthPasswordResetFlowTrait;
use Client\Controller\Concerns\AuthPasswordResetMailTrait;
use Client\Model\AuthModel;

class AuthController extends BaseController
{
    use AuthPasswordResetMailTrait;
    use AuthPasswordResetFlowTrait;

    private bool $passwordResetStorageChecked = false;
    private ?AuthModel $authModelCache = null;

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
        $recoveryEmail = strtolower(trim((string) $this->request->post('recovery_email', '')));
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

        if ($recoveryEmail !== '' && !filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('auth.flash_register_recovery_email_invalid', 'Informe um e-mail de recuperacao valido.'));
            $this->redirectToRoute('auth/register');
        }

        if ($recoveryEmail === '') {
            $recoveryEmail = $email;
        }

        if (strlen($password) < 8) {
            flash('error', $this->t('auth.flash_register_password_short', 'A senha deve conter no minimo 8 caracteres.'));
            $this->redirectToRoute('auth/register');
        }

        if (!hash_equals($password, $passwordConfirmation)) {
            flash('error', $this->t('auth.flash_register_password_mismatch', 'A confirmacao da senha nao confere.'));
            $this->redirectToRoute('auth/register');
        }

        if (!$this->authModel()->databaseConnected()) {
            flash('error', $this->t('auth.flash_register_unavailable', 'Cadastro temporariamente indisponivel.'));
            $this->redirectToRoute('auth/register');
        }

        if ($this->authModel()->userExistsByEmail($email)) {
            flash('error', $this->t('auth.flash_register_email_exists', 'Este e-mail ja esta cadastrado.'));
            $this->redirectToRoute('auth/register');
        }

        $groupId = $this->resolveClientGroupId();
        if ($groupId <= 0) {
            flash('error', $this->t('auth.flash_register_group_missing', 'Grupo de clientes nao encontrado. Contate o suporte.'));
            $this->redirectToRoute('auth/register');
        }

        $createdUserId = 0;
        $timestamp = $this->formatDateTime();
        $subscription = $this->subscriptionService();

        try {
            $subscription->ensureTables();
            $createdUserId = (int) $this->authModel()->runInTransaction(function () use (
                $groupId,
                $name,
                $email,
                $recoveryEmail,
                $password,
                $timestamp,
                $subscription
            ): int {
                $newUserId = $this->authModel()->createUser([
                    'user_group_id' => $groupId,
                    'name' => $name,
                    'email' => $email,
                    'recovery_email' => $recoveryEmail,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'avatar' => null,
                    'language_code' => $this->defaultLanguageCode(),
                    'status' => 1,
                    'last_login_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $subscription->ensureUserSubscription($newUserId);

                return $newUserId;
            });
        } catch (\Throwable) {
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

    private function redirectAfterLoginFailure(): never
    {
        $this->redirectToRoute('auth/login');
    }

    private function resolveClientGroupId(): int
    {
        return $this->authModel()->resolveClientGroupId();
    }

    private function authModel(): AuthModel
    {
        if (!$this->authModelCache instanceof AuthModel) {
            /** @var AuthModel $model */
            $model = $this->loader->model('auth');
            $this->authModelCache = $model;
        }

        return $this->authModelCache;
    }
}
