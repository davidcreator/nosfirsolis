<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-list-check"></i> Plans Hub</span>
        <h2><i class="fa-solid fa-layer-group"></i> Planejamento editorial</h2>
        <p>Monte planos por template anual ou por período, com fluxo rápido para execução e acompanhamento.</p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#templates-planos"><i class="fa-solid fa-wand-magic-sparkles"></i> Templates</a>
        <a class="btn" href="#geracao-planos"><i class="fa-solid fa-calendar-plus"></i> Gerar por período</a>
        <a class="btn" href="<?= e(route_url('calendar/index')) ?>"><i class="fa-solid fa-calendar-days"></i> Abrir calendário</a>
    </div>
</section>

<section class="panel" id="templates-planos">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-layer-group"></i> Templates anuais prontos para uso rápido</h2>
        <span class="meta-text"><?= count($templates) ?> template(s) disponível(is)</span>
    </div>
    <p class="calendar-subtitle">
        Modelos completos de janeiro a dezembro para B2C, B2B, direto/indireto, aquecimento de vendas, clientes, artistas, músicos, livros, infoprodutos e lançamentos de infoprodutos.
    </p>

    <form method="post" action="<?= e(route_url('plans/storeTemplate')) ?>" class="filters-grid">
        <?= csrf_field() ?>

        <label>Template estratégico
            <select name="template_slug" required>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= e($template['slug']) ?>">
                        <?= e($template['name']) ?> - <?= e($template['segment']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Ano
            <input type="number" name="template_year" value="<?= (int) date('Y') ?>" min="1970" max="2100" required>
        </label>

        <label>Frequência de execução
            <select name="template_frequency">
                <option value="diario">Diário</option>
                <option value="semanal" selected>Semanal</option>
                <option value="quinzenal">Quinzenal</option>
                <option value="mensal">Mensal</option>
            </select>
        </label>

        <button type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar plano completo por template</button>
    </form>

    <div class="template-grid">
        <?php foreach ($templates as $template): ?>
            <article class="template-card">
                <h3><?= e($template['name']) ?></h3>
                <p><strong>Segmento:</strong> <?= e($template['segment']) ?></p>
                <p><strong>Padrão:</strong> <?= e(strtoupper($template['default_frequency'])) ?></p>
                <p><?= e($template['description']) ?></p>
                <div class="table-wrap short">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Estratégia</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($template['monthly_strategy'] as $month => $strategy): ?>
                            <tr>
                                <td><?= (int) $month ?></td>
                                <td><?= e($strategy) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" id="geracao-planos">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-calendar-plus"></i> Geração de plano editorial por período</h2>
        <span class="meta-text">Fluxo manual e rápido</span>
    </div>

    <form method="post" action="<?= e(route_url('plans/store')) ?>" class="filters-grid">
        <?= csrf_field() ?>

        <label>Nome do plano
            <input type="text" name="name" placeholder="Ex.: Plano Comercial Q2" required>
        </label>

        <label>Data inicial
            <input type="date" name="start_date" required>
        </label>

        <label>Data final
            <input type="date" name="end_date" required>
        </label>

        <label>Campanha
            <select name="campaign_id">
                <option value="">Todas</option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int) $campaign['id'] ?>"><?= e($campaign['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Objetivo
            <select name="content_objective_id">
                <option value="">Todos</option>
                <?php foreach ($objectives as $objective): ?>
                    <option value="<?= (int) $objective['id'] ?>"><?= e($objective['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Canal
            <select name="channel_id">
                <option value="">Todos</option>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= (int) $channel['id'] ?>"><?= e($channel['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="wide">Observações gerais
            <input type="text" name="notes" placeholder="Observações estratégicas do período">
        </label>

        <button type="submit"><i class="fa-solid fa-calendar-plus"></i> Gerar plano por período</button>
    </form>

    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-list-check"></i> Planos gerados</h3>
        <span class="meta-text"><?= count($plans) ?> plano(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Período</th>
                    <th>Status</th>
                    <th>Itens</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td>#<?= (int) $plan['id'] ?></td>
                        <td><?= e($plan['name']) ?></td>
                        <td><?= e($plan['start_date']) ?> até <?= e($plan['end_date']) ?></td>
                        <td><span class="status-pill status-<?= e(strtolower((string) $plan['status'])) ?>"><?= e($plan['status']) ?></span></td>
                        <td><?= (int) $plan['total_items'] ?></td>
                        <td><a href="<?= e(route_url('plans/show/' . $plan['id'])) ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
