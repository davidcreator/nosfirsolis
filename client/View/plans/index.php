<section class="panel">
    <h2>Templates anuais prontos para uso rápido</h2>
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

<section class="panel">
    <h2>Geração de plano editorial por período</h2>

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

    <h3>Planos gerados</h3>
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
                        <td><?= e($plan['status']) ?></td>
                        <td><?= (int) $plan['total_items'] ?></td>
                        <td><a href="<?= e(route_url('plans/show/' . $plan['id'])) ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
