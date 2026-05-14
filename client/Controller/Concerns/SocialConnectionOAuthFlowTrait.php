<?php

namespace Client\Controller\Concerns;

trait SocialConnectionOAuthFlowTrait
{
    public function connectAll(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_social_connections');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $selected = $this->sanitizePlatformSlugs((array) $this->request->post('platforms', []));
        if ($selected === []) {
            flash('error', $this->t(
                'social.flash_bulk_select_platform',
                'Selecione ao menos uma rede para iniciar a conexao rapida.'
            ));
            $this->redirectToRoute('social/index');
        }

        $registry = $this->socialPlatformRegistry();
        $all = $registry->all();
        $queue = [];

        foreach ($selected as $slug) {
            $target = $all[$slug] ?? null;
            if (!$target || !($target['enabled'] ?? false)) {
                continue;
            }

            if (($target['kind'] ?? '') !== 'oauth2') {
                continue;
            }

            if (trim((string) ($target['client_id'] ?? '')) === '' || trim((string) ($target['client_secret'] ?? '')) === '') {
                continue;
            }

            $queue[] = $slug;
        }

        $queue = $this->sanitizePlatformSlugs($queue);
        if ($queue === []) {
            flash('error', $this->t(
                'social.flash_bulk_no_oauth_ready',
                'Nenhuma rede OAuth selecionada esta pronta para conexao. Revise as credenciais e tente novamente.'
            ));
            $this->redirectToRoute('social/index');
        }

        $this->session->set('social_oauth_bulk_queue', $queue);
        $this->session->set('social_oauth_bulk_success', []);
        $this->session->set('social_oauth_bulk_failed', []);

        $first = (string) ($queue[0] ?? '');
        if ($first === '') {
            flash('error', $this->t('social.flash_oauth_start_error', 'Nao foi possivel iniciar autorizacao para esta plataforma.'));
            $this->redirectToRoute('social/index');
        }

        $this->response->redirect(route_url('social/connect/' . rawurlencode($first) . '?bulk=1'));
    }

    public function connectFailed(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $failed = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_failed', []));
        if ($failed === []) {
            flash('error', $this->t(
                'social.flash_bulk_no_failed',
                'Nao ha redes com falha para repetir agora.'
            ));
            $this->redirectToRoute('social/index');
        }

        $this->session->set('social_oauth_bulk_queue', $failed);
        $this->session->set('social_oauth_bulk_success', []);
        $this->session->set('social_oauth_bulk_failed', []);

        $first = (string) ($failed[0] ?? '');
        if ($first === '') {
            flash('error', $this->t('social.flash_oauth_start_error', 'Nao foi possivel iniciar autorizacao para esta plataforma.'));
            $this->redirectToRoute('social/index');
        }

        $this->response->redirect(route_url('social/connect/' . rawurlencode($first) . '?bulk=1'));
    }

    public function connect(string $platform): void
    {
        $this->boot('client.social');

        $platform = strtolower(trim($platform));
        $bulkMode = $this->isBulkRequest() && $this->hasBulkQueuePlatform($platform);
        $registry = $this->socialPlatformRegistry();
        $target = $registry->get($platform);

        if (!$target || !($target['enabled'] ?? false)) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_platform_unavailable', 'Plataforma social indisponivel.'));
            $this->redirectToRoute('social/index');
        }

        if (($target['kind'] ?? '') !== 'oauth2') {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_platform_manual_only', 'Esta plataforma usa conexao manual por token.'));
            $this->redirectToRoute('social/index');
        }

