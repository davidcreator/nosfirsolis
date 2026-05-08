<?php

namespace System\Library;

trait SubscriptionServicePaymentValidationTrait
{
    public function pendingValidations(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT pt.*, bi.invoice_number, bi.status AS invoice_status, bi.plan_id, bi.total_cents,
                    sp.name AS plan_name, u.name AS user_name, u.email AS user_email
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             INNER JOIN users u ON u.id = pt.user_id
             LEFT JOIN subscription_plans sp ON sp.id = bi.plan_id
             WHERE pt.status = \'pending\'
             ORDER BY pt.id DESC
             LIMIT ' . $limit
        );
    }

    public function approvePaymentTransaction(int $transactionId, int $adminUserId = 0, string $note = ''): array
    {
        if ($transactionId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Transacao invalida para aprovacao.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de pagamentos indisponivel para aprovacao.'];
        }
        $row = $this->db()->fetch(
            'SELECT pt.*, bi.subscription_id, bi.plan_id, bi.status AS invoice_status
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             WHERE pt.id = :id
             LIMIT 1',
            ['id' => $transactionId]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Transacao nao encontrada.'];
        }

        $status = (string) ($row['status'] ?? 'pending');
        if ($status === 'paid') {
            return ['success' => true, 'message' => 'Transacao ja estava aprovada.'];
        }
        if ($status !== 'pending') {
            return ['success' => false, 'message' => 'Apenas transacoes pendentes podem ser aprovadas.'];
        }

        $timestamp = $this->clockDateTimeNow();
        $payload = $this->decodeJsonPayload((string) ($row['payload_json'] ?? ''));
        $payload['validated_by_admin_id'] = $adminUserId;
        $payload['validated_at'] = $timestamp;
        if (trim($note) !== '') {
            $payload['validation_note'] = mb_substr(trim($note), 0, 255);
        }

        $this->db()->update('payment_transactions', [
            'status' => 'paid',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed_at' => $timestamp,
            'updated_at' => $timestamp,
        ], 'id = :id', ['id' => $transactionId]);

        $invoiceId = (int) ($row['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $paymentMethod = (string) ($row['payment_method'] ?? 'pix');
            $this->markInvoicePaid($invoiceId, $paymentMethod);
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $planId = (int) ($row['plan_id'] ?? 0);
        if ($userId > 0 && $planId > 0) {
            $this->activatePlan($userId, $planId, 'active');
            $this->invalidateContext($userId);
        }

        $this->logEvent(
            $userId,
            isset($row['subscription_id']) ? (int) $row['subscription_id'] : null,
            'invoice_paid_manual_validation',
            'Pagamento aprovado manualmente pelo admin.',
            [
                'transaction_id' => $transactionId,
                'invoice_id' => $invoiceId,
                'admin_user_id' => $adminUserId,
            ]
        );

        return ['success' => true, 'message' => 'Pagamento validado e aprovado com sucesso.'];
    }

    public function rejectPaymentTransaction(int $transactionId, int $adminUserId = 0, string $reason = ''): array
    {
        if ($transactionId <= 0 || !$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Transacao invalida para rejeicao.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de pagamentos indisponivel para rejeicao.'];
        }
        $row = $this->db()->fetch(
            'SELECT pt.*, bi.subscription_id, bi.status AS invoice_status
             FROM payment_transactions pt
             INNER JOIN billing_invoices bi ON bi.id = pt.invoice_id
             WHERE pt.id = :id
             LIMIT 1',
            ['id' => $transactionId]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Transacao nao encontrada.'];
        }

        $status = (string) ($row['status'] ?? 'pending');
        if ($status === 'failed') {
            return ['success' => true, 'message' => 'Transacao ja estava rejeitada.'];
        }
        if ($status !== 'pending') {
            return ['success' => false, 'message' => 'Apenas transacoes pendentes podem ser rejeitadas.'];
        }

        $timestamp = $this->clockDateTimeNow();
        $payload = $this->decodeJsonPayload((string) ($row['payload_json'] ?? ''));
        $payload['rejected_by_admin_id'] = $adminUserId;
        $payload['rejected_at'] = $timestamp;
        if (trim($reason) !== '') {
            $payload['rejection_reason'] = mb_substr(trim($reason), 0, 255);
        }

        $this->db()->update('payment_transactions', [
            'status' => 'failed',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed_at' => $timestamp,
            'updated_at' => $timestamp,
        ], 'id = :id', ['id' => $transactionId]);

        $invoiceId = (int) ($row['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $this->db()->update('billing_invoices', [
                'status' => 'failed',
                'updated_at' => $timestamp,
            ], 'id = :id', ['id' => $invoiceId]);
        }

        $this->logEvent(
            (int) ($row['user_id'] ?? 0),
            isset($row['subscription_id']) ? (int) $row['subscription_id'] : null,
            'invoice_rejected_manual_validation',
            'Pagamento rejeitado manualmente pelo admin.',
            [
                'transaction_id' => $transactionId,
                'invoice_id' => $invoiceId,
                'admin_user_id' => $adminUserId,
                'reason' => mb_substr(trim($reason), 0, 255),
            ]
        );

        return ['success' => true, 'message' => 'Pagamento rejeitado e marcado para revisao.'];
    }
}
