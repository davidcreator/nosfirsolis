<?php

namespace Admin\Controller;

class PlansCampaignsController extends BaseController
{
    private const DEFAULT_AI_PER_PAGE = 15;
    private const DEFAULT_CAMPAIGNS_PER_PAGE = 12;
    private const DEFAULT_PLANS_PER_PAGE = 12;
    private const PER_PAGE_OPTIONS = [10, 12, 15, 25, 50];

    public function index(): void
    {
        $this->boot('admin.campaigns');

        $model = $this->loader->model('plans_campaigns');
        $aiService = $this->planCampaignAiManagerService();
        $filters = $this->filtersFromRequest();
        $managers = $aiService->availableManagers();
        $defaultManagerId = $aiService->defaultManagerId();
        $defaultManager = $managers[$defaultManagerId] ?? reset($managers);
        if (!is_array($defaultManager)) {
            $defaultManager = [];
        }

        $aiPerPage = (int) ($filters['pagination']['ai_per_page'] ?? self::DEFAULT_AI_PER_PAGE);
        $campaignPerPage = (int) ($filters['pagination']['campaign_per_page'] ?? self::DEFAULT_CAMPAIGNS_PER_PAGE);
        $planPerPage = (int) ($filters['pagination']['plan_per_page'] ?? self::DEFAULT_PLANS_PER_PAGE);

        $aiPagination = $this->buildPagination(
            $model->usersForAiAssignmentsCount($filters['ai']),
            $aiPerPage,
            (int) ($filters['pagination']['ai_page'] ?? 1)
        );
        $campaignsPagination = $this->buildPagination(
            $model->campaignsCount($filters['campaigns']),
            $campaignPerPage,
            (int) ($filters['pagination']['campaign_page'] ?? 1)
        );
        $plansPagination = $this->buildPagination(
            $model->plansCount($filters['plans']),
            $planPerPage,
            (int) ($filters['pagination']['plan_page'] ?? 1)
        );

        $filters['pagination']['ai_page'] = (int) $aiPagination['page'];
        $filters['pagination']['campaign_page'] = (int) $campaignsPagination['page'];
        $filters['pagination']['plan_page'] = (int) $plansPagination['page'];
        $filters['pagination']['ai_per_page'] = (int) $aiPagination['per_page'];
        $filters['pagination']['campaign_per_page'] = (int) $campaignsPagination['per_page'];
        $filters['pagination']['plan_per_page'] = (int) $plansPagination['per_page'];

        $clients = $model->usersForAiAssignments(
            (int) $aiPagination['per_page'],
            $filters['ai'],
            (int) $aiPagination['offset']
        );
        foreach ($clients as &$client) {
            $assignedManagerId = strtolower(trim((string) ($client['assigned_manager_id'] ?? '')));
            $hasCustom = (int) ($client['has_custom_manager'] ?? 0) === 1;
            $source = $hasCustom ? 'user' : 'default';
            $resolvedManager = $defaultManager;

            if ($hasCustom && isset($managers[$assignedManagerId])) {
                $resolvedManager = (array) $managers[$assignedManagerId];
            }

            $client['assigned_manager_id'] = $hasCustom ? $assignedManagerId : null;
            $client['resolved_manager'] = $resolvedManager;
            $client['manager_source'] = $source;
        }
        unset($client);

        $campaigns = $model->campaignsWithUsage(
            (int) $campaignsPagination['per_page'],
            $filters['campaigns'],
            (int) $campaignsPagination['offset']
        );
        $plans = $model->plansWithUsage(
            (int) $plansPagination['per_page'],
            $filters['plans'],
            (int) $plansPagination['offset']
        );

        $filtersQueryParams = $this->buildFiltersQueryParams($filters);

        $this->render('plans_campaigns/index', [
            'title' => $this->t('plans_campaigns.title_index', 'Gestao de Planos e Campanhas'),
            'summary' => $model->summary(),
            'campaigns' => $campaigns,
            'plans' => $plans,
            'campaign_options' => $model->campaignOptions(),
            'clients' => $clients,
            'ai_managers' => $managers,
            'default_ai_manager_id' => $defaultManagerId,
            'filters' => $filters,
            'filters_query' => $this->buildFiltersQuery($filters),
            'filters_query_params' => $filtersQueryParams,
            'per_page_options' => self::PER_PAGE_OPTIONS,
            'pagination' => [
                'ai' => $aiPagination,
                'campaigns' => $campaignsPagination,
                'plans' => $plansPagination,
            ],
        ]);
    }

    public function saveDefaultManager(): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        $managerId = (string) $this->request->post('default_manager_id', '');
        $updated = $this->planCampaignAiManagerService()->setDefaultManager($managerId);

        if ($updated) {
            flash('success', $this->t('plans_campaigns.flash_default_ai_saved', 'IA padrao atualizada com sucesso.'));
            $this->redirectToIndexWithReturnQuery();
        }

