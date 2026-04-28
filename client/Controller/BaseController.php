<?php

namespace Client\Controller;

use System\Engine\Controller;
use System\Library\FeatureFlagService;
use System\Library\SubscriptionService;

abstract class BaseController extends Controller
{
    private ?array $subscriptionContextCache = null;

    protected function boot(string $permission = '', ?string $featureKey = null): void
    {
        $this->requireAuth($permission);
        $this->bootstrapSubscription();
        if ($featureKey !== null && trim($featureKey) !== '') {
            $this->requireFeature($featureKey);
        }
    }

    protected function ensurePostWithCsrf(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('common.flash_invalid_request', 'Requisição inválida.'));
            $this->redirectToRoute('dashboard/index');
        }
    }

    protected function requireFeature(string $featureKey): void
    {
        $service = new FeatureFlagService($this->registry);
        $enabled = $service->isEnabled($featureKey, $this->auth ? $this->auth->user() : null, 'client');
        if ($enabled) {
            return;
        }

        $this->response->setStatusCode(403);
        $this->render('partials/forbidden', [
            'title' => $this->t('common.feature_disabled_title', 'Recurso indisponivel'),
            'message' => $this->t('common.feature_disabled_message', 'Este recurso esta desabilitado por feature flag.'),
        ]);
        $this->response->send();
        exit;
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layout/main'): void
    {
        $service = new FeatureFlagService($this->registry);
        $data['feature_flags'] = $service->resolvedMap($this->auth ? $this->auth->user() : null, 'client');
        if (!empty($data['current_user']) || $this->auth?->check()) {
            $data['subscription_context'] = $this->subscriptionContext();
        }

        parent::render($template, $data, $layout);
    }

    protected function subscriptionService(): SubscriptionService
    {
        return new SubscriptionService($this->registry);
    }

    protected function subscriptionContext(): array
    {
        if ($this->subscriptionContextCache !== null) {
            return $this->subscriptionContextCache;
        }

        $userId = (int) ($this->auth?->user()['id'] ?? 0);
        if ($userId <= 0) {
            $this->subscriptionContextCache = [];
            return $this->subscriptionContextCache;
        }

        $service = $this->subscriptionService();
        $service->ensureUserSubscription($userId);
        $this->subscriptionContextCache = $service->contextForUser($userId);

        return $this->subscriptionContextCache;
    }

    private function bootstrapSubscription(): void
    {
        $userId = (int) ($this->auth?->user()['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $service = $this->subscriptionService();
        $service->ensureUserSubscription($userId);
    }
}
