<?php
$plansTotal = (int) ($overview['plans_total'] ?? 0);
$itemsTotal = (int) ($overview['items_total'] ?? 0);
$campaignsActive = (int) ($overview['campaigns_active'] ?? 0);
$suggestionsTotal = (int) ($overview['suggestions_total'] ?? 0);
$upcomingItems = (array) ($overview['upcoming_items'] ?? []);
$featureFlags = (array) ($feature_flags ?? []);
$executiveEnabled = (bool) ($featureFlags['dashboard.executive'] ?? true);
$executive = (array) ($overview['executive'] ?? []);

$statusCounter = [
    'planned' => 0,
    'scheduled' => 0,
    'published' => 0,
    'skipped' => 0,
];
foreach ($upcomingItems as $upcomingItem) {
    $status = strtolower((string) ($upcomingItem['status'] ?? 'planned'));
    if (!array_key_exists($status, $statusCounter)) {
        $statusCounter[$status] = 0;
    }
    $statusCounter[$status] = (int) $statusCounter[$status] + 1;
}
$pipelineTotal = max(1, array_sum($statusCounter));
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-compass-drafting"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h2><?= e($app_name ?? 'Solis') ?></h2>
        <p><?= e($t('dashboard.hero_description', 'Organize campanhas com clareza, acompanhe o ritmo das publicacoes e execute sua estrategia com consistencia anual.')) ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#dashboard-pipeline"><i class="fa-solid fa-diagram-project"></i> <?= e($t('dashboard.action_pipeline', 'Pipeline')) ?></a>
        <a class="btn" href="#dashboard-upcoming"><i class="fa-solid fa-clock"></i> <?= e($t('dashboard.action_upcoming', 'Proximas publicacoes')) ?></a>
        <a class="btn" href="<?= e(route_url('calendar/index')) ?>"><i class="fa-solid fa-calendar-days"></i> <?= e($t('dashboard.action_open_calendar', 'Abrir calendario')) ?></a>
        <?php if (!array_key_exists('tracking.campaign_links', $featureFlags) || !empty($featureFlags['tracking.campaign_links'])): ?>
            <a class="btn" href="<?= e(route_url('tracking/index')) ?>"><i class="fa-solid fa-link"></i> Tracking</a>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="stats-grid kpi-grid">
        <article class="kpi-card accent-blue">
            <span class="kpi-icon"><i class="fa-solid fa-folder-tree"></i></span>
            <div><strong><?= $plansTotal ?></strong><span><?= e($t('dashboard.metric_plans', 'Planos editoriais')) ?></span></div>
        </article>
        <article class="kpi-card accent-green">
            <span class="kpi-icon"><i class="fa-solid fa-clipboard-list"></i></span>
            <div><strong><?= $itemsTotal ?></strong><span><?= e($t('dashboard.metric_items', 'Itens planejados')) ?></span></div>
        </article>
        <article class="kpi-card accent-purple">
            <span class="kpi-icon"><i class="fa-solid fa-bullhorn"></i></span>
            <div><strong><?= $campaignsActive ?></strong><span><?= e($t('dashboard.metric_campaigns', 'Campanhas ativas')) ?></span></div>
        </article>
        <article class="kpi-card accent-amber">
            <span class="kpi-icon"><i class="fa-solid fa-lightbulb"></i></span>
            <div><strong><?= $suggestionsTotal ?></strong><span><?= e($t('dashboard.metric_suggestions', 'Sugestoes estrategicas')) ?></span></div>
        </article>
    </div>
</section>

