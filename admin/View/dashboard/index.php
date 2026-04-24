<?php
$users = (int) ($summary['users'] ?? 0);
$holidays = (int) ($summary['holidays'] ?? 0);
$commemoratives = (int) ($summary['commemoratives'] ?? 0);
$suggestions = (int) ($summary['suggestions'] ?? 0);
$campaigns = (int) ($summary['campaigns'] ?? 0);
$platforms = (int) ($summary['platforms'] ?? 0);

$metrics = [
    ['label' => $t('dashboard.metric_users', 'Usuários'), 'value' => $users, 'icon' => 'fa-solid fa-users', 'accent' => 'blue'],
    ['label' => $t('dashboard.metric_holidays', 'Feriados'), 'value' => $holidays, 'icon' => 'fa-solid fa-calendar-day', 'accent' => 'red'],
    ['label' => $t('dashboard.metric_commemoratives', 'Comemorativas'), 'value' => $commemoratives, 'icon' => 'fa-solid fa-star', 'accent' => 'amber'],
    ['label' => $t('dashboard.metric_suggestions', 'Sugestões'), 'value' => $suggestions, 'icon' => 'fa-solid fa-lightbulb', 'accent' => 'green'],
    ['label' => $t('dashboard.metric_campaigns', 'Campanhas'), 'value' => $campaigns, 'icon' => 'fa-solid fa-bullhorn', 'accent' => 'purple'],
    ['label' => $t('dashboard.metric_platforms', 'Plataformas'), 'value' => $platforms, 'icon' => 'fa-solid fa-share-nodes', 'accent' => 'cyan'],
];

$baseTotal = max(1, $holidays + $commemoratives + $suggestions + $campaigns);
$formatCounter = [];
foreach ($recent_suggestions as $suggestionItem) {
    $format = trim((string) ($suggestionItem['format_type'] ?? $t('dashboard.undefined_format', 'Não definido')));
    $key = $format !== '' ? $format : $t('dashboard.undefined_format', 'Não definido');
    $formatCounter[$key] = (int) ($formatCounter[$key] ?? 0) + 1;
}
arsort($formatCounter);
$topFormats = array_slice($formatCounter, 0, 4, true);
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-gauge-high"></i> <?= e($app_name ?? 'Solis') ?></span>
        <h1><?= e($t('dashboard.hero_title', '{app} - Visão Administrativa', ['app' => ($app_name ?? 'Solis')])) ?></h1>
        <p><?= e($t('dashboard.hero_description', 'Monitore a operação editorial anual, valide consistência de dados estratégicos e acelere a curadoria de conteúdos.')) ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="<?= e(route_url('suggestions/create')) ?>"><i class="fa-solid fa-plus"></i> <?= e($t('dashboard.button_new_suggestion', 'Nova sugestão')) ?></a>
        <a class="btn" href="<?= e(route_url('holidays/create')) ?>"><i class="fa-solid fa-calendar-plus"></i> <?= e($t('dashboard.button_new_holiday', 'Novo feriado')) ?></a>
        <a class="btn" href="<?= e(route_url('campaigns/create')) ?>"><i class="fa-solid fa-bullhorn"></i> <?= e($t('dashboard.button_new_campaign', 'Nova campanha')) ?></a>
    </div>
</section>

<section class="panel">
    <div class="cards-grid kpi-grid">
        <?php foreach ($metrics as $metric): ?>
            <article class="kpi-card accent-<?= e($metric['accent']) ?>">
                <span class="kpi-icon"><i class="<?= e($metric['icon']) ?>"></i></span>
                <div>
                    <strong><?= (int) $metric['value'] ?></strong>
                    <span><?= e($metric['label']) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><?= e($t('dashboard.section_distribution_title', 'Distribuição da base estratégica')) ?></h2>
        <span class="meta-text"><?= e($t('dashboard.section_distribution_subtitle', 'Participação relativa por tipo de conteúdo cadastrado')) ?></span>
    </div>
    <div class="metric-stack">
        <div class="metric-row">
            <span><?= e($t('dashboard.metric_holidays', 'Feriados')) ?></span>
            <strong><?= (int) round(($holidays / $baseTotal) * 100) ?>%</strong>
        </div>
        <div class="metric-progress"><span style="width: <?= (float) round(($holidays / $baseTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row">
            <span><?= e($t('dashboard.metric_commemoratives', 'Comemorativas')) ?></span>
            <strong><?= (int) round(($commemoratives / $baseTotal) * 100) ?>%</strong>
        </div>
        <div class="metric-progress"><span style="width: <?= (float) round(($commemoratives / $baseTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row">
            <span><?= e($t('dashboard.metric_suggestions_strategic', 'Sugestões estratégicas')) ?></span>
            <strong><?= (int) round(($suggestions / $baseTotal) * 100) ?>%</strong>
        </div>
        <div class="metric-progress"><span style="width: <?= (float) round(($suggestions / $baseTotal) * 100, 2) ?>%"></span></div>

        <div class="metric-row">
            <span><?= e($t('dashboard.metric_campaigns', 'Campanhas')) ?></span>
            <strong><?= (int) round(($campaigns / $baseTotal) * 100) ?>%</strong>
        </div>
        <div class="metric-progress"><span style="width: <?= (float) round(($campaigns / $baseTotal) * 100, 2) ?>%"></span></div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><?= e($t('dashboard.section_recent_suggestions_title', 'Sugestões recentes')) ?></h2>
        <span class="meta-text"><?= e($t('dashboard.highlighted_items', '{count} item(ns) em destaque', ['count' => (int) count($recent_suggestions)])) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e($t('dashboard.col_date', 'Data')) ?></th>
                    <th><?= e($t('dashboard.col_title', 'Título')) ?></th>
                    <th><?= e($t('dashboard.col_format', 'Formato')) ?></th>
                    <th><?= e($t('dashboard.col_pillar', 'Pilar')) ?></th>
                    <th><?= e($t('dashboard.col_objective', 'Objetivo')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_suggestions)): ?>
                    <tr>
                        <td colspan="5"><?= e($t('dashboard.empty_recent_suggestions', 'Sem sugestões recentes cadastradas.')) ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_suggestions as $item): ?>
                        <tr>
                            <td><?= e($item['suggestion_date']) ?></td>
                            <td><?= e($item['title']) ?></td>
                            <td><span class="table-chip"><?= e($item['format_type']) ?></span></td>
                            <td><?= e($item['pillar_name'] ?? '-') ?></td>
                            <td><?= e($item['objective_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($topFormats)): ?>
    <section class="panel">
        <div class="panel-header">
            <h2><?= e($t('dashboard.section_top_formats_title', 'Formatos mais recorrentes')) ?></h2>
            <span class="meta-text"><?= e($t('dashboard.section_top_formats_subtitle', 'Baseado nas sugestões recentes')) ?></span>
        </div>
        <div class="metric-stack">
            <?php $maxFormats = max(1, (int) max($topFormats)); ?>
            <?php foreach ($topFormats as $format => $count): ?>
                <div class="metric-row">
                    <span><?= e((string) $format) ?></span>
                    <strong><?= (int) $count ?></strong>
                </div>
                <div class="metric-progress"><span style="width: <?= (float) round(((int) $count / $maxFormats) * 100, 2) ?>%"></span></div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
