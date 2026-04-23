<?php

namespace Client\Controller;

use System\Library\ExportService;
use System\Library\PlanTemplateService;

class PlansController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.plans');

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $templateService = new PlanTemplateService();

        $this->render('plans/index', [
            'title' => $this->t('plans.title_index', 'Planos Editoriais'),
            'plans' => $this->loader->model('planner')->plansByUser($userId),
            'campaigns' => $this->loader->model('calendar')->filterData()['campaigns'],
            'objectives' => $this->loader->model('calendar')->filterData()['objectives'],
            'channels' => $this->loader->model('calendar')->filterData()['channels'],
            'templates' => $templateService->templates(),
        ]);
    }

    public function store(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $startDate = (string) $this->request->post('start_date');
        $endDate = (string) $this->request->post('end_date');

        if ($startDate === '' || $endDate === '') {
            flash('error', $this->t('plans.flash_period_required', 'Informe inicio e fim do periodo.'));
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
            'name' => trim((string) $this->request->post('name', $this->t('plans.default_plan_name', 'Plano {date}', ['date' => date('Y-m-d H:i')]))),
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

    public function storeTemplate(): void
    {
        $this->boot('client.plans');
        $this->ensurePostWithCsrf();

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $templateSlug = trim((string) $this->request->post('template_slug'));
        $year = (int) $this->request->post('template_year', date('Y'));
        $frequency = trim((string) $this->request->post('template_frequency', 'semanal'));

        if ($year < 1970 || $year > 2100) {
            $year = (int) date('Y');
        }

        $service = new PlanTemplateService();
        $template = $service->findTemplate($templateSlug);

        if (!$template) {
            flash('error', $this->t('plans.flash_invalid_template', 'Template invalido.'));
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
            flash('error', $this->t('plans.flash_not_found_or_forbidden', 'Plano nao encontrado ou sem permissao de acesso.'));
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
            flash('error', $this->t('plans.flash_invalid_item_for_update', 'Item de plano invalido para atualizacao.'));
            $this->redirectToRoute('plans/index');
        }

        $updated = $this->loader->model('planner')->updatePlanItemForUser($itemId, $userId, [
            'status' => (string) $this->request->post('status', 'planned'),
            'manual_note' => (string) $this->request->post('manual_note', ''),
        ]);

        if ($updated) {
            flash('success', $this->t('plans.flash_item_updated', 'Item atualizado com sucesso.'));
        } else {
            flash('error', $this->t('plans.flash_item_update_error', 'Nao foi possivel atualizar o item selecionado.'));
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
            flash('error', $this->t('plans.flash_invalid_plan_for_bulk_update', 'Plano invalido para atualizacao em lote.'));
            $this->redirectToRoute('plans/index');
        }

        $status = $this->normalizeItemStatus((string) $this->request->post('bulk_status', ''));
        if ($status === null) {
            flash('error', $this->t('plans.flash_invalid_bulk_status', 'Status de lote invalido.'));
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
            flash('error', $this->t('plans.flash_bulk_update_no_changes', 'Nenhum item foi atualizado. Verifique a selecao e as permissoes do plano.'));
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
            flash('error', $this->t('plans.flash_not_found_for_export', 'Plano nao encontrado para exportacao.'));
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

        $exporter = new ExportService();
        $csv = $exporter->exportCsv($rows, [
            $this->t('plans.csv_header_planned_date', 'Data planejada'),
            $this->t('plans.csv_header_title', 'Titulo'),
            $this->t('plans.csv_header_format', 'Formato'),
            $this->t('plans.csv_header_status', 'Status'),
            $this->t('plans.csv_header_channels', 'Canais'),
            $this->t('plans.csv_header_description', 'Descricao'),
            $this->t('plans.csv_header_note', 'Observacao'),
        ]);

        $rawName = strtolower((string) ($plan['name'] ?? ('plano-' . $id)));
        $safeName = preg_replace('/[^a-z0-9_-]/', '-', $rawName);
        $safeName = trim((string) $safeName, '-');
        if ($safeName === '') {
            $safeName = 'plano-' . $id;
        }

        $filename = 'nosfirsolis-' . $safeName . '-' . date('Ymd-His') . '.csv';

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
            flash('error', $this->t('plans.flash_note_required', 'Data e observacao sao obrigatorias.'));
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
            'Observacao salva para {date}.',
            ['date' => $noteDate]
        ));
        $this->redirectToRoute('calendar/index?mode=monthly&year=' . date('Y', strtotime($noteDate)) . '&month=' . date('n', strtotime($noteDate)));
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
