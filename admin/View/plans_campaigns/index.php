<?php
$summary = (array) ($summary ?? []);
$campaigns = (array) ($campaigns ?? []);
$plans = (array) ($plans ?? []);
$campaignOptions = (array) ($campaign_options ?? []);
$clients = (array) ($clients ?? []);
$aiManagers = (array) ($ai_managers ?? []);
$defaultAiManagerId = (string) ($default_ai_manager_id ?? '');
$filters = (array) ($filters ?? []);
$filtersQuery = (string) ($filters_query ?? '');
$filtersQueryParams = (array) ($filters_query_params ?? []);
$aiFilters = (array) ($filters['ai'] ?? []);
$campaignFilters = (array) ($filters['campaigns'] ?? []);
$planFilters = (array) ($filters['plans'] ?? []);
$pagination = (array) ($pagination ?? []);
$aiPagination = (array) ($pagination['ai'] ?? []);
$campaignsPagination = (array) ($pagination['campaigns'] ?? []);
$plansPagination = (array) ($pagination['plans'] ?? []);
$perPageOptions = array_values(array_unique(array_map('intval', (array) ($per_page_options ?? [10, 12, 15, 25, 50]))));
sort($perPageOptions);
if (empty($perPageOptions)) {
    $perPageOptions = [10, 25, 50];
}
$aiPerPageSelected = (int) ($aiPagination['per_page'] ?? 15);
$campaignPerPageSelected = (int) ($campaignsPagination['per_page'] ?? 12);
$planPerPageSelected = (int) ($plansPagination['per_page'] ?? 12);

$buildPageUrl = static function (string $pageKey, int $page, array $queryParams): string {
    $params = $queryParams;
    if ($page <= 1) {
        unset($params[$pageKey]);
    } else {
        $params[$pageKey] = $page;
    }

    $query = http_build_query($params);
    return route_url('plans_campaigns/index' . ($query !== '' ? '?' . $query : ''));
};

$buildPageSteps = static function (int $currentPage, int $totalPages, int $radius = 2): array {
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($currentPage, $totalPages));
    if ($totalPages <= 1) {
        return [1];
    }

    $radius = max(1, min(6, $radius));
    $pages = [1, $totalPages];
    for ($page = $currentPage - $radius; $page <= $currentPage + $radius; $page++) {
        if ($page > 1 && $page < $totalPages) {
            $pages[] = $page;
        }
    }

    $pages = array_values(array_unique(array_map('intval', $pages)));
    sort($pages);

    $steps = [];
    $previous = 0;
    foreach ($pages as $page) {
        if ($previous > 0 && ($page - $previous) > 1) {
            if (($page - $previous) === 2) {
                $steps[] = $previous + 1;
            } else {
                $steps[] = '...';
            }
        }
        $steps[] = $page;
        $previous = $page;
    }

    return $steps;
};

$campaignStatusLabels = [
    'planned' => 'Planejada',
    'active' => 'Ativa',
    'completed' => 'Concluida',
    'archived' => 'Arquivada',
];

$planStatusLabels = [
    'draft' => 'Rascunho',
    'active' => 'Ativo',
    'archived' => 'Arquivado',
];
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-diagram-project"></i> Plans & Campaigns Control</span>
        <h1><i class="fa-solid fa-sitemap"></i> <?= e($t('plans_campaigns.heading_index', 'Gestao completa de planos e campanhas')) ?></h1>
        <p><?= e($t('plans_campaigns.description_index', 'Central unica para governanca de IA, campanhas de conteudo e planos editoriais dos clientes.')) ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#ai-governance"><i class="fa-solid fa-robot"></i> Governanca de IA</a>
        <a class="btn" href="#campaigns-governance"><i class="fa-solid fa-bullhorn"></i> Campanhas</a>
        <a class="btn" href="#plans-governance"><i class="fa-solid fa-layer-group"></i> Planos</a>
        <a class="btn" href="<?= e(route_url('campaigns/index')) ?>"><i class="fa-solid fa-pen-to-square"></i> CRUD completo campanhas</a>
    </div>
</section>

