<?php
$context = (array) ($subscription_context ?? []);
$currentPlan = (array) ($context['plan'] ?? []);
$subscription = (array) ($context['subscription'] ?? []);
$metrics = (array) ($context['metrics'] ?? []);
$plans = (array) ($available_plans ?? []);
$invoices = (array) ($invoices ?? []);
$paymentMethods = (array) ($payment_methods ?? []);
$announcements = (array) ($billing_announcements ?? []);

$currentSlug = strtolower((string) ($currentPlan['slug'] ?? ''));
$currency = (string) ($currentPlan['currency'] ?? 'BRL');

$formatMoney = static function (int $value, string $currencyCode = 'BRL'): string {
    $amount = $value / 100;
    if (strtoupper($currencyCode) === 'BRL') {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }
    return strtoupper($currencyCode) . ' ' . number_format($amount, 2, '.', ',');
};

$resolveTier = static function (string $slug): array {
    $slug = strtolower(trim($slug));

    if ($slug === '' || str_contains($slug, 'grat') || str_contains($slug, 'free')) {
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
?>

<section class="panel billing-client-panel dashboard-hero">
    <div class="hero-content">
        <span class="hero-badge"><i class="fa-solid fa-credit-card"></i> Billing Hub</span>
        <h2><i class="fa-solid fa-wallet"></i> Planos e faturamento</h2>
        <p>Gerencie sua assinatura, acompanhe limites de uso e realize upgrade para Bronze, Prata ou Ouro.</p>
    </div>
</section>

<?php if (!empty($announcements)): ?>
<section class="panel billing-client-panel">
    <h3><i class="fa-solid fa-bullhorn"></i> Comunicados de preço e promoção</h3>
    <div class="usage-grid">
        <?php foreach ($announcements as $announcement): ?>
            <?php
            $announcementType = strtolower((string) ($announcement['announcement_type'] ?? 'informativo'));
            $announcementTypeClass = preg_replace('/[^a-z0-9_-]/', '', $announcementType);
            ?>
            <article class="usage-card billing-announce-card type-<?= e($announcementTypeClass) ?>">
                <span class="billing-announce-type"><?= e((string) ($announcement['announcement_type'] ?? 'informativo')) ?></span>
                <strong><?= e((string) ($announcement['title'] ?? 'Comunicado')) ?></strong>
                <p><?= e((string) ($announcement['message'] ?? '')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel billing-client-panel">
    <div class="panel-head-inline">
        <h3><i class="fa-solid fa-id-badge"></i> Plano atual</h3>
        <span class="meta-text">Status: <?= e((string) ($subscription['status'] ?? 'active')) ?></span>
    </div>
    <div class="plan-insights-grid">
        <article class="plan-insight-card">
            <span>Plano ativo</span>
            <strong><?= e((string) ($currentPlan['name'] ?? 'Básico Gratuito')) ?></strong>
            <small><?= e((string) ($currentPlan['description'] ?? '')) ?></small>
        </article>
        <article class="plan-insight-card">
            <span>Mensalidade</span>
            <strong><?= $formatMoney((int) ($currentPlan['price_monthly_cents'] ?? 0), $currency) ?></strong>
            <small>Ciclo <?= e((string) ($subscription['billing_cycle'] ?? 'monthly')) ?></small>
        </article>
        <article class="plan-insight-card">
            <span>Próxima cobrança</span>
            <strong><?= e((string) ($subscription['next_billing_at'] ?? '-')) ?></strong>
            <small>Atualizado automaticamente</small>
        </article>
    </div>
</section>

<section class="panel billing-client-panel">
    <h3><i class="fa-solid fa-chart-pie"></i> Consumo no período atual</h3>
    <div class="usage-grid">
        <?php foreach ($metrics as $metric): ?>
            <?php
            $limit = (int) ($metric['limit'] ?? -1);
            $used = (int) ($metric['used'] ?? 0);
            $percent = (float) ($metric['percent'] ?? 0.0);
            $isUnlimited = $limit < 0;
            ?>
            <article class="usage-card">
                <span><?= e((string) ($metric['label'] ?? 'Métrica')) ?></span>
                <strong><?= $used ?><?= $isUnlimited ? ' / Ilimitado' : ' / ' . $limit ?></strong>
                <div class="metric-progress">
                    <span style="width: <?= $isUnlimited ? 100 : max(0.0, min(100.0, $percent)) ?>%"></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel billing-client-panel">
    <h3><i class="fa-solid fa-sliders"></i> Alterar plano</h3>
    <div class="pricing-grid">
        <?php foreach ($plans as $plan): ?>
            <?php
            $slug = strtolower((string) ($plan['slug'] ?? ''));
            $isCurrent = $slug !== '' && $slug === $currentSlug;
            $priceMonthly = (int) ($plan['price_monthly_cents'] ?? 0);
            $priceMonthlyFinal = (int) ($plan['price_monthly_final_cents'] ?? $priceMonthly);
            $priceYearly = (int) ($plan['price_yearly_cents'] ?? 0);
            $discountMonthly = max(0, (int) ($plan['price_monthly_discount_cents'] ?? 0));
            $activePromotion = is_array($plan['active_promotion'] ?? null) ? $plan['active_promotion'] : null;
            $tier = $resolveTier($slug);
            ?>
            <article class="pricing-card <?= e((string) ($tier['class'] ?? 'tier-custom')) ?><?= $isCurrent ? ' is-current' : '' ?>">
                <div class="pricing-tier-row">
                    <span class="pricing-tier-badge">
                        <i class="<?= e((string) ($tier['icon'] ?? 'fa-solid fa-layer-group')) ?>"></i>
                        <?= e((string) ($tier['label'] ?? 'Plano')) ?>
                    </span>
                    <?php if ($isCurrent): ?>
                        <span class="pricing-current-chip">Ativo</span>
                    <?php endif; ?>
                </div>
                <h4><?= e((string) ($plan['name'] ?? 'Plano')) ?></h4>
                <p><?= e((string) ($plan['description'] ?? '')) ?></p>
                <div class="pricing-values">
                    <strong><?= $formatMoney($priceMonthlyFinal, (string) ($plan['currency'] ?? 'BRL')) ?>/mês</strong>
                    <?php if ($discountMonthly > 0): ?>
                        <small>
                            De <?= $formatMoney($priceMonthly, (string) ($plan['currency'] ?? 'BRL')) ?>
                            por <?= $formatMoney($priceMonthlyFinal, (string) ($plan['currency'] ?? 'BRL')) ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($priceYearly > 0): ?>
                        <small><?= $formatMoney($priceYearly, (string) ($plan['currency'] ?? 'BRL')) ?>/ano</small>
                    <?php endif; ?>
                    <?php if ($activePromotion): ?>
                        <small>
                            Promoção ativa: <?= e((string) ($activePromotion['name'] ?? '')) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(route_url('billing/subscribe')) ?>" class="pricing-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_slug" value="<?= e($slug) ?>">
                    <?php if ($priceMonthlyFinal > 0): ?>
                        <label>Método de pagamento
                            <select name="payment_method">
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= e((string) ($method['key'] ?? 'pix')) ?>"><?= e((string) ($method['label'] ?? 'PIX')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else: ?>
                        <input type="hidden" name="payment_method" value="free">
                    <?php endif; ?>
                    <button type="submit" <?= $isCurrent ? 'disabled' : '' ?>>
                        <?= $isCurrent ? 'Plano atual' : 'Selecionar plano' ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel billing-client-panel">
    <h3><i class="fa-solid fa-file-invoice-dollar"></i> Histórico de cobranças</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Fatura</th>
                <th>Plano</th>
                <th>Total</th>
                <th>Status</th>
                <th>Vencimento</th>
                <th>Pagamento</th>
                <th>Ação</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="7">Nenhuma cobrança registrada até o momento.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <?php
                    $status = strtolower((string) ($invoice['status'] ?? 'open'));
                    $statusClass = preg_replace('/[^a-z0-9_-]/', '', $status);
                    ?>
                    <tr>
                        <td><?= e((string) ($invoice['invoice_number'] ?? ('INV-' . (int) ($invoice['id'] ?? 0)))) ?></td>
                        <td><?= e((string) ($invoice['plan_name'] ?? '-')) ?></td>
                        <td><?= $formatMoney((int) ($invoice['total_cents'] ?? 0), (string) ($invoice['currency'] ?? 'BRL')) ?></td>
                        <td><span class="invoice-status status-<?= e($statusClass) ?>"><?= e($status) ?></span></td>
                        <td><?= e((string) ($invoice['due_at'] ?? '-')) ?></td>
                        <td><?= e((string) ($invoice['payment_method'] ?? '-')) ?></td>
                        <td>
                            <?php if ($status === 'open' || $status === 'failed'): ?>
                                <form method="post" action="<?= e(route_url('billing/payInvoice/' . (int) ($invoice['id'] ?? 0))) ?>" class="invoice-pay-form">
                                    <?= csrf_field() ?>
                                    <select name="payment_method">
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <option value="<?= e((string) ($method['key'] ?? 'pix')) ?>"><?= e((string) ($method['label'] ?? 'PIX')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-link"><i class="fa-solid fa-check"></i> Pagar agora</button>
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

