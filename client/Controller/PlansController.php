<?php

namespace Client\Controller;

use System\Library\Database;

class PlansController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.plans');

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $templateService = $this->planTemplateService();
        $calendarFilterData = $this->loader->model('calendar')->filterData();
        $aiService = $this->planCampaignAiManagerService();
        $resolvedAi = $aiService->resolveManagerForUser($userId);

        $this->render('plans/index', [
            'title' => $this->t('plans.title_index', 'Planos Editoriais'),
            'plans' => $this->loader->model('planner')->plansByUser($userId),
            'campaigns' => (array) ($calendarFilterData['campaigns'] ?? []),
            'objectives' => (array) ($calendarFilterData['objectives'] ?? []),
            'channels' => (array) ($calendarFilterData['channels'] ?? []),
            'templates' => $templateService->templates(),
            'ai_managers' => $aiService->availableManagers(),
            'ai_resolved_manager' => (array) ($resolvedAi['manager'] ?? []),
            'ai_manager_source' => (string) ($resolvedAi['source'] ?? 'default'),
        ]);
    }

    public function store(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $quota = $subscription->evaluateQuota($userId, 'max_editorial_plans_per_month', 1);
        if (empty($quota['allowed'])) {
            flash('error', (string) ($quota['message'] ?? $this->t('plans.flash_limit_reached', 'Limite de plano atingido para este periodo.')));
            $this->redirectToRoute('billing/index');
        }

        $startDate = (string) $this->request->post('start_date');
        $endDate = (string) $this->request->post('end_date');

        if ($startDate === '' || $endDate === '') {
            flash('error', $this->t('plans.flash_period_required', 'Informe início e fim do período.'));
            $this->redirectToRoute('plans/index');
        }

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $filters = [
            'campaign_id' => $this->request->post('campaign_id', ''),
            'content_objective_id' => $this->request->post('content_objective_id', ''),
            'channel_id' => $this->request->post('channel_id', ''),
        ];

        $planner = $this->loader->model('planner');
        $planId = $planner->createPlan($userId, [
            'name' => trim((string) $this->request->post('name', $this->t('plans.default_plan_name', 'Plano {date}', ['date' => $this->formatDateTime('Y-m-d H:i')]))),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'campaign_id' => $this->request->post('campaign_id', ''),
            'filters' => $filters,
            'notes' => trim((string) $this->request->post('notes')),
        ]);

        $items = $planner->generateItems($planId, $startDate, $endDate, $filters);

        flash('success', $this->t(
            'plans.flash_created',
            'Plano gerado com sucesso. Itens criados: {count}',
            ['count' => $items]
        ));
        $this->redirectToRoute('plans/show/' . $planId);
    }

    public function storeAi(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();

        $feature = $subscription->evaluateFeature($userId, 'allow_ai_draft_generator');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? $this->t('plans.flash_ai_feature_unavailable', 'Recurso de IA indisponivel no plano atual.')));
            $this->redirectToRoute('billing/index');
        }

        $quota = $subscription->evaluateQuota($userId, 'max_editorial_plans_per_month', 1);
        if (empty($quota['allowed'])) {
            flash('error', (string) ($quota['message'] ?? $this->t('plans.flash_limit_reached', 'Limite de plano atingido para este periodo.')));
            $this->redirectToRoute('billing/index');
        }

        $calendarFilterData = $this->loader->model('calendar')->filterData();
        $objectiveMap = [];
        foreach ((array) ($calendarFilterData['objectives'] ?? []) as $objectiveRow) {
            $objectiveId = (int) ($objectiveRow['id'] ?? 0);
            $objectiveName = trim((string) ($objectiveRow['name'] ?? ''));
            if ($objectiveId > 0 && $objectiveName !== '') {
                $objectiveMap[$objectiveId] = $objectiveName;
            }
        }

        $contentObjectiveId = (int) $this->request->post('ai_objective_id', 0);
        if (!array_key_exists($contentObjectiveId, $objectiveMap)) {
            $contentObjectiveId = 0;
        }

        $objectiveText = trim((string) $this->request->post('ai_objective', ''));
        if ($objectiveText === '' && $contentObjectiveId > 0) {
            $objectiveText = (string) ($objectiveMap[$contentObjectiveId] ?? '');
        }

        $aiService = $this->planCampaignAiManagerService();
        $blueprint = $aiService->buildPlanCampaignBlueprint([
            'user_id' => $userId,
            'manager_id' => (string) $this->request->post('ai_manager_id', ''),
            'theme' => (string) $this->request->post('ai_theme', ''),
            'objective' => $objectiveText,
            'audience' => (string) $this->request->post('ai_audience', ''),
            'tone' => (string) $this->request->post('ai_tone', ''),
            'frequency' => (string) $this->request->post('ai_frequency', 'semanal'),
            'campaign_focus' => (string) $this->request->post('ai_campaign_focus', ''),
            'start_date' => (string) $this->request->post('ai_start_date', ''),
            'end_date' => (string) $this->request->post('ai_end_date', ''),
            'channels' => (array) $this->request->post('channels', []),
        ]);

        $campaignMode = $this->normalizeAiCampaignMode((string) $this->request->post('ai_campaign_mode', 'new'));
        $selectedCampaignId = (int) $this->request->post('ai_campaign_id', 0);

        if ($campaignMode === 'existing' && $selectedCampaignId <= 0) {
            flash('error', $this->t('plans.flash_ai_campaign_existing_required', 'Selecione uma campanha existente para vincular o plano.'));
            $this->redirectToRoute('plans/index');
        }

        $itemsBlueprint = (array) ($blueprint['items'] ?? []);
        if ($itemsBlueprint === []) {
            flash('error', $this->t('plans.flash_ai_no_items', 'A IA nao conseguiu montar itens para o periodo informado. Revise os dados e tente novamente.'));
            $this->redirectToRoute('plans/index');
        }

        $db = $this->registry->get('db');
        if (!$db instanceof Database || !$db->connected()) {
            flash('error', $this->t('plans.flash_ai_db_unavailable', 'Banco indisponivel para salvar o plano com IA.'));
            $this->redirectToRoute('plans/index');
        }

        $planner = $this->loader->model('planner');
        $campaignId = null;

        $campaignData = (array) ($blueprint['campaign'] ?? []);
        $planData = (array) ($blueprint['plan'] ?? []);

        try {
            $db->beginTransaction();

            if ($campaignMode === 'existing') {
                $campaignId = $selectedCampaignId;
            } elseif ($campaignMode === 'new') {
                $createdCampaignId = $planner->createCampaign([
                    'name' => (string) ($campaignData['name'] ?? ''),
                    'description' => (string) ($campaignData['description'] ?? ''),
                    'objective' => (string) ($campaignData['objective'] ?? ''),
                    'status' => (string) ($campaignData['status'] ?? 'planned'),
                    'start_date' => (string) ($campaignData['start_date'] ?? ''),
                    'end_date' => (string) ($campaignData['end_date'] ?? ''),
                ]);

                if ($createdCampaignId <= 0) {
                    throw new \RuntimeException('campaign_create_failed');
                }

                $campaignId = $createdCampaignId;
            }

            $managerId = (string) (($blueprint['manager']['id'] ?? ''));
            $managerSource = (string) ($blueprint['manager_source'] ?? 'default');

            $planId = $planner->createPlan($userId, [
                'name' => (string) ($planData['name'] ?? $this->t('plans.default_plan_name', 'Plano {date}', ['date' => $this->formatDateTime('Y-m-d H:i')])),
                'start_date' => (string) ($planData['start_date'] ?? ''),
                'end_date' => (string) ($planData['end_date'] ?? ''),
                'campaign_id' => $campaignId,
                'filters' => [
                    'source' => 'ai_manager',
                    'ai_manager_id' => $managerId,
                    'ai_manager_source' => $managerSource,
                    'ai_campaign_mode' => $campaignMode,
                    'content_objective_id' => $contentObjectiveId > 0 ? $contentObjectiveId : null,
                ],
                'notes' => (string) ($planData['notes'] ?? ''),
            ]);

            if ($planId <= 0) {
                throw new \RuntimeException('plan_create_failed');
            }

            $itemsToPersist = [];
            foreach ($itemsBlueprint as $itemBlueprint) {
                $channels = array_values(array_filter(array_map(
                    static fn ($channel): string => trim((string) $channel),
                    (array) ($itemBlueprint['channels'] ?? [])
                ), static fn (string $channel): bool => $channel !== ''));

                $encodedChannels = json_encode($channels);
                if (!is_string($encodedChannels)) {
                    $encodedChannels = '[]';
                }

                $itemsToPersist[] = [
                    'planned_date' => (string) ($itemBlueprint['planned_date'] ?? ''),
                    'title' => (string) ($itemBlueprint['title'] ?? ''),
                    'description' => (string) ($itemBlueprint['description'] ?? ''),
                    'campaign_id' => $campaignId,
                    'content_objective_id' => $contentObjectiveId > 0 ? $contentObjectiveId : null,
                    'format_type' => (string) ($itemBlueprint['format_type'] ?? 'post'),
                    'channels_json' => $encodedChannels,
                    'status' => (string) ($itemBlueprint['status'] ?? 'planned'),
                ];
            }

            $inserted = $planner->addPlanItems($planId, $itemsToPersist);
            if ($inserted <= 0) {
                throw new \RuntimeException('plan_items_create_failed');
            }

            $db->commit();

            flash('success', $this->t(
                'plans.flash_ai_created',
                'Plano e campanha gerados por IA com sucesso. Itens criados: {count}.',
                ['count' => $inserted]
            ));
            $this->redirectToRoute('plans/show/' . $planId);
        } catch (\Throwable) {
            $db->rollBack();
            flash('error', $this->t(
                'plans.flash_ai_create_failed',
                'Nao foi possivel gerar o plano por IA neste momento. Tente novamente.'
            ));
            $this->redirectToRoute('plans/index');
        }
    }

    public function storeTemplate(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_template_plans');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? $this->t('plans.flash_template_feature_unavailable', 'Seu plano atual nao permite templates anuais.')));
            $this->redirectToRoute('billing/index');
        }

        $quota = $subscription->evaluateQuota($userId, 'max_editorial_plans_per_month', 1);
        if (empty($quota['allowed'])) {
            flash('error', (string) ($quota['message'] ?? $this->t('plans.flash_limit_reached', 'Limite de plano atingido para este periodo.')));
            $this->redirectToRoute('billing/index');
        }

        $templateSlug = trim((string) $this->request->post('template_slug'));
        $defaultYear = (int) $this->formatDateTime('Y');
        $year = (int) $this->request->post('template_year', (string) $defaultYear);
        $frequency = trim((string) $this->request->post('template_frequency', 'semanal'));

        if ($year < 1970 || $year > 2100) {
            $year = $defaultYear;
        }

        $service = $this->planTemplateService();
        $template = $service->findTemplate($templateSlug);

        if (!$template) {
            flash('error', $this->t('plans.flash_invalid_template', 'Template inválido.'));
            $this->redirectToRoute('plans/index');
        }

        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $planner = $this->loader->model('planner');
        $planId = $planner->createPlan($userId, [
            'name' => $template['name'] . ' ' . $year . ' (' . strtoupper($frequency) . ')',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'campaign_id' => null,
            'filters' => [
                'template_slug' => $template['slug'],
                'template_segment' => $template['segment'],
                'template_frequency' => $frequency,
            ],
            'notes' => $this->t(
                'plans.template_generated_note',
                'Plano gerado automaticamente a partir do template "{template}".',
                ['template' => $template['name']]
            ),
        ]);

        $items = $service->generateItems($template, $year, $frequency);
        $inserted = $planner->addPlanItems($planId, $items);

        flash('success', $this->t(
            'plans.flash_template_applied',
            'Template aplicado com sucesso. Itens criados: {count}',
            ['count' => $inserted]
        ));
        $this->redirectToRoute('plans/show/' . $planId);
    }

    public function show(int $id): void
    {
        $this->boot('client.plans');

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $filters = $this->showFiltersFromRequest();
        $planner = $this->loader->model('planner');

        $plan = $planner->planByIdForUser($id, $userId);
        if (!$plan) {
            flash('error', $this->t('plans.flash_not_found_or_forbidden', 'Plano não encontrado ou sem permissão de acesso.'));
            $this->redirectToRoute('plans/index');
        }

        $items = $planner->planItemsForUser($id, $userId, $filters);
        $statusBreakdown = $planner->statusBreakdownForPlan($id, $userId);
        $insights = $planner->planInsightsForUser($id, $userId);

        $this->render('plans/show', [
            'title' => $this->t('plans.title_show', 'Plano Editorial #{id}', ['id' => $id]),
            'plan' => $plan,
            'plan_id' => $id,
            'items' => $items,
            'status_filter' => $filters['status'],
            'search_query' => $filters['q'],
            'status_breakdown' => $statusBreakdown,
            'insights' => $insights,
            'show_query' => $this->showQuery($filters),
        ]);
    }

    public function updateItem(int $itemId): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $planId = (int) $this->request->post('plan_id', 0);

        if ($itemId <= 0 || $planId <= 0) {
            flash('error', $this->t('plans.flash_invalid_item_for_update', 'Item de plano inválido para atualização.'));
            $this->redirectToRoute('plans/index');
        }

        $updated = $this->loader->model('planner')->updatePlanItemForUser($itemId, $userId, [
            'status' => (string) $this->request->post('status', 'planned'),
            'manual_note' => (string) $this->request->post('manual_note', ''),
        ]);

        if ($updated) {
            flash('success', $this->t('plans.flash_item_updated', 'Item atualizado com sucesso.'));
        } else {
            flash('error', $this->t('plans.flash_item_update_error', 'Não foi possível atualizar o item selecionado.'));
        }

        $query = $this->showQueryFromPost();
        $this->redirectToRoute('plans/show/' . $planId . ($query !== '' ? '?' . $query : ''));
    }

    public function bulkUpdateStatus(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $planId = (int) $this->request->post('plan_id', 0);

        if ($planId <= 0) {
            flash('error', $this->t('plans.flash_invalid_plan_for_bulk_update', 'Plano inválido para atualização em lote.'));
            $this->redirectToRoute('plans/index');
        }

        $status = $this->normalizeItemStatus((string) $this->request->post('bulk_status', ''));
        if ($status === null) {
            flash('error', $this->t('plans.flash_invalid_bulk_status', 'Status de lote inválido.'));
            $query = $this->showQueryFromPost();
            $this->redirectToRoute('plans/show/' . $planId . ($query !== '' ? '?' . $query : ''));
        }

        $itemIds = $this->parseSelectedItemIds((string) $this->request->post('selected_item_ids', ''));
        if (empty($itemIds)) {
            flash('error', $this->t('plans.flash_bulk_requires_selection', 'Selecione ao menos um item para atualizar em lote.'));
            $query = $this->showQueryFromPost();
            $this->redirectToRoute('plans/show/' . $planId . ($query !== '' ? '?' . $query : ''));
        }

        $updated = $this->loader->model('planner')->updatePlanItemsStatusForUser($planId, $userId, $itemIds, $status);
        if ($updated > 0) {
            flash('success', $this->t(
                'plans.flash_bulk_update_success',
                'Atualizacao em lote concluida. Itens atualizados: {count}.',
                ['count' => $updated]
            ));
        } else {
            flash('error', $this->t('plans.flash_bulk_update_no_changes', 'Nenhum item foi atualizado. Verifique a seleção e as permissões do plano.'));
        }

        $query = $this->showQueryFromPost();
        $this->redirectToRoute('plans/show/' . $planId . ($query !== '' ? '?' . $query : ''));
    }

    public function exportCsv(int $id): void
    {
        $this->boot('client.plans');

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $filters = $this->showFiltersFromRequest();
        $planner = $this->loader->model('planner');
        $plan = $planner->planByIdForUser($id, $userId);

        if (!$plan) {
            flash('error', $this->t('plans.flash_not_found_for_export', 'Plano não encontrado para exportação.'));
            $this->redirectToRoute('plans/index');
        }

        $items = $planner->planItemsForUser($id, $userId, $filters);
        $rows = [];

        foreach ($items as $item) {
            $channels = json_decode((string) ($item['channels_json'] ?? '[]'), true);
            if (!is_array($channels) || empty($channels)) {
                $channels = [];
            }

            $rows[] = [
                (string) ($item['planned_date'] ?? ''),
                (string) ($item['title'] ?? ''),
                (string) ($item['format_type'] ?? ''),
                (string) ($item['status'] ?? ''),
                implode(', ', array_map(static fn ($channel): string => (string) $channel, $channels)),
                (string) ($item['description'] ?? ''),
                (string) ($item['manual_note'] ?? ''),
            ];
        }

        $exporter = $this->exportService();
        $csv = $exporter->exportCsv($rows, [
            $this->t('plans.csv_header_planned_date', 'Data planejada'),
            $this->t('plans.csv_header_title', 'Título'),
            $this->t('plans.csv_header_format', 'Formato'),
            $this->t('plans.csv_header_status', 'Status'),
            $this->t('plans.csv_header_channels', 'Canais'),
            $this->t('plans.csv_header_description', 'Descrição'),
            $this->t('plans.csv_header_note', 'Observação'),
        ]);

        $rawName = strtolower((string) ($plan['name'] ?? ('plano-' . $id)));
        $safeName = preg_replace('/[^a-z0-9_-]/', '-', $rawName);
        $safeName = trim((string) $safeName, '-');
        if ($safeName === '') {
            $safeName = 'plano-' . $id;
        }

        $filename = 'solis-' . $safeName . '-' . $this->formatDateTime('Ymd-His') . '.csv';

        $this->response->addHeader('Content-Type: text/csv; charset=UTF-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        $this->response->setOutput("\xEF\xBB\xBF" . $csv);
    }

    public function saveNote(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $noteDate = (string) $this->request->post('note_date');
        $contextType = (string) $this->request->post('context_type', 'editorial');
        $noteText = trim((string) $this->request->post('note_text'));

        if ($noteDate === '' || $noteText === '') {
            flash('error', $this->t('plans.flash_note_required', 'Data e observação são obrigatórias.'));
            $this->redirectToRoute('calendar/index?mode=monthly');
        }

        $this->loader->model('planner')->upsertDayNote(
            (int) ($this->auth->user()['id'] ?? 0),
            $noteDate,
            $contextType,
            $noteText
        );

        flash('success', $this->t(
            'plans.flash_note_saved',
            'Observação salva para {date}.',
            ['date' => $noteDate]
        ));
        $noteTs = $this->parseDateToTimestamp($noteDate);
        if ($noteTs === null) {
            $this->redirectToRoute('calendar/index?mode=monthly');
        }

        $this->redirectToRoute(
            'calendar/index?mode=monthly&year='
            . $this->formatDateTime('Y', $noteTs)
            . '&month='
            . $this->formatDateTime('n', $noteTs)
        );
    }

    private function showFiltersFromRequest(): array
    {
        return [
            'status' => $this->normalizeShowStatus((string) $this->request->get('status', 'all')),
            'q' => substr(trim((string) $this->request->get('q', '')), 0, 120),
        ];
    }

    private function showQueryFromPost(): string
    {
        return $this->showQuery([
            'status' => $this->normalizeShowStatus((string) $this->request->post('return_status', 'all')),
            'q' => substr(trim((string) $this->request->post('return_q', '')), 0, 120),
        ]);
    }

    private function showQuery(array $filters): string
    {
        $status = $this->normalizeShowStatus((string) ($filters['status'] ?? 'all'));
        $search = substr(trim((string) ($filters['q'] ?? '')), 0, 120);

        $query = [];
        if ($status !== 'all') {
            $query['status'] = $status;
        }
        if ($search !== '') {
            $query['q'] = $search;
        }

        return http_build_query($query);
    }

    private function normalizeShowStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['all', 'planned', 'scheduled', 'published', 'skipped'];

        return in_array($status, $allowed, true) ? $status : 'all';
    }

    private function normalizeItemStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        $allowed = ['planned', 'scheduled', 'published', 'skipped'];

        return in_array($status, $allowed, true) ? $status : null;
    }

    private function normalizeAiCampaignMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['new', 'existing', 'none'], true) ? $mode : 'new';
    }

    private function parseSelectedItemIds(string $csv): array
    {
        $rawParts = explode(',', $csv);
        $ids = [];

        foreach ($rawParts as $rawPart) {
            $part = trim($rawPart);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }

            $id = (int) $part;
            if ($id <= 0) {
                continue;
            }

            $ids[$id] = true;
            if (count($ids) >= 500) {
                break;
            }
        }

        return array_keys($ids);
    }
}
