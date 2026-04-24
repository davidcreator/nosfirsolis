<?php

namespace Client\Controller;

use System\Engine\Controller;
use System\Library\FeatureFlagService;

abstract class BaseController extends Controller
{
    protected function boot(string $permission = '', ?string $featureKey = null): void
    {
        $this->requireAuth($permission);
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

        parent::render($template, $data, $layout);
    }
}
