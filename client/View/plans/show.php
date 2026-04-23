<?php
$planName = (string) ($plan['name'] ?? ('Plano #' . (int) $plan_id));
$planPeriod = (string) ($plan['start_date'] ?? '') . ' ate ' . (string) ($plan['end_date'] ?? '');
$planStatus = (string) ($plan['status'] ?? 'draft');
$campaignName = trim((string) ($plan['campaign_name'] ?? ''));

$statusFilter = (string) ($status_filter ?? 'all');
$searchQuery = (string) ($search_query ?? '');
$showQuery = (string) ($show_query ?? '');
$baseShowUrl = route_url('plans/show/' . (int) $plan_id);
$csvUrl = route_url('plans/exportCsv/' . (int) $plan_id . ($showQuery !== '' ? '?' . $showQuery : ''));

$statusLabels = [
    'all' => 'Todos',
    'planned' => 'Planejado',
    'scheduled' => 'Agendado',
    'published' => 'Publicado',
    'skipped' => 'Pulado',
];
$itemStatusOptions = array_filter(
    $statusLabels,
    static fn (string $key): bool => $key !== 'all',
    ARRAY_FILTER_USE_KEY
);

$statusBreakdown = (array) ($status_breakdown ?? []);
$insights = (array) ($insights ?? []);
$totalItems = (int) ($insights['total_items'] ?? count($items));
$overdueItems = (int) ($insights['overdue_items'] ?? 0);
$nextPendingDate = (string) ($insights['next_pending_date'] ?? '');
$completionRate = (float) ($insights['completion_rate'] ?? 0);
$publicationRate = (float) ($insights['publication_rate'] ?? 0);
$statusBarTotal = max(1, array_sum($statusBreakdown));
?>

