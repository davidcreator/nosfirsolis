<?php
$links = (array) ($links ?? []);
$summary = (array) ($summary ?? []);
$campaigns = (array) ($campaigns ?? []);
$planItems = (array) ($plan_items ?? []);

$totalLinks = (int) ($summary['total_links'] ?? 0);
$totalClicks = (int) ($summary['total_clicks'] ?? 0);
$topCampaigns = (array) ($summary['top_campaigns'] ?? []);
$topChannels = (array) ($summary['top_channels'] ?? []);
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-link"></i> Tracking Hub</span>
        <h2><i class="fa-solid fa-chart-line"></i> Rastreamento de campanhas</h2>
        <p>Crie URLs UTM/MTM, gere short links e acompanhe cliques por campanha, item e canal.</p>
    </div>
</section>

<section class="panel">
    <div class="stats-grid kpi-grid">
        <article class="kpi-card accent-blue">
            <span class="kpi-icon"><i class="fa-solid fa-link"></i></span>
            <div><strong><?= (int) $totalLinks ?></strong><span>Links rastreaveis</span></div>
        </article>
        <article class="kpi-card accent-green">
            <span class="kpi-icon"><i class="fa-solid fa-arrow-pointer"></i></span>
            <div><strong><?= (int) $totalClicks ?></strong><span>Total de cliques</span></div>
        </article>
    </div>
</section>

<section class="panel">
    <h3><i class="fa-solid fa-link"></i> Novo link rastreável</h3>
    <form method="post" action="<?= e(route_url('tracking/store')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <label class="wide">URL de destino
            <input type="url" name="destination_url" placeholder="https://seusite.com/pagina" required>
        </label>
        <label>Campanha
            <select name="campaign_id">
                <option value="">Sem campanha</option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int) ($campaign['id'] ?? 0) ?>"><?= e((string) ($campaign['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Item do plano
            <select name="plan_item_id">
                <option value="">Sem item</option>
                <?php foreach ($planItems as $item): ?>
                    <option value="<?= (int) ($item['id'] ?? 0) ?>">
                        #<?= (int) ($item['id'] ?? 0) ?> - <?= e((string) ($item['title'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Canal
            <input type="text" name="channel_slug" placeholder="instagram">
        </label>
        <label>utm_source
            <input type="text" name="utm_source" placeholder="instagram">
        </label>
        <label>utm_medium
            <input type="text" name="utm_medium" placeholder="social">
        </label>
        <label>utm_campaign
            <input type="text" name="utm_campaign" placeholder="black_friday_2026">
        </label>
        <label>utm_content
            <input type="text" name="utm_content" placeholder="carrossel_1">
        </label>
        <label>utm_term
            <input type="text" name="utm_term" placeholder="estrategia-social">
        </label>
        <label>mtm_campaign
            <input type="text" name="mtm_campaign" placeholder="newsletter_abril">
        </label>
        <label>mtm_keyword
            <input type="text" name="mtm_keyword" placeholder="segmento_b2b">
        </label>
        <label class="wide">Notas
            <input type="text" name="notes" placeholder="Observacoes internas da campanha">
        </label>
        <button type="submit"><i class="fa-solid fa-plus"></i> Criar link rastreavel</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-trophy"></i> Top campanhas e canais</h3>
    </div>
    <div class="plan-insights-grid">
        <article class="plan-insight-card">
            <span>Campanhas com mais cliques</span>
            <strong><?= !empty($topCampaigns) ? e((string) ($topCampaigns[0]['label'] ?? '-')) : '-' ?></strong>
            <small><?= !empty($topCampaigns) ? (int) ($topCampaigns[0]['total_clicks'] ?? 0) . ' clique(s)' : '' ?></small>
        </article>
        <article class="plan-insight-card">
            <span>Canal com mais cliques</span>
            <strong><?= !empty($topChannels) ? e((string) ($topChannels[0]['label'] ?? '-')) : '-' ?></strong>
            <small><?= !empty($topChannels) ? (int) ($topChannels[0]['total_clicks'] ?? 0) . ' clique(s)' : '' ?></small>
        </article>
    </div>
</section>

<section class="panel">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-list"></i> Links criados</h3>
        <span class="meta-text"><?= count($links) ?> registro(s)</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Campanha</th>
                <th>Canal</th>
                <th>Link curto</th>
                <th>Tracking URL</th>
                <th>Cliques</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($links)): ?>
                <tr>
                    <td colspan="7">Nenhum link rastreavel criado ainda.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($links as $link): ?>
                    <tr>
                        <td><?= e((string) ($link['campaign_name'] ?? 'Sem campanha')) ?></td>
                        <td><?= e((string) ($link['channel_slug'] ?? '-')) ?></td>
                        <td>
                            <a href="<?= e((string) ($link['short_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e((string) ($link['short_url'] ?? '')) ?>
                            </a>
                            <?php if (!empty($link['external_short_url'])): ?>
                                <small class="plan-item-description">Externo: <?= e((string) $link['external_short_url']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e((string) ($link['tracking_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e((string) ($link['tracking_url'] ?? '')) ?>
                            </a>
                        </td>
                        <td><?= (int) ($link['clicks'] ?? 0) ?></td>
                        <td><?= e((string) ($link['status'] ?? 'active')) ?></td>
                        <td>
                            <?php if ((string) ($link['status'] ?? 'active') !== 'archived'): ?>
                                <form method="post" action="<?= e(route_url('tracking/archive/' . (int) ($link['id'] ?? 0))) ?>" onsubmit="return confirm('Arquivar este link?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-link danger"><i class="fa-regular fa-folder"></i> Arquivar</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
