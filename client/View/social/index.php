<?php
$frequencyOptions = [
    'diario' => 'Diario',
    'semanal' => 'Semanal',
    'quinzenal' => 'Quinzenal',
    'mensal' => 'Mensal',
];

$formatOptions = [
    'post' => 'Post',
    'carousel' => 'Carrossel',
];

$selectedPlatform = (string) ($standards_selected_platform ?? 'instagram');
$selectedFormat = (string) ($standards_selected_format ?? 'post');
$selectedPreset = is_array($standards_selected_preset ?? null) ? $standards_selected_preset : null;
$selectedSources = is_array($standards_selected_sources ?? null) ? $standards_selected_sources : [];
$sourceKeysCsv = $selectedPreset ? implode(',', (array) ($selectedPreset['source_keys'] ?? [])) : '';

$platformNameBySlug = [];
foreach ((array) $platforms as $slug => $platform) {
    $platformNameBySlug[(string) $slug] = (string) ($platform['name'] ?? $slug);
}

$publicationQueue = (array) ($publication_queue ?? []);
$publishPlanItems = (array) ($publish_plan_items ?? []);
$featureFlags = (array) ($feature_flags ?? []);
$publishHubEnabled = (bool) ($featureFlags['social.publish_hub'] ?? true);
?>

<?php if ($publishHubEnabled): ?>
<section class="panel">
    <div class="panel-head-inline">
        <h2>Central Social e Seguranca</h2>
        <span class="calendar-subtitle">Conecte cada rede individualmente e proteja seus acessos.</span>
    </div>

    <div class="social-grid">
        <?php foreach ($platforms as $slug => $platform): ?>
            <?php $conn = $connections[$slug] ?? null; ?>
            <?php $connected = $conn && in_array((string) ($conn['status'] ?? ''), ['connected', 'manual'], true); ?>
            <article class="social-card">
                <h3><?= e($platform['name']) ?></h3>
                <p class="social-meta">Tipo: <?= e(strtoupper((string) $platform['kind'])) ?></p>
                <p class="social-meta">Status: <strong><?= $connected ? 'Conectado' : 'Nao conectado' ?></strong></p>

                <?php if ($connected): ?>
                    <p class="social-meta">Conta: <?= e((string) ($conn['account_name'] ?? 'Conta vinculada')) ?></p>
                    <p class="social-meta">Atualizado em: <?= e((string) ($conn['updated_at'] ?? '-')) ?></p>
                <?php endif; ?>

                <div class="social-actions">
                    <?php if (($platform['kind'] ?? '') === 'oauth2'): ?>
                        <?php if (!empty($platform['client_id']) && !empty($platform['client_secret'])): ?>
                            <a class="btn-link" href="<?= e(route_url('social/connect/' . $slug)) ?>">
                                <?= $connected ? '<i class="fa-solid fa-rotate"></i> Reconectar OAuth' : '<i class="fa-solid fa-link"></i> Conectar OAuth' ?>
                            </a>
                        <?php else: ?>
                            <span class="hint">Configure `client_id` e `client_secret` em `config.php`.</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($connected): ?>
                        <form method="post" action="<?= e(route_url('social/disconnect/' . $slug)) ?>" onsubmit="return confirm('Desconectar esta plataforma?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link danger"><i class="fa-solid fa-link-slash"></i> Desconectar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head-inline">
        <h3>Hub de publicacao oficial</h3>
        <span class="calendar-subtitle">Fila multi-canal com disparo manual e processamento em lote.</span>
    </div>

    <form method="post" action="<?= e(route_url('social/queuePublication')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <label>Item do plano (opcional)
            <select name="plan_item_id">
                <option value="0">Publicacao avulsa (sem item)</option>
                <?php foreach ($publishPlanItems as $item): ?>
                    <option value="<?= (int) ($item['id'] ?? 0) ?>">
                        #<?= (int) ($item['id'] ?? 0) ?> - <?= e((string) ($item['title'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Titulo avulso
            <input type="text" name="title" placeholder="Usado apenas sem item do plano">
        </label>
        <label>Agendar para
            <input type="datetime-local" name="scheduled_at">
        </label>
        <label class="wide">Texto da publicacao
            <textarea name="message_text" rows="3" placeholder="Texto base para o post"></textarea>
        </label>
        <label class="wide">URL de midia (opcional)
            <input type="url" name="media_url" placeholder="https://cdn.seudominio.com/imagem.jpg">
        </label>
        <div class="wide channel-checks">
            <strong>Plataformas alvo</strong>
            <?php foreach ($platforms as $slug => $platform): ?>
                <label class="check">
                    <input type="checkbox" name="platforms[]" value="<?= e((string) $slug) ?>">
                    <?= e((string) ($platform['name'] ?? $slug)) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit"><i class="fa-solid fa-paper-plane"></i> Enfileirar publicacao</button>
    </form>

    <form method="post" action="<?= e(route_url('social/processQueue')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <label>Processar ate
            <input type="number" name="limit" min="1" max="50" value="10">
        </label>
        <button type="submit"><i class="fa-solid fa-play"></i> Processar fila agora</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Plataforma</th>
                <th>Conteudo</th>
                <th>Status</th>
                <th>Agendamento</th>
                <th>Publicado em</th>
                <th>Acao</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($publicationQueue)): ?>
                <tr>
                    <td colspan="7">Nenhuma publicacao registrada no hub.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($publicationQueue as $publication): ?>
                    <?php
                    $status = (string) ($publication['status'] ?? 'queued');
                    $allowManualPublish = in_array($status, ['queued', 'failed', 'manual_review'], true);
                    ?>
                    <tr>
                        <td>#<?= (int) ($publication['id'] ?? 0) ?></td>
                        <td><?= e($platformNameBySlug[(string) ($publication['platform_slug'] ?? '')] ?? (string) ($publication['platform_slug'] ?? '-')) ?></td>
                        <td>
                            <strong><?= e((string) ($publication['title'] ?? ($publication['plan_item_title'] ?? 'Sem titulo'))) ?></strong>
                            <?php if (!empty($publication['error_message'])): ?>
                                <small class="plan-item-description"><?= e((string) $publication['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($status) ?></td>
                        <td><?= e((string) ($publication['scheduled_at'] ?? '-')) ?></td>
                        <td><?= e((string) ($publication['published_at'] ?? '-')) ?></td>
                        <td>
                            <?php if ($allowManualPublish): ?>
                                <form method="post" action="<?= e(route_url('social/publishNow/' . (int) ($publication['id'] ?? 0))) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-link"><i class="fa-solid fa-bolt"></i> Publicar agora</button>
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

<section class="panel">
    <div class="panel-head-inline">
        <h3>Padroes de post e carrossel por rede</h3>
        <span class="calendar-subtitle">Matriz com medidas recomendadas para padronizar criacao de conteudo multi-canal.</span>
    </div>

    <form method="get" action="<?= e(route_url('social/index')) ?>" class="filters-grid">
        <label>Plataforma
            <select name="std_platform">
                <?php foreach ((array) ($standards_matrix ?? []) as $row): ?>
                    <option value="<?= e((string) $row['slug']) ?>" <?= ((string) $row['slug'] === $selectedPlatform) ? 'selected' : '' ?>>
                        <?= e((string) $row['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Formato
            <select name="std_format">
                <?php foreach ($formatOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($key === $selectedFormat) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit"><i class="fa-solid fa-arrows-rotate"></i> Atualizar padrao</button>
    </form>

    <?php if ($selectedPreset): ?>
        <div class="standards-focus-grid">
            <article class="standards-card">
                <h4>Preset recomendado: <?= e((string) ($selectedPreset['platform_name'] ?? $selectedPlatform)) ?> / <?= e($formatOptions[$selectedFormat] ?? ucfirst($selectedFormat)) ?></h4>
                <div class="standards-metrics">
                    <div>
                        <span>Canvas</span>
                        <strong><?= e((string) ($selectedPreset['recommended_canvas'] ?? '-')) ?></strong>
                    </div>
                    <div>
                        <span>Proporcao</span>
                        <strong><?= e((string) ($selectedPreset['recommended_ratio'] ?? '-')) ?></strong>
                    </div>
                    <div>
                        <span>Safe area</span>
                        <strong><?= e((string) ($selectedPreset['recommended_safe_area'] ?? '-')) ?></strong>
                    </div>
                </div>
                <p class="social-meta"><strong>Regra oficial:</strong> <?= e((string) ($selectedPreset['official_rule'] ?? '-')) ?></p>
                <p class="social-meta"><strong>Limites:</strong> <?= e((string) ($selectedPreset['official_limits'] ?? '-')) ?></p>
                <p class="social-meta"><strong>Observacoes:</strong> <?= e((string) ($selectedPreset['notes'] ?? '-')) ?></p>
                <?php if (!empty($selectedPreset['is_inference'])): ?>
                    <p class="hint">Parte deste preset usa inferencia tecnica quando a plataforma nao publica regra consolidada.</p>
                <?php endif; ?>
            </article>

            <article class="standards-card">
                <h4>Fontes consultadas</h4>
                <?php if (empty($selectedSources)): ?>
                    <p class="social-meta">Este preset usa padrao interno sem fonte publica obrigatoria.</p>
                <?php else: ?>
                    <ul class="standards-source-list">
                        <?php foreach ($selectedSources as $source): ?>
                            <li>
                                <a href="<?= e((string) ($source['url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= e((string) ($source['label'] ?? 'Fonte oficial')) ?>
                                </a>
                                <small>Validado em <?= e((string) ($source['checked_at'] ?? '-')) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>

        <h4>Salvar preset personalizado</h4>
        <form method="post" action="<?= e(route_url('social/saveFormatPreset')) ?>" class="filters-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="platform_slug" value="<?= e((string) ($selectedPreset['platform_slug'] ?? $selectedPlatform)) ?>">
            <input type="hidden" name="format_type" value="<?= e((string) ($selectedPreset['format_type'] ?? $selectedFormat)) ?>">
            <input type="hidden" name="source_keys" value="<?= e($sourceKeysCsv) ?>">

            <label>Nome do preset
                <input type="text" name="preset_name" value="<?= e((string) ($selectedPreset['platform_name'] ?? 'Rede') . ' ' . ($formatOptions[$selectedFormat] ?? ucfirst($selectedFormat)) . ' Padrao') ?>" required>
            </label>
            <label>Largura (px)
                <input type="number" name="width_px" min="1" max="8000" value="<?= e((string) ($selectedPreset['width_px'] ?? 1080)) ?>" required>
            </label>
            <label>Altura (px)
                <input type="number" name="height_px" min="1" max="8000" value="<?= e((string) ($selectedPreset['height_px'] ?? 1080)) ?>" required>
            </label>
            <label>Aspect ratio
                <input type="text" name="aspect_ratio" value="<?= e((string) ($selectedPreset['recommended_ratio'] ?? '1:1')) ?>" required>
            </label>
            <label>Safe area recomendada
                <input type="text" name="safe_area_text" value="<?= e((string) ($selectedPreset['recommended_safe_area'] ?? '')) ?>">
            </label>
            <label>Cor de referencia
                <input type="color" name="color_hex" value="#1F7A53">
            </label>
            <label class="wide">Notas internas
                <textarea name="notes" rows="3" placeholder="Observacoes de producao, estilo visual e regras internas."><?= e((string) ($selectedPreset['official_limits'] ?? '')) ?></textarea>
            </label>
            <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar preset personalizado</button>
        </form>
    <?php endif; ?>

    <h4>Matriz consolidada de medidas</h4>
    <div class="table-wrap">
        <table class="table standards-table">
            <thead>
            <tr>
                <th>Plataforma</th>
                <th>Post</th>
                <th>Carrossel</th>
                <th>Regra-chave</th>
                <th>Acesso rapido</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ((array) ($standards_matrix ?? []) as $row): ?>
                <?php
                $postUrl = route_url('social/index') . '?std_platform=' . rawurlencode((string) $row['slug']) . '&std_format=post';
                $carouselUrl = route_url('social/index') . '?std_platform=' . rawurlencode((string) $row['slug']) . '&std_format=carousel';
                ?>
                <tr>
                    <td>
                        <strong><?= e((string) $row['name']) ?></strong>
                        <?php if (!empty($row['has_inference'])): ?>
                            <span class="hint">* parcial por inferencia</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $row['post_canvas']) ?> (<?= e((string) $row['post_ratio']) ?>)</td>
                    <td>
                        <?= e((string) $row['carousel_canvas']) ?> (<?= e((string) $row['carousel_ratio']) ?>)
                        <?php if (empty($row['carousel_supported'])): ?>
                            <span class="hint">nao nativo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $row['key_rule']) ?></td>
                    <td class="standards-actions">
                        <a class="btn-link" href="<?= e($postUrl) ?>"><i class="fa-solid fa-image"></i> Post</a>
                        <a class="btn-link" href="<?= e($carouselUrl) ?>"><i class="fa-regular fa-images"></i> Carrossel</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3>Presets personalizados salvos</h3>
    <?php if (empty($saved_format_presets)): ?>
        <p>Voce ainda nao salvou presets personalizados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Preset</th>
                    <th>Rede</th>
                    <th>Formato</th>
                    <th>Dimensoes</th>
                    <th>Safe area</th>
                    <th>Cor</th>
                    <th>Atualizacao</th>
                    <th>Acao</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($saved_format_presets as $preset): ?>
                    <?php
                    $platformSlug = (string) ($preset['platform_slug'] ?? '');
                    $formatSlug = (string) ($preset['format_type'] ?? 'post');
                    $sourceLinks = json_decode((string) ($preset['source_links_json'] ?? '[]'), true);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($preset['preset_name'] ?? 'Preset')) ?></strong>
                            <?php if (!empty($sourceLinks) && is_array($sourceLinks)): ?>
                                <div class="social-meta"><?= e((string) count($sourceLinks)) ?> fonte(s) vinculada(s)</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($platformNameBySlug[$platformSlug] ?? $platformSlug) ?></td>
                        <td><?= e($formatOptions[$formatSlug] ?? ucfirst($formatSlug)) ?></td>
                        <td><?= e((string) ($preset['width_px'] ?? '-')) ?>x<?= e((string) ($preset['height_px'] ?? '-')) ?> (<?= e((string) ($preset['aspect_ratio'] ?? '-')) ?>)</td>
                        <td><?= e((string) ($preset['safe_area_text'] ?? '-')) ?></td>
                        <td>
                            <?php if (!empty($preset['color_hex'])): ?>
                                <span class="color-pill" style="background: <?= e((string) $preset['color_hex']) ?>;" title="<?= e((string) $preset['color_hex']) ?>"></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($preset['updated_at'] ?? '-')) ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('social/deleteFormatPreset/' . (int) ($preset['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir este preset personalizado?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-link danger"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3>Conexao manual por token (todas as plataformas)</h3>
    <form method="post" action="<?= e(route_url('social/saveManualConnection')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <label>Plataforma
            <select name="platform_slug" required>
                <?php foreach ($platforms as $slug => $platform): ?>
                    <option value="<?= e($slug) ?>"><?= e($platform['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nome da conta
            <input type="text" name="account_name" placeholder="Ex.: Marca Oficial">
        </label>
        <label>Token expira em
            <input type="datetime-local" name="token_expires_at">
        </label>
        <label class="wide">Access token
            <input type="text" name="access_token" required>
        </label>
        <label class="wide">Refresh token (opcional)
            <input type="text" name="refresh_token">
        </label>
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar conexao manual</button>
    </form>
</section>

<section class="panel">
    <h3>Criador de Conteudo Estrategico Multi-Rede</h3>
    <form method="post" action="<?= e(route_url('social/generateDraft')) ?>" class="filters-grid">
        <?= csrf_field() ?>
        <label>Tema central
            <input type="text" name="theme" placeholder="Ex.: Lancamento de servico premium" required>
        </label>
        <label>Objetivo
            <input type="text" name="objective" placeholder="Ex.: Conversao e captacao de leads" required>
        </label>
        <label>Pilar estrategico
            <input type="text" name="pillar" placeholder="Ex.: Seja o especialista" required>
        </label>
        <label>Tom da comunicacao
            <input type="text" name="tone" placeholder="Ex.: Direto, consultivo e humano" required>
        </label>
        <label>Publico alvo
            <input type="text" name="audience" placeholder="Ex.: Pequenas empresas B2B">
        </label>
        <label>Frequencia
            <select name="frequency">
                <?php foreach ($frequencyOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="wide">CTA principal
            <input type="text" name="cta" value="Comente sua opiniao e fale com nossa equipe">
        </label>
        <div class="wide channel-checks">
            <strong>Canais para gerar variacoes:</strong>
            <?php foreach ($platforms as $slug => $platform): ?>
                <label class="check"><input type="checkbox" name="channels[]" value="<?= e($slug) ?>" checked> <?= e($platform['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar conteudo estrategico</button>
    </form>
</section>

<section class="panel">
    <h3>Conteudos estrategicos gerados</h3>
    <?php if (empty($drafts)): ?>
        <p>Nenhum draft ainda. Gere seu primeiro conteudo multi-rede acima.</p>
    <?php else: ?>
        <div class="draft-grid">
            <?php foreach ($drafts as $draft): ?>
                <article class="draft-card">
                    <h4><?= e((string) ($draft['title'] ?? 'Plano estrategico')) ?></h4>
                    <p class="social-meta">Criado em <?= e((string) ($draft['created_at'] ?? '')) ?> | Frequencia: <?= e((string) ($draft['frequency'] ?? '-')) ?></p>
                    <p><strong>Base:</strong><br><?= nl2br(e((string) ($draft['base_text'] ?? ''))) ?></p>
                    <?php if (!empty($draft['hooks'])): ?>
                        <p><strong>Ganchos:</strong><br><?= nl2br(e(implode("\n", (array) $draft['hooks']))) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($draft['hashtags'])): ?>
                        <p><strong>Hashtags:</strong> <?= e(implode(' ', (array) $draft['hashtags'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($draft['channels'])): ?>
                        <p><strong>Canais:</strong> <?= e(implode(', ', (array) $draft['channels'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3>Monitoramento de seguranca de acesso</h3>
    <?php if (empty($security_events)): ?>
        <p>Sem eventos de seguranca para este usuario no momento.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Evento</th>
                    <th>Severidade</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($security_events as $event): ?>
                    <tr>
                        <td><?= e((string) ($event['created_at'] ?? '')) ?></td>
                        <td><?= e((string) ($event['event_type'] ?? '')) ?></td>
                        <td><?= e((string) ($event['severity'] ?? 'info')) ?></td>
                        <td><?= e((string) ($event['ip_address'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
