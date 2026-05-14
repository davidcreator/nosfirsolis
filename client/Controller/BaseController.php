<?php

namespace Client\Controller;

use System\Engine\Controller;
use System\Library\AutomationService;
use System\Library\CalendarService;
use System\Library\CampaignTrackingService;
use System\Library\ContentStrategistService;
use System\Library\ExportService;
use System\Library\FeatureFlagService;
use System\Library\JobMonitorService;
use System\Library\MailService;
use System\Library\ObservabilityService;
use System\Library\PlanCampaignAiManagerService;
use System\Library\PlanTemplateService;
use System\Library\SocialAuthService;
use System\Library\SocialFormatStandardsService;
use System\Library\SocialPlatformRegistry;
use System\Library\SocialPublishingService;
use System\Library\SubscriptionService;

abstract class BaseController extends Controller
{
    private ?array $subscriptionContextCache = null;
    private ?FeatureFlagService $featureFlagService = null;
    private ?SubscriptionService $subscriptionServiceInstance = null;
    private ?CampaignTrackingService $campaignTrackingService = null;
    private ?AutomationService $automationService = null;
    private ?JobMonitorService $jobMonitorService = null;
    private ?MailService $mailService = null;
    private ?ObservabilityService $observabilityService = null;
    private ?SocialPublishingService $socialPublishingService = null;
    private ?CalendarService $calendarService = null;
    private ?PlanTemplateService $planTemplateService = null;
    private ?ExportService $exportService = null;
    private ?SocialAuthService $socialAuthService = null;
    private ?SocialFormatStandardsService $socialFormatStandardsService = null;
    private ?SocialPlatformRegistry $socialPlatformRegistry = null;
    private ?ContentStrategistService $contentStrategistService = null;
    private ?PlanCampaignAiManagerService $planCampaignAiManagerService = null;

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
        $service = $this->featureFlags();
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
        $service = $this->featureFlags();
        $data['feature_flags'] = $service->resolvedMap($this->auth ? $this->auth->user() : null, 'client');
        if (!empty($data['current_user']) || $this->auth?->check()) {
            $data['subscription_context'] = $this->subscriptionContext();
        }

        parent::render($template, $data, $layout);
    }

    protected function subscriptionService(): SubscriptionService
    {
        if ($this->subscriptionServiceInstance instanceof SubscriptionService) {
            return $this->subscriptionServiceInstance;
        }

        $this->subscriptionServiceInstance = new SubscriptionService($this->registry);
        return $this->subscriptionServiceInstance;
    }

    protected function featureFlags(): FeatureFlagService
    {
        if ($this->featureFlagService instanceof FeatureFlagService) {
            return $this->featureFlagService;
        }

        $this->featureFlagService = new FeatureFlagService($this->registry);
        return $this->featureFlagService;
    }

    protected function campaignTrackingService(): CampaignTrackingService
    {
        if ($this->campaignTrackingService instanceof CampaignTrackingService) {
            return $this->campaignTrackingService;
        }

        $this->campaignTrackingService = new CampaignTrackingService($this->registry);
        return $this->campaignTrackingService;
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

    protected function mailService(): MailService
    {
        if ($this->mailService instanceof MailService) {
            return $this->mailService;
        }

        $this->mailService = new MailService($this->registry);
        return $this->mailService;
    }

    protected function observabilityService(): ObservabilityService
    {
        if ($this->observabilityService instanceof ObservabilityService) {
            return $this->observabilityService;
        }

        $this->observabilityService = new ObservabilityService($this->registry);
        return $this->observabilityService;
    }

    protected function socialPublishingService(): SocialPublishingService
    {
        if ($this->socialPublishingService instanceof SocialPublishingService) {
            return $this->socialPublishingService;
        }

        $this->socialPublishingService = new SocialPublishingService($this->registry);
        return $this->socialPublishingService;
    }

    protected function calendarService(): CalendarService
    {
        if ($this->calendarService instanceof CalendarService) {
            return $this->calendarService;
        }

        $this->calendarService = new CalendarService();
        return $this->calendarService;
    }

    protected function planTemplateService(): PlanTemplateService
    {
        if ($this->planTemplateService instanceof PlanTemplateService) {
            return $this->planTemplateService;
        }

        $this->planTemplateService = new PlanTemplateService();
        return $this->planTemplateService;
    }

    protected function exportService(): ExportService
    {
        if ($this->exportService instanceof ExportService) {
            return $this->exportService;
        }

        $this->exportService = new ExportService();
        return $this->exportService;
    }

    protected function socialAuthService(): SocialAuthService
    {
        if ($this->socialAuthService instanceof SocialAuthService) {
            return $this->socialAuthService;
        }

        $this->socialAuthService = new SocialAuthService();
        return $this->socialAuthService;
    }

    protected function socialFormatStandardsService(): SocialFormatStandardsService
    {
        if ($this->socialFormatStandardsService instanceof SocialFormatStandardsService) {
            return $this->socialFormatStandardsService;
        }

        $this->socialFormatStandardsService = new SocialFormatStandardsService();
        return $this->socialFormatStandardsService;
    }

    protected function socialPlatformRegistry(): SocialPlatformRegistry
    {
        if ($this->socialPlatformRegistry instanceof SocialPlatformRegistry) {
            return $this->socialPlatformRegistry;
        }

        $this->socialPlatformRegistry = new SocialPlatformRegistry($this->config);
        return $this->socialPlatformRegistry;
    }

    protected function contentStrategistService(): ContentStrategistService
    {
        if ($this->contentStrategistService instanceof ContentStrategistService) {
            return $this->contentStrategistService;
        }

        $this->contentStrategistService = new ContentStrategistService();
        return $this->contentStrategistService;
    }

    protected function planCampaignAiManagerService(): PlanCampaignAiManagerService
    {
        if ($this->planCampaignAiManagerService instanceof PlanCampaignAiManagerService) {
            return $this->planCampaignAiManagerService;
        }

        $this->planCampaignAiManagerService = new PlanCampaignAiManagerService($this->registry);
        return $this->planCampaignAiManagerService;
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
