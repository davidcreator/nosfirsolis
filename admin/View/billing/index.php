<?php
$plans = (array) ($plans ?? []);
$promotions = (array) ($promotions ?? []);
$announcements = (array) ($announcements ?? []);
$paymentSettings = (array) ($payment_settings ?? []);
$pendingValidations = (array) ($pending_validations ?? []);
$checkoutMethods = (array) ($checkout_methods ?? []);

$formatMoney = static function (int $value): string {
    $amount = $value / 100;
    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$resolveTier = static function (string $slug, bool $isFree): array {
    $slug = strtolower(trim($slug));

    if ($isFree || $slug === '' || str_contains($slug, 'grat') || str_contains($slug, 'free')) {
        return ['class' => 'tier-free', 'icon' => 'fa-regular fa-gem', 'label' => 'Gratuito'];
    }

    if (str_contains($slug, 'bronze')) {
        return ['class' => 'tier-bronze', 'icon' => 'fa-solid fa-medal', 'label' => 'Bronze'];
    }

    if (str_contains($slug, 'prata') || str_contains($slug, 'silver')) {
        return ['class' => 'tier-silver', 'icon' => 'fa-solid fa-shield-halved', 'label' => 'Prata'];
    }

    if (str_contains($slug, 'ouro') || str_contains($slug, 'gold')) {
        return ['class' => 'tier-gold', 'icon' => 'fa-solid fa-crown', 'label' => 'Ouro'];
    }

    return ['class' => 'tier-custom', 'icon' => 'fa-solid fa-layer-group', 'label' => 'Plano'];
};

$activePlans = 0;
foreach ($plans as $plan) {
    if (!empty($plan['status'])) {
        $activePlans++;
    }
}

$activePromotions = 0;
foreach ($promotions as $promotionItem) {
    if (!empty($promotionItem['status'])) {
        $activePromotions++;
    }
}

$activeAnnouncements = 0;
foreach ($announcements as $announcementItem) {
    if (!empty($announcementItem['status'])) {
        $activeAnnouncements++;
    }
}

$enabledMethodsCount = 0;
$configuredMethods = (array) ($paymentSettings['methods'] ?? []);
foreach ($configuredMethods as $isEnabled) {
    if (!empty($isEnabled)) {
        $enabledMethodsCount++;
    }
}
?>

<section class="panel billing-admin-panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-credit-card"></i> Billing Admin</span>
        <h1><?= e($t('billing.heading_index', 'Planos, pagamentos e validações')) ?></h1>
        <p><?= e($t('billing.description_index', 'Gerencie valores de planos, promoções, notícias de reajuste e o fluxo de validação de pagamentos.')) ?></p>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="billing-summary-grid">
        <article class="billing-summary-card accent-blue">
            <span class="summary-icon"><i class="fa-solid fa-layer-group"></i></span>
            <div>
                <strong><?= (int) count($plans) ?></strong>
                <span>Planos cadastrados</span>
                <small><?= (int) $activePlans ?> ativo(s)</small>
            </div>
        </article>
        <article class="billing-summary-card accent-red">
            <span class="summary-icon"><i class="fa-solid fa-tags"></i></span>
            <div>
                <strong><?= (int) count($promotions) ?></strong>
                <span>Promoções</span>
                <small><?= (int) $activePromotions ?> ativa(s)</small>
            </div>
        </article>
        <article class="billing-summary-card accent-green">
            <span class="summary-icon"><i class="fa-solid fa-clipboard-check"></i></span>
            <div>
                <strong><?= (int) count($pendingValidations) ?></strong>
                <span>Validações pendentes</span>
                <small><?= (int) $activeAnnouncements ?> comunicado(s) ativo(s)</small>
            </div>
        </article>
        <article class="billing-summary-card accent-amber">
            <span class="summary-icon"><i class="fa-solid fa-wallet"></i></span>
            <div>
                <strong><?= (int) $enabledMethodsCount ?></strong>
                <span>Meios habilitados</span>
                <small><?= (int) count($checkoutMethods) ?> em checkout</small>
            </div>
        </article>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="billing-quick-actions">
        <a class="billing-quick-action action-promo" href="#billingPromotionForm">
            <span class="quick-icon"><i class="fa-solid fa-tags"></i></span>
            <span class="quick-content">
                <strong>Nova promoção</strong>
                <small>Cadastrar desconto para upgrade</small>
            </span>
        </a>
        <a class="billing-quick-action action-announce" href="#billingAnnouncementForm">
            <span class="quick-icon"><i class="fa-solid fa-bullhorn"></i></span>
            <span class="quick-content">
                <strong>Novo comunicado</strong>
                <small>Publicar aviso para clientes</small>
            </span>
        </a>
        <a class="billing-quick-action action-validate" href="#billingValidationsPanel">
            <span class="quick-icon"><i class="fa-solid fa-clipboard-check"></i></span>
            <span class="quick-content">
                <strong>Validar pagamentos</strong>
                <small><?= (int) count($pendingValidations) ?> pendência(s)</small>
            </span>
        </a>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-sliders"></i> <?= e($t('billing.heading_plans', 'Configuração de planos e limites')) ?></h2>
        <span class="meta-text"><?= e($t('billing.meta_plans', 'Valores em centavos para evitar arredondamentos.')) ?></span>
    </div>

    <div class="billing-plan-grid">
        <?php foreach ($plans as $plan): ?>
            <?php
            $planId = (int) ($plan['id'] ?? 0);
            $limits = (array) ($plan['limits'] ?? []);
            $promotion = is_array($plan['active_promotion'] ?? null) ? $plan['active_promotion'] : null;
            $slug = strtolower((string) ($plan['slug'] ?? ''));
            $isFree = !empty($plan['is_free']) || (int) ($plan['price_monthly_cents'] ?? 0) <= 0;
            $tier = $resolveTier($slug, $isFree);
            $statusClass = !empty($plan['status']) ? 'status-active' : 'status-inactive';
            ?>
            <article class="billing-plan-card <?= e((string) ($tier['class'] ?? 'tier-custom')) ?>">
                <div class="billing-plan-head">
                    <span class="billing-tier-badge">
                        <i class="<?= e((string) ($tier['icon'] ?? 'fa-solid fa-layer-group')) ?>"></i>
                        <?= e((string) ($tier['label'] ?? 'Plano')) ?>
                    </span>
                    <span class="billing-plan-status <?= e($statusClass) ?>"><?= !empty($plan['status']) ? 'Ativo' : 'Inativo' ?></span>
                </div>
                <h3><?= e((string) ($plan['name'] ?? 'Plano')) ?></h3>
                <div class="billing-plan-prices">
                    <strong><?= $formatMoney((int) ($plan['price_monthly_cents'] ?? 0)) ?>/mês</strong>
                    <small><?= $formatMoney((int) ($plan['price_yearly_cents'] ?? 0)) ?>/ano</small>
                </div>
                <p class="meta-text"><?= e((string) ($plan['description'] ?? '')) ?></p>
                <?php if ($promotion): ?>
                    <div class="table-chip">
                        <?= e($t('billing.label_active_promotion', 'Promoção ativa')) ?>:
                        <?= e((string) ($promotion['name'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(route_url('billing/savePlan/' . $planId)) ?>" class="form-grid">
                    <?= csrf_field() ?>

                    <label><?= e($t('billing.field_plan_name', 'Nome')) ?>
                        <input type="text" name="name" value="<?= e((string) ($plan['name'] ?? '')) ?>" required>
                    </label>

                    <label><?= e($t('billing.field_currency', 'Moeda')) ?>
                        <input type="text" name="currency" value="<?= e((string) ($plan['currency'] ?? 'BRL')) ?>" maxlength="3" required>
                    </label>

                    <label><?= e($t('billing.field_monthly_cents', 'Mensalidade (centavos)')) ?>
                        <input type="number" name="price_monthly_cents" min="0" step="1" value="<?= (int) ($plan['price_monthly_cents'] ?? 0) ?>" required>
                    </label>

                    <label><?= e($t('billing.field_yearly_cents', 'Anuidade (centavos)')) ?>
                        <input type="number" name="price_yearly_cents" min="0" step="1" value="<?= (int) ($plan['price_yearly_cents'] ?? 0) ?>" required>
                    </label>

                    <label><?= e($t('billing.field_sort_order', 'Ordem')) ?>
                        <input type="number" name="sort_order" min="0" step="1" value="<?= (int) ($plan['sort_order'] ?? 0) ?>" required>
                    </label>

                    <label class="full"><?= e($t('billing.field_plan_description', 'Descrição')) ?>
                        <textarea name="description" rows="2"><?= e((string) ($plan['description'] ?? '')) ?></textarea>
                    </label>

                    <label class="chip"><input type="checkbox" name="is_free" value="1" <?= !empty($plan['is_free']) ? 'checked' : '' ?>> <?= e($t('billing.field_is_free', 'Plano gratuito')) ?></label>
                    <label class="chip"><input type="checkbox" name="ad_supported" value="1" <?= !empty($plan['ad_supported']) ? 'checked' : '' ?>> <?= e($t('billing.field_ads', 'Com propaganda')) ?></label>
                    <label class="chip"><input type="checkbox" name="is_public" value="1" <?= !empty($plan['is_public']) ? 'checked' : '' ?>> <?= e($t('billing.field_public', 'Visível para usuários')) ?></label>
                    <label class="chip"><input type="checkbox" name="status" value="1" <?= !empty($plan['status']) ? 'checked' : '' ?>> <?= e($t('billing.field_status', 'Ativo')) ?></label>

                    <fieldset class="full">
                        <legend><?= e($t('billing.legend_limits', 'Limites e recursos')) ?></legend>
                        <div class="form-grid">
                            <label>Planos editoriais/mês
                                <input type="number" name="limits[max_editorial_plans_per_month]" step="1" value="<?= (int) ($limits['max_editorial_plans_per_month'] ?? 0) ?>">
                            </label>
                            <label>Publicações sociais/mês
                                <input type="number" name="limits[max_social_publications_per_month]" step="1" value="<?= (int) ($limits['max_social_publications_per_month'] ?? 0) ?>">
                            </label>
                            <label>Contas sociais
                                <input type="number" name="limits[max_social_accounts]" step="1" value="<?= (int) ($limits['max_social_accounts'] ?? 0) ?>">
                            </label>
                            <label>Links de rastreio/mês
                                <input type="number" name="limits[max_tracking_links_per_month]" step="1" value="<?= (int) ($limits['max_tracking_links_per_month'] ?? 0) ?>">
                            </label>
                            <label>Eventos extras/mês
                                <input type="number" name="limits[max_calendar_extra_events_per_month]" step="1" value="<?= (int) ($limits['max_calendar_extra_events_per_month'] ?? 0) ?>">
                            </label>

                            <label class="chip"><input type="checkbox" name="limits[ads_enabled]" value="1" <?= !empty($limits['ads_enabled']) ? 'checked' : '' ?>> Ads habilitados</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_template_plans]" value="1" <?= !empty($limits['allow_template_plans']) ? 'checked' : '' ?>> Templates anuais</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_ai_draft_generator]" value="1" <?= !empty($limits['allow_ai_draft_generator']) ? 'checked' : '' ?>> Gerador de drafts</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_format_presets]" value="1" <?= !empty($limits['allow_format_presets']) ? 'checked' : '' ?>> Presets avançados</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_publish_hub]" value="1" <?= !empty($limits['allow_publish_hub']) ? 'checked' : '' ?>> Hub de publicação</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_queue_processing]" value="1" <?= !empty($limits['allow_queue_processing']) ? 'checked' : '' ?>> Processar fila</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_tracking_links]" value="1" <?= !empty($limits['allow_tracking_links']) ? 'checked' : '' ?>> Tracking links</label>
                            <label class="chip"><input type="checkbox" name="limits[allow_social_connections]" value="1" <?= !empty($limits['allow_social_connections']) ? 'checked' : '' ?>> Conexões sociais</label>
                        </div>
                    </fieldset>

                    <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= e($t('common.button_save', 'Salvar')) ?></button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-tags"></i> <?= e($t('billing.heading_promotions', 'Promoções e descontos')) ?></h2>
        <span class="meta-text"><?= e($t('billing.meta_promotions', 'Desconto automático aplicado no upgrade para planos pagos.')) ?></span>
    </div>

    <div class="billing-split-grid">
        <article class="billing-surface">
            <h3><i class="fa-solid fa-plus"></i> Nova promoção</h3>
            <form id="billingPromotionForm" method="post" action="<?= e(route_url('billing/savePromotion')) ?>" class="form-grid billing-form">
        <?= csrf_field() ?>
        <label>Nome
            <input type="text" name="name" required>
        </label>
        <label>Código
            <input type="text" name="code" placeholder="PROMO10">
        </label>
        <label>Plano alvo
            <select name="plan_id">
                <option value="0">Todos os planos pagos</option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= (int) ($plan['id'] ?? 0) ?>"><?= e((string) ($plan['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo de desconto
            <select name="discount_type">
                <option value="percent">Percentual (%)</option>
                <option value="amount">Valor fixo (centavos)</option>
            </select>
        </label>
        <label>Valor do desconto
            <input type="number" name="discount_value" min="1" step="1" required>
        </label>
        <label>Início
            <input type="datetime-local" name="starts_at">
        </label>
        <label>Fim
            <input type="datetime-local" name="ends_at">
        </label>
        <label class="chip"><input type="checkbox" name="is_public" value="1" checked> Pública</label>
        <label class="chip"><input type="checkbox" name="status" value="1" checked> Ativa</label>
        <label class="full">Descrição
            <input type="text" name="description">
        </label>
        <button type="submit"><i class="fa-solid fa-tag"></i> Criar promoção</button>
            </form>
        </article>

        <article class="billing-surface">
            <h3><i class="fa-solid fa-table-list"></i> Promocoes cadastradas</h3>
            <div class="table-wrap billing-table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Nome</th>
                <th>Plano</th>
                <th>Desconto</th>
                <th>Período</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($promotions)): ?>
                <tr><td colspan="6">Nenhuma promoção cadastrada.</td></tr>
            <?php else: ?>
                <?php foreach ($promotions as $promotion): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($promotion['name'] ?? '')) ?></strong>
                            <small class="meta-text"><?= e((string) ($promotion['code'] ?? '')) ?></small>
                        </td>
                        <td><?= e((string) ($promotion['plan_name'] ?? 'Todos')) ?></td>
                        <td>
                            <?php if ((string) ($promotion['discount_type'] ?? 'percent') === 'amount'): ?>
                                <?= $formatMoney((int) ($promotion['discount_value'] ?? 0)) ?>
                            <?php else: ?>
                                <?= (int) ($promotion['discount_value'] ?? 0) ?>%
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($promotion['starts_at'] ?? '-')) ?> até <?= e((string) ($promotion['ends_at'] ?? '-')) ?></td>
                        <td>
                            <span class="status-pill <?= !empty($promotion['status']) ? 'status-active' : 'status-inactive' ?>">
                                <?= !empty($promotion['status']) ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="<?= e(route_url('billing/deletePromotion/' . (int) ($promotion['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir promoção?')">
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
        </article>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-bullhorn"></i> <?= e($t('billing.heading_announcements', 'Notícias de desconto e reajuste')) ?></h2>
        <span class="meta-text"><?= e($t('billing.meta_announcements', 'Comunicados exibidos para os usuários na área de billing.')) ?></span>
    </div>

    <div class="billing-split-grid">
        <article class="billing-surface">
            <h3><i class="fa-solid fa-bullhorn"></i> Novo comunicado</h3>
            <form id="billingAnnouncementForm" method="post" action="<?= e(route_url('billing/saveAnnouncement')) ?>" class="form-grid billing-form">
        <?= csrf_field() ?>
        <label>Título
            <input type="text" name="title" required>
        </label>
        <label>Tipo
            <select name="announcement_type">
                <option value="discount">Desconto</option>
                <option value="reajuste">Reajuste</option>
                <option value="informativo">Informativo</option>
            </select>
        </label>
        <label>Início
            <input type="datetime-local" name="starts_at">
        </label>
        <label>Fim
            <input type="datetime-local" name="ends_at">
        </label>
        <label class="chip"><input type="checkbox" name="status" value="1" checked> Ativo</label>
        <label class="full">Mensagem
            <textarea name="message" rows="3" required></textarea>
        </label>
        <button type="submit"><i class="fa-solid fa-bullhorn"></i> Publicar comunicado</button>
            </form>
        </article>

        <article class="billing-surface">
            <h3><i class="fa-solid fa-table-list"></i> Comunicados publicados</h3>
            <div class="table-wrap billing-table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Período</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($announcements)): ?>
                <tr><td colspan="5">Nenhum comunicado registrado.</td></tr>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) ($announcement['title'] ?? '')) ?></strong>
                            <small class="meta-text"><?= e((string) ($announcement['message'] ?? '')) ?></small>
                        </td>
                        <td><?= e((string) ($announcement['announcement_type'] ?? 'informativo')) ?></td>
                        <td><?= e((string) ($announcement['starts_at'] ?? '-')) ?> até <?= e((string) ($announcement['ends_at'] ?? '-')) ?></td>
                        <td>
                            <span class="status-pill <?= !empty($announcement['status']) ? 'status-active' : 'status-inactive' ?>">
                                <?= !empty($announcement['status']) ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="<?= e(route_url('billing/deleteAnnouncement/' . (int) ($announcement['id'] ?? 0))) ?>" onsubmit="return confirm('Excluir comunicado?')">
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
        </article>
    </div>
