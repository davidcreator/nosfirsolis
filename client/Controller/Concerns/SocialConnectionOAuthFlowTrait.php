<?php

namespace Client\Controller\Concerns;

trait SocialConnectionOAuthFlowTrait
{
    public function connect(string $platform): void
    {
        $this->boot('client.social');

        $platform = strtolower(trim($platform));
        $registry = $this->socialPlatformRegistry();
        $target = $registry->get($platform);

        if (!$target || !($target['enabled'] ?? false)) {
            flash('error', $this->t('social.flash_platform_unavailable', 'Plataforma social indisponivel.'));
            $this->redirectToRoute('social/index');
        }

        if (($target['kind'] ?? '') !== 'oauth2') {
            flash('error', $this->t('social.flash_platform_manual_only', 'Esta plataforma usa conexao manual por token.'));
            $this->redirectToRoute('social/index');
        }

        if (trim((string) ($target['client_id'] ?? '')) === '' || trim((string) ($target['client_secret'] ?? '')) === '') {
            flash('error', $this->t(
                'social.flash_oauth_credentials_missing',
                'Credenciais OAuth nao configuradas para {platform}.',
                ['platform' => ($target['name'] ?? $platform)]
            ));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_social_connections');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $connections = $this->loader->model('social')->connectionsByUser($userId);
        $alreadyConnected = false;
        foreach ($connections as $connection) {
            if (
                strtolower((string) ($connection['platform_slug'] ?? '')) === $platform
                && in_array((string) ($connection['status'] ?? ''), ['connected', 'manual'], true)
            ) {
                $alreadyConnected = true;
                break;
            }
        }

        if (!$alreadyConnected) {
            $quota = $subscription->evaluateQuota($userId, 'max_social_accounts', 1);
            if (empty($quota['allowed'])) {
                flash('error', (string) ($quota['message'] ?? 'Limite de conexoes sociais atingido para o plano atual.'));
                $this->redirectToRoute('billing/index');
            }
        }

        $stateToken = bin2hex(random_bytes(24));
        $statePayload = [
            'user_id' => (int) ($this->auth->user()['id'] ?? 0),
            'platform' => $platform,
            'created_at' => $this->nowUnixTime(),
        ];

        $redirectUri = $this->absoluteRoute('social/callback/' . rawurlencode($platform));
        $oauth = $this->socialAuthService();
        $authUrl = $oauth->buildAuthorizationUrl($target, $redirectUri, $stateToken, $statePayload);
        if ($authUrl === null) {
            flash('error', $this->t('social.flash_oauth_start_error', 'Nao foi possivel iniciar autorizacao para esta plataforma.'));
            $this->redirectToRoute('social/index');
        }

        $this->session->set('social_oauth_state_' . $stateToken, $statePayload);
        $this->response->redirect($authUrl);
    }

    public function callback(string $platform): void
    {
        $this->boot('client.social');

        $platform = strtolower(trim($platform));
        $state = trim((string) $this->request->get('state', ''));
        $code = trim((string) $this->request->get('code', ''));
        $oauthError = trim((string) $this->request->get('error', ''));

        if ($oauthError !== '') {
            flash('error', $this->t(
                'social.flash_oauth_denied',
                'Autorizacao cancelada ou negada pela plataforma: {error}',
                ['error' => $oauthError]
            ));
            $this->redirectToRoute('social/index');
        }

        if ($state === '' || $code === '') {
            flash('error', $this->t('social.flash_oauth_invalid_return', 'Retorno OAuth invalido.'));
            $this->redirectToRoute('social/index');
        }

        $statePayload = $this->session->get('social_oauth_state_' . $state, []);
        $this->session->remove('social_oauth_state_' . $state);

        if (!is_array($statePayload) || empty($statePayload['platform']) || (string) $statePayload['platform'] !== $platform) {
            flash('error', $this->t('social.flash_oauth_invalid_state', 'Estado OAuth invalido.'));
            $this->redirectToRoute('social/index');
        }

        $stateCreatedAt = (int) ($statePayload['created_at'] ?? 0);
        if ($stateCreatedAt <= 0 || ($this->nowUnixTime() - $stateCreatedAt) > 900) {
            flash('error', $this->t('social.flash_oauth_expired_state', 'Estado OAuth expirado. Inicie a conexao novamente.'));
            $this->redirectToRoute('social/index');
        }

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        if ((int) ($statePayload['user_id'] ?? 0) !== $userId) {
            flash('error', $this->t('social.flash_oauth_invalid_session', 'Sessao de autenticacao social invalida.'));
            $this->redirectToRoute('social/index');
        }

        $registry = $this->socialPlatformRegistry();
        $target = $registry->get($platform);
        if (!$target) {
            flash('error', $this->t('social.flash_platform_not_found', 'Plataforma social nao encontrada.'));
            $this->redirectToRoute('social/index');
        }

        $redirectUri = $this->absoluteRoute('social/callback/' . rawurlencode($platform));
        $oauth = $this->socialAuthService();
        $token = $oauth->exchangeCode(
            $target,
            $code,
            $redirectUri,
            isset($statePayload['code_verifier']) ? (string) $statePayload['code_verifier'] : null
        );

        if (empty($token['ok'])) {
            flash('error', $this->t(
                'social.flash_connect_error',
                'Falha ao conectar {platform}. Verifique as credenciais OAuth.',
                ['platform' => ($target['name'] ?? $platform)]
            ));
            $this->redirectToRoute('social/index');
        }

        $profile = $oauth->fetchProfile($target, (string) $token['access_token']);
        $accountName = '';
        $platformUserId = '';
        if (!empty($profile['ok']) && is_array($profile['data'])) {
            $accountName = (string) ($profile['data']['name'] ?? $profile['data']['username'] ?? '');
            $platformUserId = (string) ($profile['data']['id'] ?? '');
        }

        $this->loader->model('social')->upsertConnection($userId, $platform, [
            'account_name' => $accountName,
            'platform_user_id' => $platformUserId,
            'access_token' => (string) $token['access_token'],
            'refresh_token' => isset($token['refresh_token']) ? (string) $token['refresh_token'] : null,
            'scopes_text' => (string) ($token['scope_text'] ?? ''),
            'token_expires_at' => $token['expires_at'] ?? null,
            'status' => 'connected',
            'metadata' => [
                'provider' => $target['name'] ?? $platform,
                'connected_at' => $this->formatDateTime(),
            ],
        ]);

        $security = $this->registry->get('security');
        if (is_object($security)) {
            $security->audit('social_connect_success', 'info', $userId, 'client', [
                'platform' => $platform,
                'kind' => 'oauth2',
            ]);
        }

        flash('success', $this->t(
            'social.flash_connected',
            'Conexao com {platform} concluida.',
            ['platform' => ($target['name'] ?? $platform)]
        ));
        $this->redirectToRoute('social/index');
    }
}