<section class="panel">
    <div class="panel-head-inline">
        <div>
            <h2><?= e($planName) ?></h2>
            <p class="calendar-subtitle">
                Periodo: <?= e($planPeriod) ?> | Status: <strong><?= e($planStatus) ?></strong>
                <?php if ($campaignName !== ''): ?> | Campanha: <strong><?= e($campaignName) ?></strong><?php endif; ?>
            </p>
        </div>

        <div class="inline-links">
            <a href="<?= e(route_url('plans/index')) ?>">Voltar</a>
            <a href="<?= e($csvUrl) ?>"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
            <a href="#" onclick="window.print(); return false;"><i class="fa-solid fa-print"></i> Imprimir</a>
        </div>
    </div>

    <div class="plan-insights-grid">
        <article class="plan-insight-card">
            <span>Total de itens</span>
            <strong><?= (int) $totalItems ?></strong>
        </article>
        <article class="plan-insight-card">
            <span>Taxa de conclusao</span>
            <strong><?= (float) $completionRate ?>%</strong>
        </article>
        <article class="plan-insight-card">
            <span>Taxa de publicacao</span>
            <strong><?= (float) $publicationRate ?>%</strong>
        </article>
        <article class="plan-insight-card<?= $overdueItems > 0 ? ' warn' : '' ?>">
            <span>Itens atrasados</span>
            <strong><?= (int) $overdueItems ?></strong>
        </article>
        <article class="plan-insight-card">
            <span>Proximo item pendente</span>
            <strong><?= e($nextPendingDate !== '' ? $nextPendingDate : '-') ?></strong>
        </article>
    </div>

    <div class="plan-status-bars">
        <?php foreach (['planned', 'scheduled', 'published', 'skipped'] as $statusKey): ?>
            <?php
            $count = (int) ($statusBreakdown[$statusKey] ?? 0);
            $width = round(($count / $statusBarTotal) * 100, 2);
            ?>
            <div class="plan-status-row">
                <span><?= e($statusLabels[$statusKey] ?? ucfirst($statusKey)) ?></span>
                <strong><?= (int) $count ?></strong>
            </div>
            <div class="metric-progress plan-status-progress status-<?= e($statusKey) ?>">
                <span style="width: <?= (float) $width ?>%"></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head-inline">
        <h3>Itens do plano</h3>
        <span class="meta-text"><?= count($items) ?> item(ns) exibidos</span>
    </div>

    <form method="get" action="<?= e($baseShowUrl) ?>" class="filters-grid plan-show-filters">
        <label>Status
            <select name="status">
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $statusFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Busca
            <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Buscar por titulo, descricao ou observacao">
        </label>

        <button type="submit"><i class="fa-solid fa-filter"></i> Aplicar filtros</button>
        <?php if ($statusFilter !== 'all' || $searchQuery !== ''): ?>
            <a class="btn btn-muted" href="<?= e($baseShowUrl) ?>"><i class="fa-solid fa-rotate-right"></i> Limpar filtros</a>
        <?php endif; ?>
    </form>

    <form method="post" action="<?= e(route_url('plans/bulkUpdateStatus')) ?>" class="plan-bulk-form" id="bulkStatusForm">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) $plan_id ?>">
        <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
        <input type="hidden" name="return_q" value="<?= e($searchQuery) ?>">
        <input type="hidden" name="selected_item_ids" id="selectedItemIdsInput" value="">

        <label>Status em lote
            <select name="bulk_status" required>
                <?php foreach ($itemStatusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="plan-bulk-actions">
            <button type="submit" class="btn-compact"><i class="fa-solid fa-layer-group"></i> Atualizar selecionados</button>
            <button type="button" class="btn-link" id="selectAllItemsBtn"><i class="fa-solid fa-check-double"></i> Marcar todos</button>
            <button type="button" class="btn-link" id="clearSelectedItemsBtn"><i class="fa-solid fa-eraser"></i> Limpar selecao</button>
        </div>

        <span class="meta-text">Selecionados: <strong id="selectedItemsCount">0</strong></span>
    </form>

    <div class="table-wrap">
        <table class="table plan-items-table">
            <thead>
            <tr>
                <th class="bulk-check-col"><input type="checkbox" id="selectAllItemsToggle" aria-label="Selecionar todos os itens"></th>
                <th>Data planejada</th>
                <th>Conteudo</th>
                <th>Formato</th>
                <th>Execucao</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5">Nenhum item encontrado para os filtros aplicados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $itemStatus = strtolower((string) ($item['status'] ?? 'planned'));
                    if (!isset($itemStatusOptions[$itemStatus])) {
                        $itemStatus = 'planned';
                    }
                    ?>
                    <tr>
                        <td class="bulk-check-col">
                            <input
                                type="checkbox"
                                class="bulk-item-checkbox"
                                value="<?= (int) $item['id'] ?>"
                                aria-label="Selecionar item #<?= (int) $item['id'] ?>"
                            >
                        </td>
                        <td><?= e($item['planned_date']) ?></td>
                        <td>
                            <strong><?= e($item['title']) ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <small class="plan-item-description"><?= e($item['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['format_type'] ?? '-') ?></td>
                        <td>
                            <form method="post" action="<?= e(route_url('plans/updateItem/' . (int) $item['id'])) ?>" class="item-update-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plan_id" value="<?= (int) $plan_id ?>">
                                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                                <input type="hidden" name="return_q" value="<?= e($searchQuery) ?>">

                                <div class="item-update-grid">
                                    <label>Status
                                        <select name="status" class="status-select status-<?= e($itemStatus) ?>">
                                            <?php foreach ($itemStatusOptions as $statusKey => $statusLabel): ?>
                                                <option value="<?= e($statusKey) ?>"<?= $itemStatus === $statusKey ? ' selected' : '' ?>>
                                                    <?= e($statusLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>Observacao
                                        <textarea name="manual_note" rows="2" placeholder="Registrar aprendizado, bloqueio ou acao tomada"><?= e($item['manual_note'] ?? '') ?></textarea>
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
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bulkForm = document.getElementById('bulkStatusForm');
    if (!bulkForm) {
        return;
    }

    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.bulk-item-checkbox'));
    var headerToggle = document.getElementById('selectAllItemsToggle');
    var selectAllBtn = document.getElementById('selectAllItemsBtn');
    var clearBtn = document.getElementById('clearSelectedItemsBtn');
    var selectedCount = document.getElementById('selectedItemsCount');
    var selectedInput = document.getElementById('selectedItemIdsInput');

    var getChecked = function () {
        return checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        });
    };

    var refreshSelectionUi = function () {
        var checked = getChecked();
        if (selectedCount) {
            selectedCount.textContent = String(checked.length);
        }

        if (!headerToggle) {
            return;
        }

        if (checkboxes.length === 0) {
            headerToggle.checked = false;
            headerToggle.indeterminate = false;
            return;
        }

        headerToggle.checked = checked.length === checkboxes.length;
        headerToggle.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    };

    var setAll = function (checked) {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = checked;
        });
        refreshSelectionUi();
    };

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshSelectionUi);
    });

    if (headerToggle) {
        headerToggle.addEventListener('change', function () {
            setAll(headerToggle.checked);
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            setAll(true);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            setAll(false);
        });
    }

    bulkForm.addEventListener('submit', function (event) {
        var ids = getChecked().map(function (checkbox) {
            return checkbox.value;
        });

        if (selectedInput) {
            selectedInput.value = ids.join(',');
        }

        if (ids.length === 0) {
            event.preventDefault();
            alert('Selecione ao menos um item para aplicar a atualizacao em lote.');
        }
    });

    refreshSelectionUi();
});
</script>