</section>

<section class="panel billing-admin-panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-wallet"></i> <?= e($t('billing.heading_payment_settings', 'Conta recebedora e meios de pagamento')) ?></h2>
        <span class="meta-text"><?= e($t('billing.meta_payment_settings', 'Defina para onde os valores vão, meios aceitos e modo de validação.')) ?></span>
    </div>

    <form method="post" action="<?= e(route_url('billing/savePaymentSettings')) ?>" class="form-grid billing-form">
        <?= csrf_field() ?>

        <label>Moeda
            <input type="text" name="currency" value="<?= e((string) ($paymentSettings['currency'] ?? 'BRL')) ?>" maxlength="3">
        </label>
        <label>Nome da conta recebedora
            <input type="text" name="receiver_name" value="<?= e((string) ($paymentSettings['receiver_name'] ?? '')) ?>">
        </label>
        <label>Documento
            <input type="text" name="receiver_document" value="<?= e((string) ($paymentSettings['receiver_document'] ?? '')) ?>">
        </label>
        <label>Banco
            <input type="text" name="receiver_bank" value="<?= e((string) ($paymentSettings['receiver_bank'] ?? '')) ?>">
        </label>
        <label>Agência
            <input type="text" name="receiver_agency" value="<?= e((string) ($paymentSettings['receiver_agency'] ?? '')) ?>">
        </label>
        <label>Conta
            <input type="text" name="receiver_account" value="<?= e((string) ($paymentSettings['receiver_account'] ?? '')) ?>">
        </label>
        <label>Tipo de conta
            <?php $accountType = (string) ($paymentSettings['receiver_account_type'] ?? 'checking'); ?>
            <select name="receiver_account_type">
                <option value="checking" <?= $accountType === 'checking' ? 'selected' : '' ?>>Corrente</option>
                <option value="savings" <?= $accountType === 'savings' ? 'selected' : '' ?>>Poupança</option>
                <option value="wallet" <?= $accountType === 'wallet' ? 'selected' : '' ?>>Carteira digital</option>
            </select>
        </label>
        <label>Chave PIX
            <input type="text" name="receiver_pix_key" value="<?= e((string) ($paymentSettings['receiver_pix_key'] ?? '')) ?>">
        </label>
        <label>E-mail financeiro
            <input type="email" name="receiver_email" value="<?= e((string) ($paymentSettings['receiver_email'] ?? '')) ?>">
        </label>

        <?php $validationMode = (string) ($paymentSettings['validation_mode'] ?? 'automatic'); ?>
        <label>Modo de validação
            <select name="validation_mode">
                <option value="automatic" <?= $validationMode === 'automatic' ? 'selected' : '' ?>>Automático</option>
                <option value="manual" <?= $validationMode === 'manual' ? 'selected' : '' ?>>Manual (aprovação no admin)</option>
            </select>
        </label>

        <label class="chip"><input type="checkbox" name="mock_auto_approve" value="1" <?= !empty($paymentSettings['mock_auto_approve']) ? 'checked' : '' ?>> Autoaprovar pagamento mock</label>

        <?php $methods = (array) ($paymentSettings['methods'] ?? []); ?>
        <fieldset class="full">
            <legend>Meios de pagamento aceitos</legend>
            <div class="chips-grid">
                <label class="chip"><input type="checkbox" name="method_pix" value="1" <?= !empty($methods['pix']) ? 'checked' : '' ?>> PIX</label>
                <label class="chip"><input type="checkbox" name="method_boleto" value="1" <?= !empty($methods['boleto']) ? 'checked' : '' ?>> Boleto</label>
                <label class="chip"><input type="checkbox" name="method_card" value="1" <?= !empty($methods['card']) ? 'checked' : '' ?>> Cartão</label>
                <label class="chip"><input type="checkbox" name="method_transfer" value="1" <?= !empty($methods['transfer']) ? 'checked' : '' ?>> Transferência</label>
            </div>
        </fieldset>

        <label class="full">Notas de validação e instruções
            <textarea name="validation_notes" rows="3"><?= e((string) ($paymentSettings['validation_notes'] ?? '')) ?></textarea>
        </label>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar configurações</button>
    </form>

    <p class="meta-text" style="margin-top:10px">
        Métodos disponíveis hoje no checkout:
        <?php if (empty($checkoutMethods)): ?>
            nenhum
        <?php else: ?>
            <?= e(implode(', ', array_map(static fn ($m) => (string) ($m['label'] ?? ''), $checkoutMethods))) ?>
        <?php endif; ?>
    </p>
