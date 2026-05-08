<?php

namespace System\Library;

trait SubscriptionServiceCheckoutLifecycleTrait
{
    public function changePlan(int $userId, string $planSlug, string $paymentMethod = 'pix'): array
    {
        if ($userId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel atualizar seu plano no momento.'];
        }

        $paymentMethod = $this->sanitizePaymentMethod($paymentMethod);

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de planos indisponivel para alteracao de assinatura.'];
        }
        $this->ensureUserSubscription($userId);

        $targetPlan = $this->planBySlug($planSlug);
        if (!$targetPlan || (int) ($targetPlan['status'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Plano selecionado e invalido ou esta indisponivel.'];
        }

        $current = $this->contextForUser($userId);
        $currentPlanId = (int) ($current['plan']['id'] ?? 0);
        $targetPlanId = (int) ($targetPlan['id'] ?? 0);

        if ($currentPlanId === $targetPlanId) {
            return ['success' => true, 'message' => 'Seu plano ja esta ativo.'];
        }

        $subscription = $this->subscriptionByUser($userId);
        if (!$subscription) {
            return ['success' => false, 'message' => 'Assinatura nao encontrada para este usuario.'];
        }

        $amountCents = (int) ($targetPlan['price_monthly_cents'] ?? 0);
        if ((int) ($targetPlan['is_free'] ?? 0) === 1 || $amountCents <= 0) {
            $this->activatePlan($userId, $targetPlanId, 'active');
            $invoiceId = $this->createInvoice(
                $userId,
                (int) ($subscription['id'] ?? 0),
                $targetPlanId,
                0,
                $paymentMethod,
                'Ativacao de plano gratuito'
            );
            $this->markInvoicePaid($invoiceId, $paymentMethod);
            $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, 0, (string) ($targetPlan['currency'] ?? 'BRL'), [
                'provider' => 'mock',
                'note' => 'free_plan_activation',
            ]);

            $this->invalidateContext($userId);
            return ['success' => true, 'message' => 'Plano gratuito ativado com sucesso.'];
        }

        $pricing = $this->priceForPlanWithPromotion($targetPlanId, $amountCents);
        $discountCents = (int) ($pricing['discount_cents'] ?? 0);
        $finalAmountCents = (int) ($pricing['total_cents'] ?? $amountCents);
        $promotion = is_array($pricing['promotion'] ?? null) ? $pricing['promotion'] : null;
        $description = 'Upgrade para plano ' . (string) ($targetPlan['name'] ?? $planSlug);
        if ($promotion !== null && $discountCents > 0) {
            $description .= ' com promocao ' . (string) ($promotion['name'] ?? 'ativa');
        }

        if ($finalAmountCents <= 0) {
            $this->activatePlan($userId, $targetPlanId, 'active');
            $invoiceId = $this->createInvoice(
                $userId,
                (int) ($subscription['id'] ?? 0),
                $targetPlanId,
                0,
                $paymentMethod,
                $description,
                $amountCents
            );
            $this->markInvoicePaid($invoiceId, $paymentMethod);
            $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, 0, (string) ($targetPlan['currency'] ?? 'BRL'), [
                'provider' => 'mock',
                'note' => 'promotion_full_discount',
            ]);

            $this->invalidateContext($userId);
            return ['success' => true, 'message' => 'Plano atualizado com desconto promocional integral.'];
        }

        $invoiceId = $this->createInvoice(
            $userId,
            (int) ($subscription['id'] ?? 0),
            $targetPlanId,
            $finalAmountCents,
            $paymentMethod,
            $description,
            $amountCents
        );

        if ($this->mockAutoApprove()) {
            return $this->payInvoice($userId, $invoiceId, $paymentMethod);
        }

        $this->invalidateContext($userId);
        return [
            'success' => true,
            'message' => 'Fatura criada com sucesso. Finalize o pagamento para ativar o novo plano.',
            'invoice_id' => $invoiceId,
        ];
    }

    public function payInvoice(int $userId, int $invoiceId, string $paymentMethod = 'pix'): array
    {
        if ($userId <= 0 || $invoiceId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Pagamento nao pode ser processado.'];
        }

        $paymentMethod = $this->sanitizePaymentMethod($paymentMethod);

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de faturamento indisponivel para processamento de pagamento.'];
        }

        $invoice = $this->db()->fetch(
            'SELECT *
             FROM billing_invoices
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1',
            [
                'id' => $invoiceId,
                'user_id' => $userId,
            ]
        );
        if (!$invoice) {
            return ['success' => false, 'message' => 'Fatura nao encontrada para este usuario.'];
        }

        if ((string) ($invoice['status'] ?? '') === 'paid') {
            return ['success' => true, 'message' => 'Esta fatura ja esta paga.'];
        }

        $amount = (int) ($invoice['total_cents'] ?? 0);
        $currency = (string) ($invoice['currency'] ?? 'BRL');
        $planId = (int) ($invoice['plan_id'] ?? 0);

        if ($this->manualPaymentValidationEnabled()) {
            $pending = $this->db()->fetch(
                'SELECT id
                 FROM payment_transactions
                 WHERE invoice_id = :invoice_id
                   AND status = \'pending\'
                 ORDER BY id DESC
                 LIMIT 1',
                ['invoice_id' => $invoiceId]
            );
            if ($pending) {
                return ['success' => true, 'message' => 'Pagamento ja enviado para validacao manual.'];
            }

            $timestamp = $this->clockDateTimeNow();
            $this->insertTransaction($userId, $invoiceId, 'pending', $paymentMethod, $amount, $currency, [
                'provider' => 'mock',
                'gateway' => 'simulado',
                'submitted_at' => $timestamp,
                'validation_mode' => 'manual',
            ]);

            $this->db()->update('billing_invoices', [
                'payment_method' => mb_substr(strtolower(trim($paymentMethod)), 0, 40),
                'updated_at' => $timestamp,
            ], 'id = :id', ['id' => $invoiceId]);

            $this->logEvent(
                $userId,
                isset($invoice['subscription_id']) ? (int) $invoice['subscription_id'] : null,
                'payment_validation_requested',
                'Pagamento enviado para validacao manual.',
                [
                    'invoice_id' => $invoiceId,
                    'plan_id' => $planId,
                    'payment_method' => $paymentMethod,
                    'amount_cents' => $amount,
                ]
            );

            return ['success' => true, 'message' => 'Pagamento enviado para validacao. O plano sera ativado apos aprovacao do admin.'];
        }

        $this->insertTransaction($userId, $invoiceId, 'paid', $paymentMethod, $amount, $currency, [
            'provider' => 'mock',
            'gateway' => 'simulado',
            'approved_at' => $this->clockDateTimeNow(),
        ]);

        $this->markInvoicePaid($invoiceId, $paymentMethod);
        if ($planId > 0) {
            $this->activatePlan($userId, $planId, 'active');
        }

        $this->logEvent(
            $userId,
            isset($invoice['subscription_id']) ? (int) $invoice['subscription_id'] : null,
            'invoice_paid',
            'Fatura paga e plano atualizado.',
            [
                'invoice_id' => $invoiceId,
                'plan_id' => $planId,
                'payment_method' => $paymentMethod,
                'amount_cents' => $amount,
            ]
        );

        $this->invalidateContext($userId);

        return ['success' => true, 'message' => 'Pagamento confirmado e plano atualizado com sucesso.'];
    }
}
