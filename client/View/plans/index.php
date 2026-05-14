<?php
$aiManagers = (array) ($ai_managers ?? []);
$aiResolvedManager = (array) ($ai_resolved_manager ?? []);
$aiManagerSource = (string) ($ai_manager_source ?? 'default');
$aiSourceLabel = $aiManagerSource === 'user'
    ? 'Configuracao personalizada pelo Admin'
    : 'Configuracao padrao definida pelo Admin';
$today = date('Y-m-d');
$defaultAiEnd = date('Y-m-d', strtotime('+60 days'));
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-list-check"></i> Plans Hub</span>
        <h2><i class="fa-solid fa-layer-group"></i> Planejamento editorial</h2>
        <p>Monte planos por template anual, fluxo manual por periodo ou com a central de IA para planos e campanhas.</p>
    </div>
    <div class="hero-actions">
        <a class="btn" href="#ia-planos-campanhas"><i class="fa-solid fa-robot"></i> Central IA</a>
        <a class="btn" href="#templates-planos"><i class="fa-solid fa-wand-magic-sparkles"></i> Templates</a>
        <a class="btn" href="#geracao-planos"><i class="fa-solid fa-calendar-plus"></i> Gerar por periodo</a>
        <a class="btn" href="<?= e(route_url('calendar/index')) ?>"><i class="fa-solid fa-calendar-days"></i> Abrir calendario</a>
    </div>
</section>

<section class="panel" id="ia-planos-campanhas">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-robot"></i> Central IA de planos e campanhas</h2>
        <span class="meta-text">Geracao automatica com governanca de IA</span>
    </div>
    <p class="calendar-subtitle">
        IA ativa para sua conta: <strong><?= e((string) ($aiResolvedManager['name'] ?? 'Strategist')) ?></strong>.
        <span class="meta-text">(<?= e($aiSourceLabel) ?>)</span>
    </p>

    <?php if (!empty($aiManagers)): ?>
        <div class="template-grid">
            <?php foreach ($aiManagers as $manager): ?>
                <?php $isCurrent = (string) ($manager['id'] ?? '') === (string) ($aiResolvedManager['id'] ?? ''); ?>
                <article class="template-card<?= $isCurrent ? ' is-current' : '' ?>">
                    <h3><?= e((string) ($manager['name'] ?? 'Strategist')) ?></h3>
                    <p><?= e((string) ($manager['description'] ?? 'Modelo de gestao editorial.')) ?></p>
                    <?php if ($isCurrent): ?>
                        <span class="table-chip"><i class="fa-solid fa-circle-check"></i> Em uso nesta conta</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(route_url('plans/storeAi')) ?>" class="filters-grid">
        <?= csrf_field() ?>

        <label>Tema central
            <input type="text" name="ai_theme" placeholder="Ex.: Escala de vendas consultivas" required>
        </label>

        <label>Objetivo (texto livre)
            <input type="text" name="ai_objective" placeholder="Ex.: gerar demanda qualificada">
        </label>

        <label>Objetivo catalogado
            <select name="ai_objective_id">
                <option value="0">Selecionar depois</option>
                <?php foreach ($objectives as $objective): ?>
                    <option value="<?= (int) $objective['id'] ?>"><?= e($objective['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Publico-alvo
            <input type="text" name="ai_audience" placeholder="Ex.: donos de pequenas empresas" required>
        </label>

        <label>Tom editorial
            <input type="text" name="ai_tone" placeholder="Ex.: consultivo e objetivo" required>
        </label>

        <label>Frequencia
            <select name="ai_frequency">
                <option value="diario">Diario</option>
                <option value="semanal" selected>Semanal</option>
                <option value="quinzenal">Quinzenal</option>
                <option value="mensal">Mensal</option>
            </select>
        </label>

        <label>Inicio do planejamento
            <input type="date" name="ai_start_date" value="<?= e($today) ?>" required>
        </label>

        <label>Fim do planejamento
            <input type="date" name="ai_end_date" value="<?= e($defaultAiEnd) ?>" required>
        </label>

        <label>Foco da campanha
            <input type="text" name="ai_campaign_focus" placeholder="Ex.: promocao de novo servico">
        </label>

        <label>Estrategia de campanha
            <select name="ai_campaign_mode">
                <option value="new" selected>Criar nova campanha automaticamente</option>
                <option value="existing">Vincular em campanha existente</option>
                <option value="none">Gerar plano sem campanha</option>
            </select>
        </label>

        <label>Campanha existente
            <select name="ai_campaign_id">
                <option value="0">Nao vincular</option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int) $campaign['id'] ?>"><?= e($campaign['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>IA gestora
            <select name="ai_manager_id">
                <option value="">Automatico (configuracao do Admin)</option>
                <?php foreach ($aiManagers as $manager): ?>
                    <option value="<?= e((string) ($manager['id'] ?? '')) ?>">
                        <?= e((string) ($manager['name'] ?? 'Strategist')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="wide">
            <strong>Canais prioritarios (opcional)</strong>
            <p class="meta-text">Se nenhum canal for marcado, a IA escolhe os canais mais indicados para a estrategia.</p>
        </div>

        <?php foreach ($channels as $channel): ?>
            <?php
            $channelSlug = trim((string) ($channel['slug'] ?? ''));
            if ($channelSlug === '') {
                continue;
            }
            ?>
            <label class="check">
                <input type="checkbox" name="channels[]" value="<?= e($channelSlug) ?>">
                <?= e($channel['name']) ?>
            </label>
        <?php endforeach; ?>

        <button type="submit"><i class="fa-solid fa-robot"></i> Gerar plano e campanha com IA</button>
    </form>
</section>

<section class="panel" id="templates-planos">
    <div class="panel-head-inline">
        <h2><i class="fa-solid fa-layer-group"></i> Templates anuais prontos para uso rapido</h2>
        <span class="meta-text"><?= count($templates) ?> template(s) disponivel(is)</span>
    </div>
    <p class="calendar-subtitle">
        Modelos completos de janeiro a dezembro para B2C, B2B, direto/indireto, aquecimento de vendas, clientes, artistas, musicos, livros, infoprodutos e lancamentos de infoprodutos.
    </p>

    <form method="post" action="<?= e(route_url('plans/storeTemplate')) ?>" class="filters-grid">
        <?= csrf_field() ?>

        <label>Template estrategico
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

        <label>Frequencia de execucao
            <select name="template_frequency">
                <option value="diario">Diario</option>
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
                <p><strong>Padrao:</strong> <?= e(strtoupper($template['default_frequency'])) ?></p>
                <p><?= e($template['description']) ?></p>
                <div class="table-wrap short">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Estrategia</th>
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
        <h2><i class="fa-solid fa-calendar-plus"></i> Geracao de plano editorial por periodo</h2>
        <span class="meta-text">Fluxo manual e rapido</span>
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

        <label class="wide">Observacoes gerais
            <input type="text" name="notes" placeholder="Observacoes estrategicas do periodo">
        </label>

        <button type="submit"><i class="fa-solid fa-calendar-plus"></i> Gerar plano por periodo</button>
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
                    <th>Periodo</th>
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
                        <td><?= e($plan['start_date']) ?> ate <?= e($plan['end_date']) ?></td>
                        <td><span class="status-pill status-<?= e(strtolower((string) $plan['status'])) ?>"><?= e($plan['status']) ?></span></td>
                        <td><?= (int) $plan['total_items'] ?></td>
                        <td><a href="<?= e(route_url('plans/show/' . $plan['id'])) ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