<section class="panel">
    <div class="cards-grid kpi-grid">
        <article class="kpi-card accent-blue">
            <span class="kpi-icon"><i class="fa-solid fa-bullhorn"></i></span>
            <div>
                <strong><?= (int) ($summary['campaigns_total'] ?? 0) ?></strong>
                <span>Campanhas totais</span>
            </div>
        </article>
        <article class="kpi-card accent-green">
            <span class="kpi-icon"><i class="fa-solid fa-signal"></i></span>
            <div>
                <strong><?= (int) ($summary['campaigns_active'] ?? 0) ?></strong>
                <span>Campanhas ativas</span>
            </div>
        </article>
        <article class="kpi-card accent-purple">
            <span class="kpi-icon"><i class="fa-solid fa-layer-group"></i></span>
            <div>
                <strong><?= (int) ($summary['plans_total'] ?? 0) ?></strong>
                <span>Planos totais</span>
            </div>
        </article>
        <article class="kpi-card accent-cyan">
            <span class="kpi-icon"><i class="fa-solid fa-check-double"></i></span>
            <div>
                <strong><?= (int) ($summary['plans_active'] ?? 0) ?></strong>
                <span>Planos ativos</span>
            </div>
        </article>
        <article class="kpi-card accent-red">
            <span class="kpi-icon"><i class="fa-solid fa-list-check"></i></span>
            <div>
                <strong><?= (int) ($summary['plan_items_total'] ?? 0) ?></strong>
                <span>Itens de planos</span>
            </div>
        </article>
        <article class="kpi-card accent-amber">
            <span class="kpi-icon"><i class="fa-solid fa-users"></i></span>
            <div>
                <strong><?= (int) ($summary['clients_with_plans'] ?? 0) ?></strong>
                <span>Clientes com planos</span>
            </div>
        </article>
    </div>
</section>

