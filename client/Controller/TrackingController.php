<?php

namespace Client\Controller;

use System\Library\AutomationService;
use System\Library\CampaignTrackingService;
use System\Library\JobMonitorService;
use System\Library\ObservabilityService;

class TrackingController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.tracking', 'tracking.campaign_links');

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $tracking = new CampaignTrackingService($this->registry);
        $tracking->ensureTables();

        $this->render('tracking/index', [
            'title' => $this->t('tracking.title_index', 'Rastreamento de Campanhas'),
            'links' => $tracking->listByUser($userId, 120),
            'summary' => $tracking->summaryByUser($userId),
            'campaigns' => $tracking->availableCampaigns(),
            'plan_items' => $tracking->availablePlanItems($userId, 120),
        ]);
    }

    public function store(): void
    {
        $this->boot('client.tracking', 'tracking.campaign_links');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $tracking = new CampaignTrackingService($this->registry);
        $tracking->ensureTables();

        $result = $tracking->createTrackedLink($userId, [
            'destination_url' => (string) $this->request->post('destination_url', ''),
            'campaign_id' => $this->request->post('campaign_id', ''),
            'plan_item_id' => $this->request->post('plan_item_id', ''),
            'channel_slug' => (string) $this->request->post('channel_slug', ''),
            'utm_source' => (string) $this->request->post('utm_source', ''),
            'utm_medium' => (string) $this->request->post('utm_medium', ''),
            'utm_campaign' => (string) $this->request->post('utm_campaign', ''),
            'utm_content' => (string) $this->request->post('utm_content', ''),
            'utm_term' => (string) $this->request->post('utm_term', ''),
            'mtm_campaign' => (string) $this->request->post('mtm_campaign', ''),
            'mtm_keyword' => (string) $this->request->post('mtm_keyword', ''),
            'notes' => (string) $this->request->post('notes', ''),
        ]);

        $job = new JobMonitorService($this->registry);
        $job->checkin('tracking.create_link', $result ? 'ok' : 'warning', null, [
            'user_id' => $userId,
        ], $result ? null : $this->t('tracking.log_create_error', 'Falha ao criar link rastreável'));

        $obs = new ObservabilityService($this->registry);
        $obs->log(
            $result ? 'info' : 'warning',
            'tracking',
            $result
                ? $this->t('tracking.log_created', 'Novo link rastreável criado.')
                : $this->t('tracking.log_create_error', 'Falha ao criar link rastreável.'),
            [
                'user_id' => $userId,
                'channel_slug' => (string) $this->request->post('channel_slug', ''),
            ],
            $userId,
            'client'
        );

        if (!$result) {
            flash('error', $this->t('tracking.flash_create_error', 'Não foi possível criar o link rastreável. Verifique a URL e os parâmetros.'));
            $this->redirectToRoute('tracking/index');
        }

        $automation = new AutomationService($this->registry);
        $automation->dispatch('tracking.link_created', [
            'tracking_id' => (int) ($result['id'] ?? 0),
            'short_code' => (string) ($result['short_code'] ?? ''),
            'short_url' => (string) ($result['short_url'] ?? ''),
            'external_short_url' => (string) ($result['external_short_url'] ?? ''),
            'tracking_url' => (string) ($result['tracking_url'] ?? ''),
        ], [
            'source' => 'client.tracking.store',
            'user_id' => $userId,
        ]);

        flash('success', $this->t('tracking.flash_created', 'Link rastreável criado com sucesso.'));
        $this->redirectToRoute('tracking/index');
    }

    public function archive(int $id): void
    {
        $this->boot('client.tracking', 'tracking.campaign_links');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $tracking = new CampaignTrackingService($this->registry);
        $tracking->archiveById($id, $userId);

        flash('success', $this->t('tracking.flash_archived', 'Link rastreável arquivado.'));
        $this->redirectToRoute('tracking/index');
    }

    public function redirect(string $shortCode): void
    {
        $tracking = new CampaignTrackingService($this->registry);
        $url = $tracking->resolveRedirect($shortCode);

        if ($url === null || $url === '') {
            $this->response->setStatusCode(404);
            $this->response->setOutput($this->t('tracking.output_not_found', 'Link não encontrado ou inativo.'));
            $this->response->send();
            exit;
        }

        $automation = new AutomationService($this->registry);
        $automation->dispatch('tracking.link_clicked', [
            'short_code' => $shortCode,
            'target_url' => $url,
        ], [
            'source' => 'client.tracking.redirect',
        ]);

        $this->response->redirect($url);
    }
}
