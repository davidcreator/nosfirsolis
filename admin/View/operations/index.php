<?php
$flags = (array) ($flags ?? []);
$webhooks = (array) ($webhooks ?? []);
$dispatchLogs = (array) ($dispatch_logs ?? []);
$monitors = (array) ($monitors ?? []);
$checkins = (array) ($checkins ?? []);
$jobAlerts = (array) ($job_alerts ?? []);
$observabilityEvents = (array) ($observability_events ?? []);
$opsFeatureMap = (array) ($ops_feature_map ?? []);

$webhooksEnabled = (bool) ($opsFeatureMap['automation.webhooks'] ?? true);
$jobsEnabled = (bool) ($opsFeatureMap['jobs.monitoring'] ?? true);
$observabilityEnabled = (bool) ($opsFeatureMap['observability.telemetry'] ?? true);
?>

<section class="panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-gears"></i> Ops Center</span>
        <h1>Operacoes e Integracoes</h1>
        <p>Governanca de feature flags, webhooks de automacao, monitoramento de jobs e observabilidade.</p>
    </div>
    <div class="hero-actions">
        <form method="post" action="<?= e(route_url('operations/runMaintenance')) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><i class="fa-solid fa-wrench"></i> Rodar manutencao</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Feature flags</h2>
        <span class="meta-text">Controle de liberacao por area, permissao e hierarquia</span>
    </div>

    <form method="post" action="<?= e(route_url('operations/saveFeatureFlag')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>Chave
            <input type="text" name="flag_key" placeholder="tracking.campaign_links" required>
        </label>
        <label>Titulo
            <input type="text" name="label" placeholder="Rastreamento de campanhas" required>
        </label>
        <label>Area
            <select name="target_area">
                <option value="all">all</option>
                <option value="admin">admin</option>
                <option value="client">client</option>
            </select>
        </label>
        <label>Estrategia
            <select name="rollout_strategy">
                <option value="all">all</option>
                <option value="admins_only">admins_only</option>
                <option value="clients_only">clients_only</option>
                <option value="min_hierarchy">min_hierarchy</option>
                <option value="permission">permission</option>
            </select>
        </label>
        <label>Nivel max hierarquia (min_hierarchy)
            <input type="number" name="min_hierarchy_level" min="1" max="999" placeholder="50">
        </label>
        <label>Permissao requerida (permission)
            <input type="text" name="required_permission" placeholder="admin.operations">
        </label>
        <label class="wide">Descricao
            <input type="text" name="description" placeholder="Descricao funcional da flag">
        </label>
        <label class="check">
            <input type="checkbox" name="enabled" value="1" checked>
            Flag ativa
        </label>
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar flag</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Flag</th>
                <th>Area</th>
                <th>Estrategia</th>
                <th>Status</th>
                <th>Acao</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($flags)): ?>
                <tr>
                    <td colspan="5">Nenhuma feature flag cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($flags as $flag): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($flag['flag_key'] ?? '')) ?></strong>
                            <small class="plan-item-description"><?= e((string) ($flag['label'] ?? '')) ?></small>
                        </td>
                        <td><?= e((string) ($flag['target_area'] ?? 'all')) ?></td>
                        <td><?= e((string) ($flag['rollout_strategy'] ?? 'all')) ?></td>
                        <td><?= (int) ($flag['enabled'] ?? 0) === 1 ? 'Ativa' : 'Inativa' ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('operations/deleteFeatureFlag/' . (int) ($flag['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir esta feature flag?')">
                                <?= csrf_field() ?>
                                <button class="btn-link danger" type="submit"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($webhooksEnabled): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Automacao por webhooks</h2>
        <span class="meta-text">Disparo de eventos para n8n, integrações internas e terceiros</span>
    </div>

    <form method="post" action="<?= e(route_url('operations/saveWebhook')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>Nome
            <input type="text" name="name" placeholder="Webhook n8n - status plano" required>
        </label>
        <label>Evento
            <input type="text" name="event_key" placeholder="plan.item_status_changed" required>
        </label>
        <label class="wide">Endpoint
            <input type="url" name="endpoint_url" placeholder="https://seu-workflow/webhook" required>
        </label>
        <label>Metodo
            <select name="http_method">
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="PATCH">PATCH</option>
            </select>
        </label>
        <label>Auth type
            <select name="auth_type">
                <option value="none">none</option>
                <option value="bearer">bearer</option>
                <option value="basic">basic</option>
                <option value="header">header</option>
            </select>
        </label>
        <label>Auth user (basic)
            <input type="text" name="auth_username">
        </label>
        <label>Auth secret/token
            <input type="text" name="auth_secret">
        </label>
        <label>Header name (header auth)
            <input type="text" name="header_name" placeholder="X-Api-Key">
        </label>
        <label>Header value
            <input type="text" name="header_value">
        </label>
        <label>Signing secret (HMAC)
            <input type="text" name="signing_secret">
        </label>
        <label>Timeout (s)
            <input type="number" name="timeout_seconds" min="2" max="30" value="8">
        </label>
        <label>Retries
            <input type="number" name="retries" min="0" max="5" value="1">
        </label>
        <label class="check">
            <input type="checkbox" name="enabled" value="1" checked>
            Webhook ativo
        </label>
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar webhook</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Nome</th>
                <th>Evento</th>
                <th>Endpoint</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($webhooks)): ?>
                <tr>
                    <td colspan="5">Nenhum webhook configurado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($webhooks as $webhook): ?>
                    <tr>
                        <td><?= e((string) ($webhook['name'] ?? '')) ?></td>
                        <td><?= e((string) ($webhook['event_key'] ?? '')) ?></td>
                        <td><?= e((string) ($webhook['endpoint_url'] ?? '')) ?></td>
                        <td><?= (int) ($webhook['enabled'] ?? 0) === 1 ? 'Ativo' : 'Inativo' ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('operations/testWebhook/' . (int) ($webhook['id'] ?? 0))) ?>">
                                <?= csrf_field() ?>
                                <button class="btn-link" type="submit"><i class="fa-solid fa-vial"></i> Testar</button>
                            </form>
                            <form method="post" action="<?= e(route_url('operations/deleteWebhook/' . (int) ($webhook['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir webhook?')">
                                <?= csrf_field() ?>
                                <button class="btn-link danger" type="submit"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Historico de dispatch</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Data</th>
                <th>Webhook</th>
                <th>Evento</th>
                <th>Status</th>
                <th>HTTP</th>
                <th>Duracao</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($dispatchLogs)): ?>
                <tr>
                    <td colspan="6">Sem disparos registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dispatchLogs as $log): ?>
                    <tr>
                        <td><?= e((string) ($log['attempted_at'] ?? '')) ?></td>
                        <td><?= e((string) ($log['webhook_name'] ?? '')) ?></td>
                        <td><?= e((string) ($log['event_key'] ?? '')) ?></td>
                        <td><?= e((string) ($log['status'] ?? 'failed')) ?></td>
                        <td><?= e((string) ($log['http_status'] ?? '-')) ?></td>
                        <td><?= e((string) ($log['duration_ms'] ?? '-')) ?> ms</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($jobsEnabled): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Monitoramento de jobs</h2>
        <span class="meta-text">Check-ins, runtime e alertas de stale/failure</span>
    </div>

    <form method="post" action="<?= e(route_url('operations/saveMonitor')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>Job key
            <input type="text" name="job_key" placeholder="social.publisher_queue" required>
        </label>
        <label>Nome
            <input type="text" name="name" placeholder="Fila de publicacao social" required>
        </label>
        <label>Intervalo esperado (min)
            <input type="number" name="expected_interval_minutes" min="1" max="10080" value="60">
        </label>
        <label>Tempo maximo (s)
            <input type="number" name="max_runtime_seconds" min="1" max="86400" value="300">
        </label>
        <label class="wide">Descricao
            <input type="text" name="description" placeholder="Descricao operacional do monitor">
        </label>
        <label class="check">
            <input type="checkbox" name="enabled" value="1" checked>
            Monitor ativo
        </label>
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar monitor</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Job</th>
                <th>Status</th>
                <th>Ultimo check-in</th>
                <th>Ultima duracao</th>
                <th>Acao</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($monitors)): ?>
                <tr>
                    <td colspan="5">Nenhum monitor cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($monitors as $monitor): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($monitor['job_key'] ?? '')) ?></strong>
                            <small class="plan-item-description"><?= e((string) ($monitor['name'] ?? '')) ?></small>
                        </td>
                        <td><?= e((string) ($monitor['last_status'] ?? 'stale')) ?></td>
                        <td><?= e((string) ($monitor['last_checkin_at'] ?? '-')) ?></td>
                        <td><?= e((string) ($monitor['last_duration_ms'] ?? '-')) ?> ms</td>
                        <td>
                            <form method="post" action="<?= e(route_url('operations/deleteMonitor/' . (int) ($monitor['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir monitor?')">
                                <?= csrf_field() ?>
                                <button class="btn-link danger" type="submit"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Alertas ativos</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Monitor</th>
                <th>Tipo</th>
                <th>Mensagem</th>
                <th>Criado em</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($jobAlerts)): ?>
                <tr>
                    <td colspan="4">Sem alertas ativos.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($jobAlerts as $alert): ?>
                    <tr>
                        <td><?= e((string) ($alert['monitor_name'] ?? $alert['job_key'] ?? '')) ?></td>
                        <td><?= e((string) ($alert['alert_type'] ?? '')) ?></td>
                        <td><?= e((string) ($alert['message'] ?? '')) ?></td>
                        <td><?= e((string) ($alert['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($observabilityEnabled): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Observabilidade</h2>
        <span class="meta-text">Eventos estruturados de integracao, seguranca e operacao</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Data</th>
                <th>Nivel</th>
                <th>Categoria</th>
                <th>Mensagem</th>
                <th>Area</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($observabilityEvents)): ?>
                <tr>
                    <td colspan="5">Sem eventos de observabilidade registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($observabilityEvents as $event): ?>
                    <tr>
                        <td><?= e((string) ($event['created_at'] ?? '')) ?></td>
                        <td><?= e((string) ($event['level'] ?? 'info')) ?></td>
                        <td><?= e((string) ($event['category'] ?? '')) ?></td>
                        <td><?= e((string) ($event['message'] ?? '')) ?></td>
                        <td><?= e((string) ($event['area'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Ultimos check-ins de jobs</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Data</th>
                <th>Job</th>
                <th>Status</th>
                <th>Duracao</th>
                <th>Erro</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($checkins)): ?>
                <tr>
                    <td colspan="5">Sem check-ins no periodo.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($checkins as $checkin): ?>
                    <tr>
                        <td><?= e((string) ($checkin['checked_at'] ?? '')) ?></td>
                        <td><?= e((string) ($checkin['job_key'] ?? $checkin['monitor_name'] ?? '')) ?></td>
                        <td><?= e((string) ($checkin['status'] ?? 'ok')) ?></td>
                        <td><?= e((string) ($checkin['duration_ms'] ?? '-')) ?> ms</td>
                        <td><?= e((string) ($checkin['error_message'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
