<?php

namespace Admin\Controller;

use System\Engine\Controller;
use System\Library\AutomationService;
use System\Library\CacheMaintenanceService;
use System\Library\FeatureFlagService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;
use System\Library\PlanCampaignAiManagerService;
use System\Library\SubscriptionService;
use System\Library\UsersListFilterService;

abstract class BaseController extends Controller
{
    private ?FeatureFlagService $featureFlagService = null;
    private ?SubscriptionService $subscriptionService = null;
    private ?AutomationService $automationService = null;
    private ?JobMonitorService $jobMonitorService = null;
    private ?ObservabilityService $observabilityService = null;
    private ?CacheMaintenanceService $cacheMaintenanceService = null;
    private ?UsersListFilterService $usersListFilterService = null;
    private ?PlanCampaignAiManagerService $planCampaignAiManagerService = null;

    protected function boot(string $permission = '', ?string $featureKey = null): void
    {
        $this->requireAuth($permission);
        if ($featureKey !== null && trim($featureKey) !== '') {
            $this->requireFeature($featureKey);
        }
    }

    protected function requirePostAndCsrf(): void
    {
        if (!$this->request->isPost() || !verify_csrf($this->request->post('_token'))) {
            flash('error', $this->t('common.flash_invalid_request', 'Requisição inválida.'));
            $this->redirectToRoute('dashboard/index');
        }
    }

    protected function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?: '';
        $value = preg_replace('/\s+/', '-', $value) ?: '';
        $value = preg_replace('/-+/', '-', $value) ?: '';

        return trim($value, '-');
    }

    protected function requireFeature(string $featureKey): void
    {
        $service = $this->featureFlags();
        $enabled = $service->isEnabled($featureKey, $this->auth ? $this->auth->user() : null, 'admin');
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
        $service = $this->featureFlags();
        $data['feature_flags'] = $service->resolvedMap($this->auth ? $this->auth->user() : null, 'admin');

        parent::render($template, $data, $layout);
    }

    protected function featureFlags(): FeatureFlagService
    {
        if ($this->featureFlagService instanceof FeatureFlagService) {
            return $this->featureFlagService;
        }

        $this->featureFlagService = new FeatureFlagService($this->registry);
        return $this->featureFlagService;
    }

    protected function subscriptionService(): SubscriptionService
    {
        if ($this->subscriptionService instanceof SubscriptionService) {
            return $this->subscriptionService;
        }

        $this->subscriptionService = new SubscriptionService($this->registry);
        return $this->subscriptionService;
    }

    protected function automationService(): AutomationService
    {
        if ($this->automationService instanceof AutomationService) {
            return $this->automationService;
        }

        $this->automationService = new AutomationService($this->registry);
        return $this->automationService;
    }

    protected function jobMonitorService(): JobMonitorService
    {
        if ($this->jobMonitorService instanceof JobMonitorService) {
            return $this->jobMonitorService;
        }

        $this->jobMonitorService = new JobMonitorService($this->registry);
        return $this->jobMonitorService;
    }

    protected function observabilityService(): ObservabilityService
    {
        if ($this->observabilityService instanceof ObservabilityService) {
            return $this->observabilityService;
        }

        $this->observabilityService = new ObservabilityService($this->registry);
        return $this->observabilityService;
    }

    protected function cacheMaintenanceService(): CacheMaintenanceService
    {
        if ($this->cacheMaintenanceService instanceof CacheMaintenanceService) {
            return $this->cacheMaintenanceService;
        }

        $this->cacheMaintenanceService = new CacheMaintenanceService($this->registry);
        return $this->cacheMaintenanceService;
    }

    protected function usersListFilter(): UsersListFilterService
    {
        if ($this->usersListFilterService instanceof UsersListFilterService) {
            return $this->usersListFilterService;
        }

        $this->usersListFilterService = new UsersListFilterService();
        return $this->usersListFilterService;
    }

    protected function planCampaignAiManagerService(): PlanCampaignAiManagerService
    {
        if ($this->planCampaignAiManagerService instanceof PlanCampaignAiManagerService) {
            return $this->planCampaignAiManagerService;
        }

        $this->planCampaignAiManagerService = new PlanCampaignAiManagerService($this->registry);
        return $this->planCampaignAiManagerService;
    }
}