<section class="panel" id="ai-governance">
    <div class="panel-header">
        <h2><i class="fa-solid fa-robot"></i> <?= e($t('plans_campaigns.heading_ai', 'Governanca de IA para clientes')) ?></h2>
        <span class="meta-text"><?= e($t('plans_campaigns.meta_ai', 'Defina a IA padrao e personalize por cliente quando necessario.')) ?></span>
    </div>

    <form method="post" action="<?= e(route_url('plans_campaigns/saveDefaultManager')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="_return_qs" value="<?= e($filtersQuery) ?>">
        <label>IA padrao global
            <select name="default_manager_id" required>
                <?php foreach ($aiManagers as $manager): ?>
                    <?php $managerId = (string) ($manager['id'] ?? ''); ?>
                    <?php if ($managerId === '') continue; ?>
                    <option value="<?= e($managerId) ?>"<?= $defaultAiManagerId === $managerId ? ' selected' : '' ?>>
                        <?= e((string) ($manager['name'] ?? $managerId)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar IA padrao</button>
    </form>

    <form method="get" action="<?= e(route_url('plans_campaigns/index')) ?>" class="filters-grid">
        <label>Buscar cliente
            <input type="search" name="ai_q" value="<?= e((string) ($aiFilters['q'] ?? '')) ?>" placeholder="Nome ou e-mail">
        </label>
        <label>Status do cliente
            <select name="ai_status">
                <?php $aiStatus = (string) ($aiFilters['status'] ?? 'all'); ?>
                <option value="all"<?= $aiStatus === 'all' ? ' selected' : '' ?>>Todos</option>
                <option value="active"<?= $aiStatus === 'active' ? ' selected' : '' ?>>Ativos</option>
                <option value="inactive"<?= $aiStatus === 'inactive' ? ' selected' : '' ?>>Inativos</option>
            </select>
        </label>
        <label>Origem da IA
            <?php $aiSource = (string) ($aiFilters['source'] ?? 'all'); ?>
            <select name="ai_source">
                <option value="all"<?= $aiSource === 'all' ? ' selected' : '' ?>>Todas</option>
                <option value="default"<?= $aiSource === 'default' ? ' selected' : '' ?>>Padrao global</option>
                <option value="custom"<?= $aiSource === 'custom' ? ' selected' : '' ?>>Personalizada</option>
            </select>
        </label>
        <label>Itens por pagina
            <select name="ai_per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $perPageOption): ?>
                    <option value="<?= $perPageOption ?>"<?= $aiPerPageSelected === $perPageOption ? ' selected' : '' ?>><?= $perPageOption ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <input type="hidden" name="campaign_q" value="<?= e((string) ($campaignFilters['q'] ?? '')) ?>">
        <input type="hidden" name="campaign_status" value="<?= e((string) ($campaignFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="plan_q" value="<?= e((string) ($planFilters['q'] ?? '')) ?>">
        <input type="hidden" name="plan_status" value="<?= e((string) ($planFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="plan_campaign_id" value="<?= e((string) ($planFilters['campaign_id'] ?? 0)) ?>">
        <input type="hidden" name="campaign_per_page" value="<?= $campaignPerPageSelected ?>">
        <input type="hidden" name="plan_per_page" value="<?= $planPerPageSelected ?>">
        <input type="hidden" name="campaign_page" value="<?= (int) ($campaignsPagination['page'] ?? 1) ?>">
        <input type="hidden" name="plan_page" value="<?= (int) ($plansPagination['page'] ?? 1) ?>">

        <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar clientes</button>
        <a class="btn btn-muted" href="<?= e(route_url('plans_campaigns/index')) ?>"><i class="fa-solid fa-rotate-right"></i> Limpar</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Cliente</th>
                <th>Planos</th>
                <th>Campanhas</th>
                <th>IA em uso</th>
                <th>Ajustar IA</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($clients)): ?>
                <tr><td colspan="5">Nenhum cliente disponivel para configuracao.</td></tr>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <?php
                    $clientId = (int) ($client['id'] ?? 0);
                    $resolvedManager = (array) ($client['resolved_manager'] ?? []);
                    $managerSource = (string) ($client['manager_source'] ?? 'default');
                    $assignedManagerId = (string) ($client['assigned_manager_id'] ?? '');
                    $sourceLabel = $managerSource === 'user' ? 'Personalizada' : 'Padrao global';
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($client['name'] ?? '-')) ?></strong><br>
                            <small class="meta-text"><?= e((string) ($client['email'] ?? '-')) ?></small>
                        </td>
                        <td>
                            <?= (int) ($client['plans_count'] ?? 0) ?>
                            <br><small class="meta-text">Ativos: <?= (int) ($client['active_plans_count'] ?? 0) ?></small>
                        </td>
                        <td><?= (int) ($client['campaigns_count'] ?? 0) ?></td>
                        <td>
                            <span class="table-chip"><?= e((string) ($resolvedManager['name'] ?? 'Strategist')) ?></span>
                            <br><small class="meta-text"><?= e($sourceLabel) ?></small>
                        </td>
                        <td>
                            <form method="post" action="<?= e(route_url('plans_campaigns/saveClientManager/' . $clientId)) ?>" class="admin-list-plan-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_return_qs" value="<?= e($filtersQuery) ?>">
                                <select name="manager_id">
                                    <option value="">Usar padrao global</option>
                                    <?php foreach ($aiManagers as $manager): ?>
                                        <?php $managerId = (string) ($manager['id'] ?? ''); ?>
                                        <?php if ($managerId === '') continue; ?>
                                        <option value="<?= e($managerId) ?>"<?= $assignedManagerId === $managerId ? ' selected' : '' ?>>
                                            <?= e((string) ($manager['name'] ?? $managerId)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-sm"><i class="fa-solid fa-sliders"></i> Atualizar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) ($aiPagination['total'] ?? 0) > 0): ?>
        <div class="panel-head-inline">
            <span class="meta-text">
                Exibindo <?= (int) ($aiPagination['from'] ?? 0) ?>-<?= (int) ($aiPagination['to'] ?? 0) ?>
                de <?= (int) ($aiPagination['total'] ?? 0) ?> cliente(s)
            </span>
            <div class="pagination-nav">
                <?php $aiPage = (int) ($aiPagination['page'] ?? 1); ?>
                <?php $aiTotalPages = (int) ($aiPagination['total_pages'] ?? 1); ?>
                <?php $aiSteps = $buildPageSteps($aiPage, $aiTotalPages); ?>
                <?php if ($aiPage > 1): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('ai_page', $aiPage - 1, $filtersQueryParams)) ?>">
                        <i class="fa-solid fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>
                <div class="pagination-pages" aria-label="Paginas clientes IA">
                    <?php foreach ($aiSteps as $step): ?>
                        <?php if ($step === '...'): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php else: ?>
                            <?php $pageNumber = (int) $step; ?>
                            <?php if ($pageNumber === $aiPage): ?>
                                <span class="pagination-page is-active"><?= $pageNumber ?></span>
                            <?php else: ?>
                                <a class="pagination-page" href="<?= e($buildPageUrl('ai_page', $pageNumber, $filtersQueryParams)) ?>"><?= $pageNumber ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($aiPage < $aiTotalPages): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('ai_page', $aiPage + 1, $filtersQueryParams)) ?>">
                        Proxima <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled">Proxima <i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="panel" id="campaigns-governance">
    <div class="panel-header">
        <h2><i class="fa-solid fa-bullhorn"></i> <?= e($t('plans_campaigns.heading_campaigns', 'Gestao operacional de campanhas')) ?></h2>
        <span class="meta-text"><?= e($t('plans_campaigns.meta_campaigns', 'Atualize status, objetivo e periodo sem sair da central.')) ?></span>
    </div>

    <form method="get" action="<?= e(route_url('plans_campaigns/index')) ?>" class="filters-grid">
        <label>Buscar campanha
            <input type="search" name="campaign_q" value="<?= e((string) ($campaignFilters['q'] ?? '')) ?>" placeholder="Nome, objetivo ou descricao">
        </label>
        <label>Status da campanha
            <?php $campaignStatusFilter = (string) ($campaignFilters['status'] ?? 'all'); ?>
            <select name="campaign_status">
                <option value="all"<?= $campaignStatusFilter === 'all' ? ' selected' : '' ?>>Todos</option>
                <?php foreach ($campaignStatusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $campaignStatusFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Itens por pagina
            <select name="campaign_per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $perPageOption): ?>
                    <option value="<?= $perPageOption ?>"<?= $campaignPerPageSelected === $perPageOption ? ' selected' : '' ?>><?= $perPageOption ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <input type="hidden" name="ai_q" value="<?= e((string) ($aiFilters['q'] ?? '')) ?>">
        <input type="hidden" name="ai_status" value="<?= e((string) ($aiFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="ai_source" value="<?= e((string) ($aiFilters['source'] ?? 'all')) ?>">
        <input type="hidden" name="ai_per_page" value="<?= $aiPerPageSelected ?>">
        <input type="hidden" name="plan_q" value="<?= e((string) ($planFilters['q'] ?? '')) ?>">
        <input type="hidden" name="plan_status" value="<?= e((string) ($planFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="plan_campaign_id" value="<?= e((string) ($planFilters['campaign_id'] ?? 0)) ?>">
        <input type="hidden" name="plan_per_page" value="<?= $planPerPageSelected ?>">
        <input type="hidden" name="ai_page" value="<?= (int) ($aiPagination['page'] ?? 1) ?>">
        <input type="hidden" name="plan_page" value="<?= (int) ($plansPagination['page'] ?? 1) ?>">

        <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar campanhas</button>
        <a class="btn btn-muted" href="<?= e(route_url('plans_campaigns/index')) ?>"><i class="fa-solid fa-rotate-right"></i> Limpar</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Campanha</th>
                <th>Planos vinculados</th>
                <th>Itens</th>
                <th>Governanca</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($campaigns)): ?>
                <tr><td colspan="5">Nenhuma campanha cadastrada.</td></tr>
            <?php else: ?>
                <?php foreach ($campaigns as $campaign): ?>
                    <?php $campaignId = (int) ($campaign['id'] ?? 0); ?>
                    <tr>
                        <td>#<?= $campaignId ?></td>
                        <td>
                            <strong><?= e((string) ($campaign['name'] ?? '-')) ?></strong>
                            <br><small class="meta-text"><?= e((string) ($campaign['description'] ?? '')) ?></small>
                        </td>
                        <td><?= (int) ($campaign['plans_count'] ?? 0) ?></td>
                        <td><?= (int) ($campaign['items_count'] ?? 0) ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('plans_campaigns/updateCampaign/' . $campaignId)) ?>" class="item-update-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_return_qs" value="<?= e($filtersQuery) ?>">
                                <div class="item-update-grid">
                                    <label>Status
                                        <select name="status">
                                            <?php $campaignStatus = strtolower((string) ($campaign['status'] ?? 'planned')); ?>
                                            <?php foreach ($campaignStatusLabels as $value => $label): ?>
                                                <option value="<?= e($value) ?>"<?= $campaignStatus === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Inicio
                                        <input type="date" name="start_date" value="<?= e((string) ($campaign['start_date'] ?? '')) ?>">
                                    </label>
                                    <label>Fim
                                        <input type="date" name="end_date" value="<?= e((string) ($campaign['end_date'] ?? '')) ?>">
                                    </label>
                                    <label>Objetivo
                                        <input type="text" name="objective" value="<?= e((string) ($campaign['objective'] ?? '')) ?>">
                                    </label>
                                    <button type="submit" class="btn-compact"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) ($campaignsPagination['total'] ?? 0) > 0): ?>
        <div class="panel-head-inline">
            <span class="meta-text">
                Exibindo <?= (int) ($campaignsPagination['from'] ?? 0) ?>-<?= (int) ($campaignsPagination['to'] ?? 0) ?>
                de <?= (int) ($campaignsPagination['total'] ?? 0) ?> campanha(s)
            </span>
            <div class="pagination-nav">
                <?php $campaignPage = (int) ($campaignsPagination['page'] ?? 1); ?>
                <?php $campaignTotalPages = (int) ($campaignsPagination['total_pages'] ?? 1); ?>
                <?php $campaignSteps = $buildPageSteps($campaignPage, $campaignTotalPages); ?>
                <?php if ($campaignPage > 1): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('campaign_page', $campaignPage - 1, $filtersQueryParams)) ?>">
                        <i class="fa-solid fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>
                <div class="pagination-pages" aria-label="Paginas campanhas">
                    <?php foreach ($campaignSteps as $step): ?>
                        <?php if ($step === '...'): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php else: ?>
                            <?php $pageNumber = (int) $step; ?>
                            <?php if ($pageNumber === $campaignPage): ?>
                                <span class="pagination-page is-active"><?= $pageNumber ?></span>
                            <?php else: ?>
                                <a class="pagination-page" href="<?= e($buildPageUrl('campaign_page', $pageNumber, $filtersQueryParams)) ?>"><?= $pageNumber ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($campaignPage < $campaignTotalPages): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('campaign_page', $campaignPage + 1, $filtersQueryParams)) ?>">
                        Proxima <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled">Proxima <i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="panel" id="plans-governance">
    <div class="panel-header">
        <h2><i class="fa-solid fa-layer-group"></i> <?= e($t('plans_campaigns.heading_plans', 'Gestao operacional de planos')) ?></h2>
        <span class="meta-text"><?= e($t('plans_campaigns.meta_plans', 'Ajuste status, campanha vinculada e notas de governanca.')) ?></span>
    </div>

    <form method="get" action="<?= e(route_url('plans_campaigns/index')) ?>" class="filters-grid">
        <label>Buscar plano
            <input type="search" name="plan_q" value="<?= e((string) ($planFilters['q'] ?? '')) ?>" placeholder="Plano, cliente, e-mail ou campanha">
        </label>
        <label>Status do plano
            <?php $planStatusFilter = (string) ($planFilters['status'] ?? 'all'); ?>
            <select name="plan_status">
                <option value="all"<?= $planStatusFilter === 'all' ? ' selected' : '' ?>>Todos</option>
                <?php foreach ($planStatusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $planStatusFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Campanha vinculada
            <?php $planCampaignFilter = (int) ($planFilters['campaign_id'] ?? 0); ?>
            <select name="plan_campaign_id">
                <option value="0">Todas</option>
                <?php foreach ($campaignOptions as $campaignOption): ?>
                    <?php $optionId = (int) ($campaignOption['id'] ?? 0); ?>
                    <option value="<?= $optionId ?>"<?= $planCampaignFilter === $optionId ? ' selected' : '' ?>>
                        <?= e((string) ($campaignOption['name'] ?? ('Campanha #' . $optionId))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Itens por pagina
            <select name="plan_per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $perPageOption): ?>
                    <option value="<?= $perPageOption ?>"<?= $planPerPageSelected === $perPageOption ? ' selected' : '' ?>><?= $perPageOption ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <input type="hidden" name="ai_q" value="<?= e((string) ($aiFilters['q'] ?? '')) ?>">
        <input type="hidden" name="ai_status" value="<?= e((string) ($aiFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="ai_source" value="<?= e((string) ($aiFilters['source'] ?? 'all')) ?>">
        <input type="hidden" name="ai_per_page" value="<?= $aiPerPageSelected ?>">
        <input type="hidden" name="campaign_q" value="<?= e((string) ($campaignFilters['q'] ?? '')) ?>">
        <input type="hidden" name="campaign_status" value="<?= e((string) ($campaignFilters['status'] ?? 'all')) ?>">
        <input type="hidden" name="campaign_per_page" value="<?= $campaignPerPageSelected ?>">
        <input type="hidden" name="ai_page" value="<?= (int) ($aiPagination['page'] ?? 1) ?>">
        <input type="hidden" name="campaign_page" value="<?= (int) ($campaignsPagination['page'] ?? 1) ?>">

        <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar planos</button>
        <a class="btn btn-muted" href="<?= e(route_url('plans_campaigns/index')) ?>"><i class="fa-solid fa-rotate-right"></i> Limpar</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Periodo</th>
                <th>Campanha</th>
                <th>Itens</th>
                <th>Governanca</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($plans)): ?>
                <tr><td colspan="6">Nenhum plano encontrado.</td></tr>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <?php
                    $planId = (int) ($plan['id'] ?? 0);
                    $planStatus = strtolower((string) ($plan['status'] ?? 'draft'));
                    $planCampaignId = (int) ($plan['campaign_id'] ?? 0);
                    ?>
                    <tr>
                        <td>#<?= $planId ?></td>
                        <td>
                            <strong><?= e((string) ($plan['user_name'] ?? '-')) ?></strong><br>
                            <small class="meta-text"><?= e((string) ($plan['user_email'] ?? '-')) ?></small>
                        </td>
                        <td>
                            <?= e((string) ($plan['start_date'] ?? '-')) ?> ate <?= e((string) ($plan['end_date'] ?? '-')) ?>
                        </td>
                        <td><?= e((string) ($plan['campaign_name'] ?? '-')) ?></td>
                        <td>
                            <?= (int) ($plan['total_items'] ?? 0) ?>
                            <br><small class="meta-text">Publicados: <?= (int) ($plan['published_items'] ?? 0) ?></small>
                        </td>
                        <td>
                            <form method="post" action="<?= e(route_url('plans_campaigns/updatePlan/' . $planId)) ?>" class="item-update-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_return_qs" value="<?= e($filtersQuery) ?>">
                                <div class="item-update-grid">
                                    <label>Status
                                        <select name="status">
                                            <?php foreach ($planStatusLabels as $value => $label): ?>
                                                <option value="<?= e($value) ?>"<?= $planStatus === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Campanha
                                        <select name="campaign_id">
                                            <option value="0">Sem campanha</option>
                                            <?php foreach ($campaignOptions as $campaignOption): ?>
                                                <?php $optionId = (int) ($campaignOption['id'] ?? 0); ?>
                                                <option value="<?= $optionId ?>"<?= $planCampaignId === $optionId ? ' selected' : '' ?>>
                                                    <?= e((string) ($campaignOption['name'] ?? ('Campanha #' . $optionId))) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Notas
                                        <textarea name="notes" rows="2"><?= e((string) ($plan['notes'] ?? '')) ?></textarea>
                                    </label>
                                    <button type="submit" class="btn-compact"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) ($plansPagination['total'] ?? 0) > 0): ?>
        <div class="panel-head-inline">
            <span class="meta-text">
                Exibindo <?= (int) ($plansPagination['from'] ?? 0) ?>-<?= (int) ($plansPagination['to'] ?? 0) ?>
                de <?= (int) ($plansPagination['total'] ?? 0) ?> plano(s)
            </span>
            <div class="pagination-nav">
                <?php $planPage = (int) ($plansPagination['page'] ?? 1); ?>
                <?php $planTotalPages = (int) ($plansPagination['total_pages'] ?? 1); ?>
                <?php $planSteps = $buildPageSteps($planPage, $planTotalPages); ?>
                <?php if ($planPage > 1): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('plan_page', $planPage - 1, $filtersQueryParams)) ?>">
                        <i class="fa-solid fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>
                <div class="pagination-pages" aria-label="Paginas planos">
                    <?php foreach ($planSteps as $step): ?>
                        <?php if ($step === '...'): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php else: ?>
                            <?php $pageNumber = (int) $step; ?>
                            <?php if ($pageNumber === $planPage): ?>
                                <span class="pagination-page is-active"><?= $pageNumber ?></span>
                            <?php else: ?>
                                <a class="pagination-page" href="<?= e($buildPageUrl('plan_page', $pageNumber, $filtersQueryParams)) ?>"><?= $pageNumber ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($planPage < $planTotalPages): ?>
                    <a class="btn-link pagination-arrow" href="<?= e($buildPageUrl('plan_page', $planPage + 1, $filtersQueryParams)) ?>">
                        Proxima <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="btn-link pagination-arrow is-disabled">Proxima <i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