<?php if ($executiveEnabled): ?>
<section class="panel" id="dashboard-executive">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-gauge-high"></i> <?= e($t('dashboard.executive_title', 'Painel executivo')) ?></h3>
        <span class="meta-text"><?= e($t('dashboard.executive_subtitle', 'Visao operacional consolidada do Solis')) ?></span>
    </div>

    <div class="stats-grid kpi-grid">
        <article class="kpi-card accent-cyan">
            <span class="kpi-icon"><i class="fa-solid fa-link"></i></span>
            <div><strong><?= (int) ($executive['tracking_links_total'] ?? 0) ?></strong><span><?= e($t('dashboard.executive_tracking_links', 'Links rastreados')) ?></span></div>
        </article>
        <article class="kpi-card accent-amber">
            <span class="kpi-icon"><i class="fa-solid fa-arrow-pointer"></i></span>
            <div><strong><?= (int) ($executive['tracking_clicks_total'] ?? 0) ?></strong><span><?= e($t('dashboard.executive_tracking_clicks', 'Cliques de campanha')) ?></span></div>
        </article>
        <article class="kpi-card accent-purple">
            <span class="kpi-icon"><i class="fa-solid fa-paper-plane"></i></span>
            <div><strong><?= (int) ($executive['publications_published'] ?? 0) ?></strong><span><?= e($t('dashboard.executive_published', 'Publicacoes concluidas')) ?></span></div>
        </article>
        <article class="kpi-card accent-red">
            <span class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><strong><?= (int) ($executive['job_alerts_open'] ?? 0) ?></strong><span><?= e($t('dashboard.executive_job_alerts', 'Alertas de jobs')) ?></span></div>
        </article>
    </div>

    <div class="plan-insights-grid">
        <article class="plan-insight-card">
            <span><?= e($t('dashboard.executive_queue', 'Fila de publicacao')) ?></span>
            <strong><?= (int) ($executive['publications_queued'] ?? 0) ?></strong>
        </article>
        <article class="plan-insight-card">
            <span><?= e($t('dashboard.executive_failures', 'Falhas de publicacao')) ?></span>
            <strong><?= (int) ($executive['publications_failed'] ?? 0) ?></strong>
        </article>
        <article class="plan-insight-card">
            <span><?= e($t('dashboard.executive_webhooks', 'Webhooks ativos')) ?></span>
            <strong><?= (int) ($executive['webhooks_active'] ?? 0) ?></strong>
        </article>
        <article class="plan-insight-card">
            <span><?= e($t('dashboard.executive_errors_24h', 'Erros (24h)')) ?></span>
            <strong><?= (int) ($executive['observability_errors_24h'] ?? 0) ?></strong>
        </article>
    </div>

    <?php $topCampaignsClicks = (array) ($executive['top_campaigns_clicks'] ?? []); ?>
    <?php if (!empty($topCampaignsClicks)): ?>
        <h4><?= e($t('dashboard.executive_top_campaigns', 'Campanhas com mais cliques')) ?></h4>
        <div class="metric-stack">
            <?php $maxClicks = max(1, (int) ($topCampaignsClicks[0]['total_clicks'] ?? 0)); ?>
            <?php foreach ($topCampaignsClicks as $row): ?>
                <?php $rowClicks = (int) ($row['total_clicks'] ?? 0); ?>
                <div class="metric-row">
                    <span><?= e((string) ($row['label'] ?? '-')) ?></span>
                    <strong><?= $rowClicks ?></strong>
                </div>
                <div class="metric-progress"><span style="width: <?= (float) round(($rowClicks / $maxClicks) * 100, 2) ?>%"></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel" id="dashboard-pipeline">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-diagram-project"></i> <?= e($t('dashboard.pipeline_title', 'Pipeline de execucao')) ?></h3>
        <span class="meta-text"><?= e($t('dashboard.pipeline_subtitle', 'Distribuicao dos proximos itens por status')) ?></span>
    </div>

    <div class="metric-stack">
        <div class="metric-row"><span><?= e($t('dashboard.status_planned', 'Planejado')) ?></span><strong><?= (int) ($statusCounter['planned'] ?? 0) ?></strong></div>
        <div class="metric-progress"><span style="width: <?= (float) round(((int) ($statusCounter['planned'] ?? 0) / $pipelineTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row"><span><?= e($t('dashboard.status_scheduled', 'Agendado')) ?></span><strong><?= (int) ($statusCounter['scheduled'] ?? 0) ?></strong></div>
        <div class="metric-progress"><span style="width: <?= (float) round(((int) ($statusCounter['scheduled'] ?? 0) / $pipelineTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row"><span><?= e($t('dashboard.status_published', 'Publicado')) ?></span><strong><?= (int) ($statusCounter['published'] ?? 0) ?></strong></div>
        <div class="metric-progress"><span style="width: <?= (float) round(((int) ($statusCounter['published'] ?? 0) / $pipelineTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row"><span><?= e($t('dashboard.status_skipped', 'Pulado')) ?></span><strong><?= (int) ($statusCounter['skipped'] ?? 0) ?></strong></div>
        <div class="metric-progress"><span style="width: <?= (float) round(((int) ($statusCounter['skipped'] ?? 0) / $pipelineTotal) * 100, 2) ?>%"></span></div>
    </div>
</section>

<section class="panel" id="dashboard-upcoming">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-clock"></i> <?= e($t('dashboard.upcoming_title', 'Proximas publicacoes')) ?></h3>
        <span class="meta-text"><?= count($upcomingItems) ?> <?= e($t('dashboard.upcoming_items_label', 'item(ns) futuros')) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e($t('dashboard.col_date', 'Data')) ?></th>
                    <th><?= e($t('dashboard.col_title', 'Titulo')) ?></th>
                    <th><?= e($t('dashboard.col_status', 'Status')) ?></th>
                    <th><?= e($t('dashboard.col_plan', 'Plano')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($upcomingItems)): ?>
                    <tr>
                        <td colspan="4"><?= e($t('dashboard.empty_upcoming', 'Nenhuma publicacao futura encontrada. Gere um plano editorial para iniciar.')) ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($upcomingItems as $item): ?>
                        <tr>
                            <td><?= e($item['planned_date']) ?></td>
                            <td><?= e($item['title']) ?></td>
                            <?php $itemStatus = strtolower((string) ($item['status'] ?? 'planned')); ?>
                            <td><span class="status-pill status-<?= e($itemStatus) ?>"><?= e($itemStatus) ?></span></td>
                            <td><?= e($item['plan_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>