<?php

namespace Client\Controller;

use System\Library\AutomationService;
use System\Library\CampaignTrackingService;
use System\Library\ContentStrategistService;
use System\Library\HostGuard;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;
use System\Library\SocialAuthService;
use System\Library\SocialFormatStandardsService;
use System\Library\SocialPlatformRegistry;
use System\Library\SocialPublishingService;
use System\Library\SubscriptionService;

class SocialController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.social');

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $registry = new SocialPlatformRegistry($this->config);
        $platforms = $registry->all();

        $socialModel = $this->loader->model('social');
        $connections = $socialModel->connectionsByUser($userId);
        $savedPresets = $socialModel->formatPresetsByUser($userId);

        $connectionsBySlug = [];
        foreach ($connections as $connection) {
            $connectionsBySlug[(string) $connection['platform_slug']] = $connection;
        }

        $standardsService = new SocialFormatStandardsService();
        $matrixRows = $standardsService->matrixRows();
        $defaultPlatform = (string) ($matrixRows[0]['slug'] ?? 'instagram');

        $selectedPlatform = strtolower(trim((string) $this->request->get('std_platform', $defaultPlatform)));
        $selectedFormat = strtolower(trim((string) $this->request->get('std_format', 'post')));
        if (!in_array($selectedFormat, ['post', 'carousel'], true)) {
            $selectedFormat = 'post';
        }

        $selectedPreset = $standardsService->presetFor($selectedPlatform, $selectedFormat);
        if ($selectedPreset === null) {
            $selectedPlatform = $defaultPlatform;
            $selectedFormat = 'post';
            $selectedPreset = $standardsService->presetFor($selectedPlatform, $selectedFormat);
        }

        $resolvedSources = [];
        if (is_array($selectedPreset)) {
            $resolvedSources = $standardsService->resolveSources((array) ($selectedPreset['source_keys'] ?? []));
        }

        $security = $this->registry->get('security');
        $events = is_object($security) ? $security->recentEvents($userId, 'client', 20) : [];

        $publishService = new SocialPublishingService($this->registry);
        $publishService->ensureTables();
        $publicationQueue = $publishService->listByUser($userId, 120);

        $trackingService = new CampaignTrackingService($this->registry);
        $publishPlanItems = $trackingService->availablePlanItems($userId, 120);

        $this->render('social/index', [
            'title' => $this->t('social.title_index', 'Central Social'),
            'platforms' => $platforms,
            'connections' => $connectionsBySlug,
            'drafts' => $socialModel->recentDrafts($userId, 12),
            'security_events' => $events,
            'standards_matrix' => $matrixRows,
            'standards_selected_platform' => $selectedPlatform,
            'standards_selected_format' => $selectedFormat,
            'standards_selected_preset' => $selectedPreset,
            'standards_selected_sources' => $resolvedSources,
            'saved_format_presets' => $savedPresets,
            'publication_queue' => $publicationQueue,
            'publish_plan_items' => $publishPlanItems,
        ]);
    }

    public function connect(string $platform): void
    {
        $this->boot('client.social');

        $platform = strtolower(trim($platform));
        $registry = new SocialPlatformRegistry($this->config);
        $target = $registry->get($platform);

        if (!$target || !($target['enabled'] ?? false)) {
            flash('error', $this->t('social.flash_platform_unavailable', 'Plataforma social indisponivel.'));
            $this->redirectToRoute('social/index');
        }

        if (($target['kind'] ?? '') !== 'oauth2') {
            flash('error', $this->t('social.flash_platform_manual_only', 'Esta plataforma usa conexão manual por token.'));
            $this->redirectToRoute('social/index');
        }

        if (trim((string) ($target['client_id'] ?? '')) === '' || trim((string) ($target['client_secret'] ?? '')) === '') {
            flash('error', $this->t(
                'social.flash_oauth_credentials_missing',
                'Credenciais OAuth não configuradas para {platform}.',
                ['platform' => ($target['name'] ?? $platform)]
            ));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
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
            'created_at' => time(),
        ];

        $redirectUri = $this->absoluteRoute('social/callback/' . rawurlencode($platform));
        $oauth = new SocialAuthService();
        $authUrl = $oauth->buildAuthorizationUrl($target, $redirectUri, $stateToken, $statePayload);
        if ($authUrl === null) {
            flash('error', $this->t('social.flash_oauth_start_error', 'Não foi possível iniciar autorização para esta plataforma.'));
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
            flash('error', $this->t('social.flash_oauth_invalid_return', 'Retorno OAuth inválido.'));
            $this->redirectToRoute('social/index');
        }

        $statePayload = $this->session->get('social_oauth_state_' . $state, []);
        $this->session->remove('social_oauth_state_' . $state);

        if (!is_array($statePayload) || empty($statePayload['platform']) || (string) $statePayload['platform'] !== $platform) {
            flash('error', $this->t('social.flash_oauth_invalid_state', 'Estado OAuth inválido.'));
            $this->redirectToRoute('social/index');
        }

        $stateCreatedAt = (int) ($statePayload['created_at'] ?? 0);
        if ($stateCreatedAt <= 0 || (time() - $stateCreatedAt) > 900) {
            flash('error', $this->t('social.flash_oauth_expired_state', 'Estado OAuth expirado. Inicie a conexão novamente.'));
            $this->redirectToRoute('social/index');
        }

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        if ((int) ($statePayload['user_id'] ?? 0) !== $userId) {
            flash('error', $this->t('social.flash_oauth_invalid_session', 'Sessão de autenticação social inválida.'));
            $this->redirectToRoute('social/index');
        }

        $registry = new SocialPlatformRegistry($this->config);
        $target = $registry->get($platform);
        if (!$target) {
            flash('error', $this->t('social.flash_platform_not_found', 'Plataforma social não encontrada.'));
            $this->redirectToRoute('social/index');
        }

        $redirectUri = $this->absoluteRoute('social/callback/' . rawurlencode($platform));
        $oauth = new SocialAuthService();
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
                'connected_at' => date('Y-m-d H:i:s'),
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
            'Conexão com {platform} concluída.',
            ['platform' => ($target['name'] ?? $platform)]
        ));
        $this->redirectToRoute('social/index');
    }

    public function saveManualConnection(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $platform = strtolower(trim((string) $this->request->post('platform_slug', '')));
        $accountName = trim((string) $this->request->post('account_name', ''));
        $accessToken = trim((string) $this->request->post('access_token', ''));
        $refreshToken = trim((string) $this->request->post('refresh_token', ''));
        $expiresAt = trim((string) $this->request->post('token_expires_at', ''));

        if ($platform === '' || $accessToken === '') {
            flash('error', $this->t('social.flash_manual_missing_fields', 'Informe plataforma e access token para salvar a conexão manual.'));
            $this->redirectToRoute('social/index');
        }

        $registry = new SocialPlatformRegistry($this->config);
        $target = $registry->get($platform);
        if (!$target || !($target['enabled'] ?? false)) {
            flash('error', $this->t('social.flash_platform_invalid', 'Plataforma invalida.'));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
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

        $tokenExpiresAt = null;
        if ($expiresAt !== '') {
            $tokenExpiresAt = date('Y-m-d H:i:s', strtotime($expiresAt));
        }

        $this->loader->model('social')->upsertConnection($userId, $platform, [
            'account_name' => $accountName,
            'platform_user_id' => '',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'scopes_text' => '',
            'token_expires_at' => $tokenExpiresAt,
            'status' => 'manual',
            'metadata' => ['kind' => 'manual'],
        ]);

        $security = $this->registry->get('security');
        if (is_object($security)) {
            $security->audit('social_connect_success', 'info', $userId, 'client', [
                'platform' => $platform,
                'kind' => 'manual',
            ]);
        }

        flash('success', $this->t(
            'social.flash_manual_saved',
            'Conexão manual salva para {platform}.',
            ['platform' => ($target['name'] ?? $platform)]
        ));
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
            'Conexão removida para {platform}.',
            ['platform' => $platform]
        ));
        $this->redirectToRoute('social/index');
    }

    public function generateDraft(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_ai_draft_generator');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $input = [
            'theme' => (string) $this->request->post('theme', ''),
            'objective' => (string) $this->request->post('objective', ''),
            'pillar' => (string) $this->request->post('pillar', ''),
            'tone' => (string) $this->request->post('tone', ''),
            'audience' => (string) $this->request->post('audience', ''),
            'frequency' => (string) $this->request->post('frequency', 'semanal'),
            'cta' => (string) $this->request->post('cta', $this->t('social.default_cta', 'Comente sua opiniao')),
            'channels' => (array) $this->request->post('channels', []),
        ];

        $service = new ContentStrategistService();
        $draft = $service->buildPack($input);

        $this->loader->model('social')->saveDraft($userId, $draft);

        flash('success', $this->t('social.flash_draft_generated', 'Novo conteúdo estratégico gerado com sucesso.'));
        $this->redirectToRoute('social/index');
    }

    public function saveFormatPreset(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_format_presets');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $standards = new SocialFormatStandardsService();
        $platformSlug = strtolower(trim((string) $this->request->post('platform_slug', '')));
        $formatType = strtolower(trim((string) $this->request->post('format_type', 'post')));
        $presetName = trim((string) $this->request->post('preset_name', ''));
        $widthPx = (int) $this->request->post('width_px', 0);
        $heightPx = (int) $this->request->post('height_px', 0);
        $aspectRatio = trim((string) $this->request->post('aspect_ratio', ''));
        $safeAreaText = trim((string) $this->request->post('safe_area_text', ''));
        $colorHex = strtoupper(trim((string) $this->request->post('color_hex', '')));
        $notes = trim((string) $this->request->post('notes', ''));
        $sourceKeysCsv = trim((string) $this->request->post('source_keys', ''));

        $selectedPreset = $standards->presetFor($platformSlug, $formatType);
        if ($selectedPreset === null) {
            flash('error', $this->t('social.flash_preset_official_not_found', 'Preset oficial não encontrado para a plataforma/formato selecionados.'));
            $this->redirectToRoute('social/index');
        }

        if ($widthPx <= 0 || $heightPx <= 0) {
            flash('error', $this->t('social.flash_preset_invalid_dimensions', 'Informe largura e altura validas para salvar o preset.'));
            $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
        }

        if ($colorHex !== '' && preg_match('/^#[0-9A-F]{6}$/', $colorHex) !== 1) {
            flash('error', $this->t('social.flash_preset_invalid_color', 'Cor invalida. Use o formato hexadecimal #RRGGBB.'));
            $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
        }

        $sourceKeys = [];
        if ($sourceKeysCsv !== '') {
            $sourceKeys = array_values(array_filter(array_map('trim', explode(',', $sourceKeysCsv)), static fn ($key): bool => $key !== ''));
        }

        $sources = $standards->resolveSources($sourceKeys);
        $sourceLinks = [];
        foreach ($sources as $source) {
            $sourceLinks[] = [
                'label' => (string) ($source['label'] ?? ''),
                'url' => (string) ($source['url'] ?? ''),
                'checked_at' => (string) ($source['checked_at'] ?? ''),
            ];
        }

        $this->loader->model('social')->createFormatPreset($userId, [
            'platform_slug' => $platformSlug,
            'format_type' => $formatType,
            'preset_name' => $presetName,
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'aspect_ratio' => $aspectRatio,
            'safe_area_text' => $safeAreaText,
            'color_hex' => $colorHex,
            'notes' => $notes,
            'source_links' => $sourceLinks,
        ]);

        flash('success', $this->t('social.flash_preset_saved', 'Preset personalizado salvo com sucesso.'));
        $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
    }

    public function deleteFormatPreset(int $presetId): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        if ($presetId <= 0) {
            flash('error', $this->t('social.flash_preset_invalid_for_delete', 'Preset inválido para exclusão.'));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $this->loader->model('social')->deleteFormatPreset($userId, $presetId);

        flash('success', $this->t('social.flash_preset_deleted', 'Preset personalizado removido.'));
        $this->redirectToRoute('social/index');
    }

    public function queuePublication(): void
    {
        $this->boot('client.social', 'social.publish_hub');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_publish_hub');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $planItemId = (int) $this->request->post('plan_item_id', 0);
        $platforms = (array) $this->request->post('platforms', []);
        $messageText = trim((string) $this->request->post('message_text', ''));
        $mediaUrl = trim((string) $this->request->post('media_url', ''));
        $scheduledAt = trim((string) $this->request->post('scheduled_at', ''));

        $quotaPlatforms = [];
        foreach ($platforms as $platform) {
            $slug = strtolower(trim((string) $platform));
            if ($slug !== '') {
                $quotaPlatforms[$slug] = true;
            }
        }
        $quotaIncrement = count($quotaPlatforms) > 0 ? count($quotaPlatforms) : 1;
        $quota = $subscription->evaluateQuota($userId, 'max_social_publications_per_month', $quotaIncrement);
        if (empty($quota['allowed'])) {
            flash('error', (string) ($quota['message'] ?? 'Limite de publicacoes sociais atingido para o plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $service = new SocialPublishingService($this->registry);
        $service->ensureTables();

        $queued = 0;
        if ($planItemId > 0) {
            $queued = $service->queueFromPlanItem($userId, $planItemId, $platforms, [
                'message_text' => $messageText,
                'media_url' => $mediaUrl,
                'scheduled_at' => $scheduledAt,
            ]);
        } else {
            $normalized = [];
            foreach ($platforms as $platform) {
                $slug = strtolower(trim((string) $platform));
                if ($slug !== '') {
                    $normalized[$slug] = true;
                }
            }

            foreach (array_keys($normalized) as $platformSlug) {
                $insert = $service->queuePublication($userId, [
                    'platform_slug' => $platformSlug,
                    'title' => trim((string) $this->request->post('title', $this->t('social.default_single_publication_title', 'Publicação avulsa'))),
                    'message_text' => $messageText,
                    'media_url' => $mediaUrl,
                    'scheduled_at' => $scheduledAt,
                    'payload' => ['origin' => 'manual_queue'],
                ]);
                if ($insert > 0) {
                    $queued++;
                }
            }
        }

        $job = new JobMonitorService($this->registry);
        $job->checkin(
            'social.publisher_queue',
            $queued > 0 ? 'ok' : 'warning',
            null,
            ['queued' => $queued, 'user_id' => $userId],
            $queued > 0 ? null : $this->t('social.log_queue_empty', 'Nenhuma publicação entrou na fila')
        );

        $obs = new ObservabilityService($this->registry);
        $obs->log(
            $queued > 0 ? 'info' : 'warning',
            'social_publish_hub',
            $queued > 0
                ? $this->t('social.log_queue_success', 'Publicacoes enfileiradas no hub.')
                : $this->t('social.log_queue_failure', 'Falha ao enfileirar publicacoes.'),
            ['queued' => $queued, 'plan_item_id' => $planItemId],
            $userId,
            'client'
        );

        $automation = new AutomationService($this->registry);
        $automation->dispatch('social.publication_queued', [
            'user_id' => $userId,
            'plan_item_id' => $planItemId,
            'queued' => $queued,
        ], [
            'source' => 'client.social.queuePublication',
        ]);

        if ($queued <= 0) {
            flash('error', $this->t('social.flash_queue_empty', 'Nenhuma publicação foi adicionada à fila. Verifique item/plataforma e conexões.'));
            $this->redirectToRoute('social/index');
        }

        flash('success', $this->t(
            'social.flash_queue_success',
            'Publicacoes adicionadas ao hub. Total enfileirado: {count}.',
            ['count' => $queued]
        ));
        $this->redirectToRoute('social/index');
    }

    public function publishNow(int $publicationId): void
    {
        $this->boot('client.social', 'social.publish_hub');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $service = new SocialPublishingService($this->registry);
        $service->ensureTables();

        $started = microtime(true);
        $result = $service->publishNow($userId, $publicationId);
        $durationMs = max(0, (int) round((microtime(true) - $started) * 1000));

        $job = new JobMonitorService($this->registry);
        $job->checkin(
            'social.publisher_queue',
            !empty($result['ok']) ? 'ok' : 'error',
            $durationMs,
            [
                'publication_id' => $publicationId,
                'status' => (string) ($result['status'] ?? ''),
            ],
            !empty($result['ok']) ? null : (string) ($result['message'] ?? $this->t('social.flash_publish_failure_short', 'Falha ao publicar'))
        );

        $automation = new AutomationService($this->registry);
        $automation->dispatch(!empty($result['ok']) ? 'social.publication_published' : 'social.publication_failed', [
            'publication_id' => $publicationId,
            'status' => (string) ($result['status'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
        ], [
            'source' => 'client.social.publishNow',
            'user_id' => $userId,
        ]);

        if (!empty($result['ok'])) {
            flash('success', (string) ($result['message'] ?? $this->t('social.flash_publish_success', 'Publicação concluída.')));
            $this->redirectToRoute('social/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('social.flash_publish_failure', 'Falha ao publicar item no hub.')));
        $this->redirectToRoute('social/index');
    }

    public function processQueue(): void
    {
        $this->boot('client.social', 'social.publish_hub');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = new SubscriptionService($this->registry);
        $feature = $subscription->evaluateFeature($userId, 'allow_queue_processing');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $limit = max(1, min(50, (int) $this->request->post('limit', 10)));

        $service = new SocialPublishingService($this->registry);
        $service->ensureTables();

        $started = microtime(true);
        $summary = $service->processDueQueue($userId, $limit);
        $durationMs = max(0, (int) round((microtime(true) - $started) * 1000));

        $job = new JobMonitorService($this->registry);
        $job->checkin('social.publisher_queue', 'ok', $durationMs, $summary, null);

        $automation = new AutomationService($this->registry);
        $automation->dispatch('social.queue_processed', [
            'user_id' => $userId,
            'summary' => $summary,
        ], [
            'source' => 'client.social.processQueue',
        ]);

        flash('success', $this->t(
            'social.flash_process_queue_done',
            'Fila processada. Total: {total}, publicados: {published}.',
            [
                'total' => (int) ($summary['total'] ?? 0),
                'published' => (int) ($summary['published'] ?? 0),
            ]
        ));
        $this->redirectToRoute('social/index');
    }

    private function absoluteRoute(string $route): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = HostGuard::effectiveHost(
            $_SERVER,
            (array) $this->config->get('security.allowed_hosts', []),
            (string) $this->config->get('app.base_url', '')
        );

        return $scheme . '://' . $host . route_url($route);
    }
}
