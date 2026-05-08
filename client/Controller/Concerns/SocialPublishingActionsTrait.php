<?php

namespace Client\Controller\Concerns;

trait SocialPublishingActionsTrait
{
    public function queuePublication(): void
    {
        $this->boot('client.social', 'social.publish_hub');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();
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

        $service = $this->socialPublishingService();
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
                    'title' => trim((string) $this->request->post('title', $this->t('social.default_single_publication_title', 'Publicacao avulsa'))),
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

        $job = $this->jobMonitorService();
        $job->checkin(
            'social.publisher_queue',
            $queued > 0 ? 'ok' : 'warning',
            null,
            ['queued' => $queued, 'user_id' => $userId],
            $queued > 0 ? null : $this->t('social.log_queue_empty', 'Nenhuma publicacao entrou na fila')
        );

        $obs = $this->observabilityService();
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

        $automation = $this->automationService();
        $automation->dispatch('social.publication_queued', [
            'user_id' => $userId,
            'plan_item_id' => $planItemId,
            'queued' => $queued,
        ], [
            'source' => 'client.social.queuePublication',
        ]);

        if ($queued <= 0) {
            flash('error', $this->t('social.flash_queue_empty', 'Nenhuma publicacao foi adicionada a fila. Verifique item/plataforma e conexoes.'));
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
        $service = $this->socialPublishingService();
        $service->ensureTables();

        $started = $this->nowMicrotime();
        $result = $service->publishNow($userId, $publicationId);
        $durationMs = $this->elapsedMilliseconds($started);

        $job = $this->jobMonitorService();
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

        $automation = $this->automationService();
        $automation->dispatch(!empty($result['ok']) ? 'social.publication_published' : 'social.publication_failed', [
            'publication_id' => $publicationId,
            'status' => (string) ($result['status'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
        ], [
            'source' => 'client.social.publishNow',
            'user_id' => $userId,
        ]);

        if (!empty($result['ok'])) {
            flash('success', (string) ($result['message'] ?? $this->t('social.flash_publish_success', 'Publicacao concluida.')));
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
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_queue_processing');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $limit = max(1, min(50, (int) $this->request->post('limit', 10)));

        $service = $this->socialPublishingService();
        $service->ensureTables();

        $started = $this->nowMicrotime();
        $summary = $service->processDueQueue($userId, $limit);
        $durationMs = $this->elapsedMilliseconds($started);

        $job = $this->jobMonitorService();
        $job->checkin('social.publisher_queue', 'ok', $durationMs, $summary, null);

        $automation = $this->automationService();
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
}