        flash('error', $this->t('plans_campaigns.flash_invalid_ai', 'Selecione uma IA valida.'));
        $this->redirectToIndexWithReturnQuery();
    }

    public function saveClientManager(int $userId): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        if ($userId <= 0) {
            flash('error', $this->t('plans_campaigns.flash_invalid_client', 'Cliente invalido para configuracao de IA.'));
            $this->redirectToIndexWithReturnQuery();
        }

        $user = $this->loader->model('users')->find($userId);
        if (!$user) {
            flash('error', $this->t('plans_campaigns.flash_client_not_found', 'Cliente nao encontrado.'));
            $this->redirectToIndexWithReturnQuery();
        }

        $managerId = trim((string) $this->request->post('manager_id', ''));
        $service = $this->planCampaignAiManagerService();

        if ($managerId === '') {
            $service->clearUserAssignment($userId);
            flash('success', $this->t('plans_campaigns.flash_client_ai_reset', 'IA do cliente voltou para a configuracao padrao.'));
            $this->redirectToIndexWithReturnQuery();
        }

        $saved = $service->assignManagerToUser($userId, $managerId);
        if ($saved) {
            flash('success', $this->t('plans_campaigns.flash_client_ai_saved', 'IA do cliente atualizada com sucesso.'));
            $this->redirectToIndexWithReturnQuery();
        }

        flash('error', $this->t('plans_campaigns.flash_invalid_ai', 'Selecione uma IA valida.'));
        $this->redirectToIndexWithReturnQuery();
    }

    public function updateCampaign(int $campaignId): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        $status = $this->normalizeCampaignStatus((string) $this->request->post('status', 'planned'));
        $startDate = $this->normalizeDateOrNull((string) $this->request->post('start_date', ''));
        $endDate = $this->normalizeDateOrNull((string) $this->request->post('end_date', ''));

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $updated = $this->loader->model('plans_campaigns')->updateCampaignGovernance($campaignId, [
            'status' => $status,
            'objective' => (string) $this->request->post('objective', ''),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if ($updated) {
            flash('success', $this->t('plans_campaigns.flash_campaign_saved', 'Campanha atualizada com sucesso.'));
            $this->redirectToIndexWithReturnQuery();
        }

        flash('error', $this->t('plans_campaigns.flash_campaign_save_error', 'Nao foi possivel atualizar a campanha.'));
        $this->redirectToIndexWithReturnQuery();
    }

    public function updatePlan(int $planId): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        $status = $this->normalizePlanStatus((string) $this->request->post('status', 'draft'));
        $campaignId = (int) $this->request->post('campaign_id', 0);
        $campaignId = $campaignId > 0 ? $campaignId : null;

        $updated = $this->loader->model('plans_campaigns')->updatePlanGovernance($planId, [
            'status' => $status,
            'campaign_id' => $campaignId,
            'notes' => (string) $this->request->post('notes', ''),
        ]);

        if ($updated) {
            flash('success', $this->t('plans_campaigns.flash_plan_saved', 'Plano atualizado com sucesso.'));
            $this->redirectToIndexWithReturnQuery();
        }

        flash('error', $this->t('plans_campaigns.flash_plan_save_error', 'Nao foi possivel atualizar o plano.'));
        $this->redirectToIndexWithReturnQuery();
    }

    private function filtersFromRequest(): array
    {
        return [
            'ai' => [
                'q' => mb_substr(trim((string) $this->request->get('ai_q', '')), 0, 120),
                'status' => $this->normalizeUserStatusFilter((string) $this->request->get('ai_status', 'all')),
                'source' => $this->normalizeAiSourceFilter((string) $this->request->get('ai_source', 'all')),
            ],
            'campaigns' => [
                'q' => mb_substr(trim((string) $this->request->get('campaign_q', '')), 0, 120),
                'status' => $this->normalizeCampaignStatusFilter((string) $this->request->get('campaign_status', 'all')),
            ],
            'plans' => [
                'q' => mb_substr(trim((string) $this->request->get('plan_q', '')), 0, 120),
                'status' => $this->normalizePlanStatusFilter((string) $this->request->get('plan_status', 'all')),
                'campaign_id' => $this->normalizePositiveId((string) $this->request->get('plan_campaign_id', '0')),
            ],
            'pagination' => [
                'ai_page' => $this->normalizePage((string) $this->request->get('ai_page', '1')),
                'campaign_page' => $this->normalizePage((string) $this->request->get('campaign_page', '1')),
                'plan_page' => $this->normalizePage((string) $this->request->get('plan_page', '1')),
                'ai_per_page' => $this->normalizePerPage(
                    (string) $this->request->get('ai_per_page', (string) self::DEFAULT_AI_PER_PAGE),
                    self::DEFAULT_AI_PER_PAGE
                ),
                'campaign_per_page' => $this->normalizePerPage(
                    (string) $this->request->get('campaign_per_page', (string) self::DEFAULT_CAMPAIGNS_PER_PAGE),
                    self::DEFAULT_CAMPAIGNS_PER_PAGE
                ),
                'plan_per_page' => $this->normalizePerPage(
                    (string) $this->request->get('plan_per_page', (string) self::DEFAULT_PLANS_PER_PAGE),
                    self::DEFAULT_PLANS_PER_PAGE
                ),
            ],
        ];
    }

    private function buildFiltersQuery(array $filters): string
    {
        return http_build_query($this->buildFiltersQueryParams($filters));
    }

    private function buildFiltersQueryParams(array $filters): array
    {
        $query = [];

        $ai = (array) ($filters['ai'] ?? []);
        if ((string) ($ai['q'] ?? '') !== '') {
            $query['ai_q'] = (string) $ai['q'];
        }
        if ((string) ($ai['status'] ?? 'all') !== 'all') {
            $query['ai_status'] = (string) $ai['status'];
        }
        if ((string) ($ai['source'] ?? 'all') !== 'all') {
            $query['ai_source'] = (string) $ai['source'];
        }

        $campaigns = (array) ($filters['campaigns'] ?? []);
        if ((string) ($campaigns['q'] ?? '') !== '') {
            $query['campaign_q'] = (string) $campaigns['q'];
        }
        if ((string) ($campaigns['status'] ?? 'all') !== 'all') {
            $query['campaign_status'] = (string) $campaigns['status'];
        }

        $plans = (array) ($filters['plans'] ?? []);
        if ((string) ($plans['q'] ?? '') !== '') {
            $query['plan_q'] = (string) $plans['q'];
        }
        if ((string) ($plans['status'] ?? 'all') !== 'all') {
            $query['plan_status'] = (string) $plans['status'];
        }
        if ((int) ($plans['campaign_id'] ?? 0) > 0) {
            $query['plan_campaign_id'] = (int) $plans['campaign_id'];
        }

        $pagination = (array) ($filters['pagination'] ?? []);
        if ((int) ($pagination['ai_page'] ?? 1) > 1) {
            $query['ai_page'] = (int) $pagination['ai_page'];
        }
        if ((int) ($pagination['campaign_page'] ?? 1) > 1) {
            $query['campaign_page'] = (int) $pagination['campaign_page'];
        }
        if ((int) ($pagination['plan_page'] ?? 1) > 1) {
            $query['plan_page'] = (int) $pagination['plan_page'];
        }
        if ((int) ($pagination['ai_per_page'] ?? self::DEFAULT_AI_PER_PAGE) !== self::DEFAULT_AI_PER_PAGE) {
            $query['ai_per_page'] = (int) $pagination['ai_per_page'];
        }
        if ((int) ($pagination['campaign_per_page'] ?? self::DEFAULT_CAMPAIGNS_PER_PAGE) !== self::DEFAULT_CAMPAIGNS_PER_PAGE) {
            $query['campaign_per_page'] = (int) $pagination['campaign_per_page'];
        }
        if ((int) ($pagination['plan_per_page'] ?? self::DEFAULT_PLANS_PER_PAGE) !== self::DEFAULT_PLANS_PER_PAGE) {
            $query['plan_per_page'] = (int) $pagination['plan_per_page'];
        }

        return $query;
    }

    private function redirectToIndexWithReturnQuery(): never
    {
        $query = $this->normalizeReturnQuery((string) $this->request->post('_return_qs', ''));
        $route = 'plans_campaigns/index';
        if ($query !== '') {
            $route .= '?' . $query;
        }

        $this->redirectToRoute($route);
    }

    private function normalizeReturnQuery(string $query): string
    {
        $query = ltrim(trim($query), '?');
        $query = preg_replace('/[^a-zA-Z0-9_\-=&%\.]/', '', $query) ?? '';
        return mb_substr($query, 0, 1200);
    }

    private function normalizeUserStatusFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
    }

    private function normalizeAiSourceFilter(string $source): string
    {
        $source = strtolower(trim($source));
        return in_array($source, ['all', 'default', 'custom'], true) ? $source : 'all';
    }

    private function normalizeCampaignStatusFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['all', 'planned', 'active', 'completed', 'archived'], true)
            ? $status
            : 'all';
    }

    private function normalizePlanStatusFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['all', 'draft', 'active', 'archived'], true)
            ? $status
            : 'all';
    }

    private function normalizePositiveId(string $value): int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return 0;
        }

        $id = (int) $value;
        return $id > 0 ? $id : 0;
    }

    private function normalizePage(string $value): int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return 1;
        }

        $page = (int) $value;
        return max(1, min(5000, $page));
    }

    private function normalizePerPage(string $value, int $default): int
    {
        $value = trim($value);
        $fallback = in_array($default, self::PER_PAGE_OPTIONS, true)
            ? $default
            : (int) (self::PER_PAGE_OPTIONS[0] ?? 10);
        if ($value === '' || !ctype_digit($value)) {
            return $fallback;
        }

        $perPage = (int) $value;
        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : $fallback;
    }

    private function buildPagination(int $total, int $perPage, int $requestedPage): array
    {
        $perPage = max(1, $perPage);
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($requestedPage, $totalPages));
        $offset = ($page - 1) * $perPage;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = $total > 0 ? min($total, $offset + $perPage) : 0;

        return [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function normalizeCampaignStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['planned', 'active', 'completed', 'archived'], true)
            ? $status
            : 'planned';
    }

    private function normalizePlanStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['draft', 'active', 'archived'], true)
            ? $status
            : 'draft';
    }

    private function normalizeDateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);
        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
