<?php

namespace Client\Controller\Concerns;

trait SocialConnectionManualFlowTrait
{
    public function saveManualConnection(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $platform = strtolower(trim((string) $this->request->post('platform_slug', '')));
        $accountName = trim((string) $this->request->post('account_name', ''));
        $accessToken = trim((string) $this->request->post('access_token', ''));
        $refreshToken = trim((string) $this->request->post('refresh_token', ''));
        $expiresAt = trim((string) $this->request->post('token_expires_at', ''));
        $manualAction = strtolower(trim((string) $this->request->post('manual_action', 'save')));
        if (!in_array($manualAction, ['save', 'verify'], true)) {
            $manualAction = 'save';
        }

        if ($platform === '' || $accessToken === '') {
            flash('error', $this->t('social.flash_manual_missing_fields', 'Informe plataforma e access token para salvar a conexao manual.'));
            $this->redirectToRoute('social/index');
        }

        $registry = $this->socialPlatformRegistry();
        $target = $registry->get($platform);
        if (!$target || !($target['enabled'] ?? false)) {
            flash('error', $this->t('social.flash_platform_invalid', 'Plataforma invalida.'));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_social_connections');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        if ($manualAction !== 'verify') {
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
        }

        $tokenExpiresAt = null;
        if ($expiresAt !== '') {
            $expiresTs = $this->parseDateToTimestamp($expiresAt);
            if ($expiresTs !== null) {
                $tokenExpiresAt = $this->formatDateTime('Y-m-d H:i:s', $expiresTs);
            }
        }

        $validation = $this->validateManualToken($target, $accessToken, $tokenExpiresAt);
        if ($manualAction === 'verify') {
            $flashType = $validation['status'] === 'valid' ? 'success' : 'error';
            flash($flashType, $this->t(
                'social.flash_manual_verified',
                'Resultado da verificacao para {platform}: {status}. {details}',
                [
                    'platform' => (string) ($target['name'] ?? $platform),
                    'status' => (string) ($validation['label'] ?? ''),
                    'details' => (string) ($validation['message'] ?? ''),
                ]
            ));
            $this->redirectToRoute('social/index');
        }

        $this->loader->model('social')->upsertConnection($userId, $platform, [
            'account_name' => $accountName,
            'platform_user_id' => '',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'scopes_text' => '',
            'token_expires_at' => $tokenExpiresAt,
            'status' => 'manual',
            'metadata' => [
                'kind' => 'manual',
                'validation_status' => (string) ($validation['status'] ?? ''),
                'validation_label' => (string) ($validation['label'] ?? ''),
                'validation_message' => (string) ($validation['message'] ?? ''),
                'validation_checked_at' => (string) ($validation['checked_at'] ?? ''),
                'validation_method' => (string) ($validation['method'] ?? ''),
            ],
        ]);

        $security = $this->registry->get('security');
        if (is_object($security)) {
            $security->audit('social_connect_success', 'info', $userId, 'client', [
                'platform' => $platform,
                'kind' => 'manual',
            ]);
        }

        $savedMessage = $this->t(
            'social.flash_manual_saved',
            'Conexao manual salva para {platform}.',
            ['platform' => ($target['name'] ?? $platform)]
        );
        if (trim((string) ($validation['message'] ?? '')) !== '') {
            $savedMessage .= ' ' . (string) $validation['message'];
        }

        flash($validation['status'] === 'invalid' ? 'error' : 'success', $savedMessage);
        $this->redirectToRoute('social/index');
    }

    public function disconnect(string $platform): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $platform = strtolower(trim($platform));
        if ($platform === '') {
            flash('error', $this->t('social.flash_platform_invalid', 'Plataforma invalida.'));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $this->loader->model('social')->disconnectConnection($userId, $platform);

        $security = $this->registry->get('security');
        if (is_object($security)) {
            $security->audit('social_disconnect', 'warning', $userId, 'client', [
                'platform' => $platform,
            ]);
        }

        flash('success', $this->t(
            'social.flash_disconnected',
            'Conexao removida para {platform}.',
            ['platform' => $platform]
        ));
        $this->redirectToRoute('social/index');
    }
}
