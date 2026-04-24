<?php

namespace Admin\Controller;

use System\Library\AutomationService;
use System\Library\FeatureFlagService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;

class OperationsController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.operations');

        $featureService = new FeatureFlagService($this->registry);
        $automation = new AutomationService($this->registry);
        $jobs = new JobMonitorService($this->registry);
        $observability = new ObservabilityService($this->registry);

        $featureService->ensureTables();
        $automation->ensureTables();
        $jobs->ensureTables();
        $observability->ensureTables();

        $newStaleAlerts = $jobs->evaluateStaleMonitors();
        if (!empty($newStaleAlerts)) {
            $automation->dispatch('jobs.alert.summary', [
                'new_stale_alerts' => count($newStaleAlerts),
                'alerts' => $newStaleAlerts,
            ], [
                'source' => 'admin.operations.index',
            ]);
        }

        $this->render('operations/index', [
            'title' => $this->t('operations.title_index', 'Operacoes e Integracoes'),
            'flags' => $featureService->all(),
            'webhooks' => $automation->listWebhooks(),
            'dispatch_logs' => $automation->recentDispatches(60),
            'monitors' => $jobs->listMonitors(),
            'checkins' => $jobs->recentCheckins(60),
            'job_alerts' => $jobs->activeAlerts(60),
            'observability_events' => $observability->recent(80),
            'ops_feature_map' => $featureService->resolvedMap($this->auth ? $this->auth->user() : null, 'admin'),
        ]);
    }

    public function saveFeatureFlag(): void
    {
        $this->boot('admin.operations');
        $this->requirePostAndCsrf();

        $service = new FeatureFlagService($this->registry);
        $id = $service->save([
            'flag_key' => (string) $this->request->post('flag_key', ''),
            'label' => (string) $this->request->post('label', ''),
            'description' => (string) $this->request->post('description', ''),
            'enabled' => $this->request->post('enabled', ''),
            'target_area' => (string) $this->request->post('target_area', 'all'),
            'rollout_strategy' => (string) $this->request->post('rollout_strategy', 'all'),
            'min_hierarchy_level' => (string) $this->request->post('min_hierarchy_level', ''),
            'required_permission' => (string) $this->request->post('required_permission', ''),
        ]);

        if ($id <= 0) {
            flash('error', $this->t('operations.flash_feature_save_error', 'Não foi possível salvar a feature flag.'));
            $this->redirectToRoute('operations/index');
        }

        flash('success', $this->t('operations.flash_feature_saved', 'Feature flag salva com sucesso.'));
        $this->redirectToRoute('operations/index');
    }

    public function deleteFeatureFlag(int $id): void
    {
        $this->boot('admin.operations');
        $this->requirePostAndCsrf();

        $service = new FeatureFlagService($this->registry);
        $service->delete($id);

        flash('success', $this->t('operations.flash_feature_deleted', 'Feature flag removida.'));
        $this->redirectToRoute('operations/index');
    }

    public function saveWebhook(): void
    {
        $this->boot('admin.operations', 'automation.webhooks');
        $this->requirePostAndCsrf();

        $service = new AutomationService($this->registry);
        $id = $service->saveWebhook([
            'id' => (int) $this->request->post('id', 0),
            'name' => (string) $this->request->post('name', ''),
            'event_key' => (string) $this->request->post('event_key', ''),
            'endpoint_url' => (string) $this->request->post('endpoint_url', ''),
            'http_method' => (string) $this->request->post('http_method', 'POST'),
            'auth_type' => (string) $this->request->post('auth_type', 'none'),
            'auth_username' => (string) $this->request->post('auth_username', ''),
            'auth_secret' => (string) $this->request->post('auth_secret', ''),
            'header_name' => (string) $this->request->post('header_name', ''),
            'header_value' => (string) $this->request->post('header_value', ''),
            'signing_secret' => (string) $this->request->post('signing_secret', ''),
            'timeout_seconds' => (int) $this->request->post('timeout_seconds', 8),
            'retries' => (int) $this->request->post('retries', 1),
            'enabled' => $this->request->post('enabled', ''),
        ]);

        if ($id <= 0) {
            flash('error', $this->t('operations.flash_webhook_save_error', 'Não foi possível salvar o webhook.'));
            $this->redirectToRoute('operations/index');
        }

        flash('success', $this->t('operations.flash_webhook_saved', 'Webhook salvo com sucesso.'));
        $this->redirectToRoute('operations/index');
    }

    public function deleteWebhook(int $id): void
    {
        $this->boot('admin.operations', 'automation.webhooks');
        $this->requirePostAndCsrf();

        $service = new AutomationService($this->registry);
        $service->deleteWebhook($id);

        flash('success', $this->t('operations.flash_webhook_deleted', 'Webhook removido.'));
        $this->redirectToRoute('operations/index');
    }

    public function testWebhook(int $id): void
    {
        $this->boot('admin.operations', 'automation.webhooks');
        $this->requirePostAndCsrf();

        $service = new AutomationService($this->registry);
        $result = $service->testWebhook($id);

        if ((int) ($result['success'] ?? 0) > 0 && (int) ($result['failed'] ?? 0) === 0) {
            flash('success', $this->t('operations.flash_webhook_test_success', 'Teste de webhook enviado com sucesso.'));
            $this->redirectToRoute('operations/index');
        }

        flash('error', $this->t('operations.flash_webhook_test_error', 'Teste de webhook finalizado com falhas. Consulte os logs de dispatch.'));
        $this->redirectToRoute('operations/index');
    }

    public function saveMonitor(): void
    {
        $this->boot('admin.operations', 'jobs.monitoring');
        $this->requirePostAndCsrf();

        $service = new JobMonitorService($this->registry);
        $id = $service->upsertMonitor([
            'job_key' => (string) $this->request->post('job_key', ''),
            'name' => (string) $this->request->post('name', ''),
            'description' => (string) $this->request->post('description', ''),
            'expected_interval_minutes' => (int) $this->request->post('expected_interval_minutes', 60),
            'max_runtime_seconds' => (int) $this->request->post('max_runtime_seconds', 300),
            'enabled' => $this->request->post('enabled', ''),
        ]);

        if ($id <= 0) {
            flash('error', $this->t('operations.flash_monitor_save_error', 'Não foi possível salvar o monitor.'));
            $this->redirectToRoute('operations/index');
        }

        flash('success', $this->t('operations.flash_monitor_saved', 'Monitor de job salvo.'));
        $this->redirectToRoute('operations/index');
    }

    public function deleteMonitor(int $id): void
    {
        $this->boot('admin.operations', 'jobs.monitoring');
        $this->requirePostAndCsrf();

        $service = new JobMonitorService($this->registry);
        $service->deleteMonitor($id);

        flash('success', $this->t('operations.flash_monitor_deleted', 'Monitor removido.'));
        $this->redirectToRoute('operations/index');
    }

    public function runMaintenance(): void
    {
        $this->boot('admin.operations');
        $this->requirePostAndCsrf();

        $jobs = new JobMonitorService($this->registry);
        $alerts = $jobs->evaluateStaleMonitors();

        $observability = new ObservabilityService($this->registry);
        $observability->log('info', 'operations', $this->t('operations.log_manual_maintenance', 'Manutencao manual executada no painel admin.'), [
            'new_stale_alerts' => count($alerts),
        ], (int) ($this->auth->user()['id'] ?? 0), 'admin');

        flash('success', $this->t(
            'operations.flash_maintenance_done',
            'Manutencao executada. Novos alertas de stale: {count}.',
            ['count' => count($alerts)]
        ));
        $this->redirectToRoute('operations/index');
    }
}