        if (trim((string) ($target['client_id'] ?? '')) === '' || trim((string) ($target['client_secret'] ?? '')) === '') {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

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
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

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
                if ($bulkMode) {
                    $this->advanceBulkFlow($platform, false);
                }

                flash('error', (string) ($quota['message'] ?? 'Limite de conexoes sociais atingido para o plano atual.'));
                $this->redirectToRoute('billing/index');
            }
        }

        $stateToken = bin2hex(random_bytes(24));
        $statePayload = [
            'user_id' => (int) ($this->auth->user()['id'] ?? 0),
            'platform' => $platform,
            'created_at' => $this->nowUnixTime(),
            'bulk_mode' => $bulkMode,
        ];

        $redirectUri = $this->absoluteRoute('social/callback/' . rawurlencode($platform));
        $oauth = $this->socialAuthService();
        $authUrl = $oauth->buildAuthorizationUrl($target, $redirectUri, $stateToken, $statePayload);
        if ($authUrl === null) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

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

        $statePayload = [];
        if ($state !== '') {
            $statePayload = $this->session->get('social_oauth_state_' . $state, []);
            $this->session->remove('social_oauth_state_' . $state);
        }

        $bulkMode = $this->isBulkContext($platform, is_array($statePayload) ? $statePayload : []);

        if ($oauthError !== '') {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t(
                'social.flash_oauth_denied',
                'Autorizacao cancelada ou negada pela plataforma: {error}',
                ['error' => $oauthError]
            ));
            $this->redirectToRoute('social/index');
        }

        if ($state === '' || $code === '') {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_oauth_invalid_return', 'Retorno OAuth invalido.'));
            $this->redirectToRoute('social/index');
        }

        if (!is_array($statePayload) || empty($statePayload['platform']) || (string) $statePayload['platform'] !== $platform) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_oauth_invalid_state', 'Estado OAuth invalido.'));
            $this->redirectToRoute('social/index');
        }

        $stateCreatedAt = (int) ($statePayload['created_at'] ?? 0);
        if ($stateCreatedAt <= 0 || ($this->nowUnixTime() - $stateCreatedAt) > 900) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_oauth_expired_state', 'Estado OAuth expirado. Inicie a conexao novamente.'));
            $this->redirectToRoute('social/index');
        }

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        if ((int) ($statePayload['user_id'] ?? 0) !== $userId) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

            flash('error', $this->t('social.flash_oauth_invalid_session', 'Sessao de autenticacao social invalida.'));
            $this->redirectToRoute('social/index');
        }

        $registry = $this->socialPlatformRegistry();
        $target = $registry->get($platform);
        if (!$target) {
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

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
            if ($bulkMode) {
                $this->advanceBulkFlow($platform, false);
            }

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

        if ($bulkMode) {
            $this->advanceBulkFlow($platform, true);
        }

        flash('success', $this->t(
            'social.flash_connected',
            'Conexao com {platform} concluida.',
            ['platform' => ($target['name'] ?? $platform)]
        ));
        $this->redirectToRoute('social/index');
    }

    private function isBulkRequest(): bool
    {
        return trim((string) $this->request->get('bulk', '')) === '1';
    }

    private function isBulkContext(string $platform, array $statePayload): bool
    {
        if (!empty($statePayload['bulk_mode'])) {
            return true;
        }

        return $this->hasBulkQueuePlatform($platform);
    }

    private function hasBulkQueuePlatform(string $platform): bool
    {
        $queue = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_queue', []));

        return in_array($platform, $queue, true);
    }

    private function advanceBulkFlow(string $platform, bool $success): never
    {
        $platform = strtolower(trim($platform));
        $this->rememberBulkResult($platform, $success);

        $queue = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_queue', []));
        $remaining = [];
        $removedCurrent = false;
        foreach ($queue as $slug) {
            if (!$removedCurrent && $slug === $platform) {
                $removedCurrent = true;
                continue;
            }

            $remaining[] = $slug;
        }

        $this->session->set('social_oauth_bulk_queue', $remaining);
        $next = (string) ($remaining[0] ?? '');
        if ($next !== '') {
            $this->response->redirect(route_url('social/connect/' . rawurlencode($next) . '?bulk=1'));
        }

        $successSlugs = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_success', []));
        $failedSlugs = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_failed', []));
        $this->session->remove('social_oauth_bulk_queue');
        $this->session->remove('social_oauth_bulk_success');
        $this->session->set('social_oauth_bulk_failed', $failedSlugs);

        if ($failedSlugs === []) {
            flash('success', $this->t(
                'social.flash_bulk_done_success',
                'Conexao em lote concluida. Redes conectadas: {count}.',
                ['count' => (string) count($successSlugs)]
            ));
            $this->redirectToRoute('social/index');
        }

        $failedNames = $this->platformNames($failedSlugs);
        flash('error', $this->t(
            'social.flash_bulk_done_partial',
            'Conexao em lote concluida com falhas. Conectadas: {success}, falhas: {failed} ({platforms}).',
            [
                'success' => (string) count($successSlugs),
                'failed' => (string) count($failedSlugs),
                'platforms' => implode(', ', $failedNames),
            ]
        ));
        $this->redirectToRoute('social/index');
    }

    private function rememberBulkResult(string $platform, bool $success): void
    {
        if ($platform === '') {
            return;
        }

        $successSlugs = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_success', []));
        $failedSlugs = $this->sanitizePlatformSlugs((array) $this->session->get('social_oauth_bulk_failed', []));

        if ($success) {
            if (!in_array($platform, $successSlugs, true)) {
                $successSlugs[] = $platform;
            }
            $failedSlugs = array_values(array_filter($failedSlugs, static fn (string $slug): bool => $slug !== $platform));
        } else {
            if (!in_array($platform, $failedSlugs, true)) {
                $failedSlugs[] = $platform;
            }
        }

        $this->session->set('social_oauth_bulk_success', $successSlugs);
        $this->session->set('social_oauth_bulk_failed', $failedSlugs);
    }

    private function sanitizePlatformSlugs(array $slugs): array
    {
        $result = [];
        foreach ($slugs as $slug) {
            $normalized = strtolower(trim((string) $slug));
            if ($normalized === '' || in_array($normalized, $result, true)) {
                continue;
            }

            $result[] = $normalized;
        }

        return $result;
    }

    private function platformNames(array $slugs): array
    {
        $all = $this->socialPlatformRegistry()->all();
        $names = [];
        foreach ($slugs as $slug) {
            $label = trim((string) (($all[$slug]['name'] ?? '') ?: $slug));
            if ($label === '') {
                continue;
            }

            $names[] = $label;
        }

        return $names;
    }
}