</section>

<section class="panel billing-admin-panel" id="billingValidationsPanel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-clipboard-check"></i> <?= e($t('billing.heading_validations', 'Fila de validação de pagamentos')) ?></h2>
        <span class="meta-text"><?= e($t('billing.meta_validations', 'Aprove ou rejeite pagamentos pendentes quando o modo manual estiver ativo.')) ?></span>
    </div>

    <div class="table-wrap billing-table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Transação</th>
                <th>Usuário</th>
                <th>Fatura</th>
                <th>Plano</th>
                <th>Valor</th>
                <th>Método</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($pendingValidations)): ?>
                <tr><td colspan="8">Nenhum pagamento pendente de validação.</td></tr>
            <?php else: ?>
                <?php foreach ($pendingValidations as $tx): ?>
                    <tr>
                        <td>#<?= (int) ($tx['id'] ?? 0) ?><br><small><?= e((string) ($tx['created_at'] ?? '')) ?></small></td>
                        <td><?= e((string) ($tx['user_name'] ?? '')) ?><br><small><?= e((string) ($tx['user_email'] ?? '')) ?></small></td>
                        <td><?= e((string) ($tx['invoice_number'] ?? ('INV-' . (int) ($tx['invoice_id'] ?? 0)))) ?></td>
                        <td><?= e((string) ($tx['plan_name'] ?? '-')) ?></td>
                        <td><?= $formatMoney((int) ($tx['amount_cents'] ?? 0)) ?></td>
                        <td><?= e((string) ($tx['payment_method'] ?? '-')) ?></td>
                        <td><span class="status-pill status-pending">Pendente</span></td>
                        <td>
                            <div class="validation-actions">
                                <form method="post" action="<?= e(route_url('billing/approvePayment/' . (int) ($tx['id'] ?? 0))) ?>" class="validation-action-form">
                                    <?= csrf_field() ?>
                                    <input type="text" name="validation_note" placeholder="Observação (opcional)">
                                    <button class="btn-link" type="submit"><i class="fa-solid fa-check"></i> Aprovar</button>
                                </form>
                                <form method="post" action="<?= e(route_url('billing/rejectPayment/' . (int) ($tx['id'] ?? 0))) ?>" class="validation-action-form">
                                    <?= csrf_field() ?>
                                    <input type="text" name="rejection_reason" placeholder="Motivo da rejeição">
                                    <button class="btn-link danger" type="submit"><i class="fa-solid fa-xmark"></i> Rejeitar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

