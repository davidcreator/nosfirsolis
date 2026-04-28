<?php

namespace Admin\Controller;

use System\Library\SubscriptionService;

class BillingController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.billing');

        $service = new SubscriptionService($this->registry);
        $service->ensureTables();

        $this->render('billing/index', [
            'title' => $this->t('billing.title_index', 'Planos e Pagamentos'),
            'plans' => $service->adminPlans(),
            'promotions' => $service->listPromotions(200),
            'announcements' => $service->listAnnouncements(200),
            'payment_settings' => $service->billingSettings(),
            'pending_validations' => $service->pendingValidations(200),
            'checkout_methods' => $service->paymentMethodsForCheckout(),
        ]);
    }

    public function savePlan(int $planId): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $result = $service->savePlanConfig($planId, [
            'name' => (string) $this->request->post('name', ''),
            'description' => (string) $this->request->post('description', ''),
            'currency' => (string) $this->request->post('currency', 'BRL'),
            'price_monthly_cents' => (int) $this->request->post('price_monthly_cents', 0),
            'price_yearly_cents' => (int) $this->request->post('price_yearly_cents', 0),
            'is_free' => $this->request->post('is_free', ''),
            'ad_supported' => $this->request->post('ad_supported', ''),
            'is_public' => $this->request->post('is_public', ''),
            'status' => $this->request->post('status', ''),
            'sort_order' => (int) $this->request->post('sort_order', 0),
            'limits' => (array) $this->request->post('limits', []),
        ]);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_plan_saved', 'Plano atualizado com sucesso.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_plan_error', 'Não foi possível salvar o plano.')));
        $this->redirectToRoute('billing/index');
    }

    public function savePromotion(): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $result = $service->savePromotion([
            'id' => (int) $this->request->post('id', 0),
            'name' => (string) $this->request->post('name', ''),
            'code' => (string) $this->request->post('code', ''),
            'description' => (string) $this->request->post('description', ''),
            'plan_id' => (int) $this->request->post('plan_id', 0),
            'discount_type' => (string) $this->request->post('discount_type', 'percent'),
            'discount_value' => (int) $this->request->post('discount_value', 0),
            'starts_at' => (string) $this->request->post('starts_at', ''),
            'ends_at' => (string) $this->request->post('ends_at', ''),
            'is_public' => $this->request->post('is_public', ''),
            'status' => $this->request->post('status', ''),
        ]);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_promotion_saved', 'Promoção salva com sucesso.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_promotion_error', 'Não foi possível salvar a promoção.')));
        $this->redirectToRoute('billing/index');
    }

    public function deletePromotion(int $promotionId): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $deleted = $service->deletePromotion($promotionId);

        if ($deleted) {
            flash('success', $this->t('billing.flash_promotion_deleted', 'Promoção removida com sucesso.'));
            $this->redirectToRoute('billing/index');
        }

        flash('error', $this->t('billing.flash_promotion_delete_error', 'Não foi possível remover a promoção.'));
        $this->redirectToRoute('billing/index');
    }

    public function saveAnnouncement(): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $result = $service->saveAnnouncement([
            'id' => (int) $this->request->post('id', 0),
            'title' => (string) $this->request->post('title', ''),
            'message' => (string) $this->request->post('message', ''),
            'announcement_type' => (string) $this->request->post('announcement_type', 'informativo'),
            'starts_at' => (string) $this->request->post('starts_at', ''),
            'ends_at' => (string) $this->request->post('ends_at', ''),
            'status' => $this->request->post('status', ''),
        ]);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_announcement_saved', 'Comunicado salvo com sucesso.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_announcement_error', 'Não foi possível salvar o comunicado.')));
        $this->redirectToRoute('billing/index');
    }

    public function deleteAnnouncement(int $announcementId): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $deleted = $service->deleteAnnouncement($announcementId);

        if ($deleted) {
            flash('success', $this->t('billing.flash_announcement_deleted', 'Comunicado removido com sucesso.'));
            $this->redirectToRoute('billing/index');
        }

        flash('error', $this->t('billing.flash_announcement_delete_error', 'Não foi possível remover o comunicado.'));
        $this->redirectToRoute('billing/index');
    }

    public function savePaymentSettings(): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $service = new SubscriptionService($this->registry);
        $result = $service->saveBillingSettings([
            'currency' => (string) $this->request->post('currency', 'BRL'),
            'receiver_name' => (string) $this->request->post('receiver_name', ''),
            'receiver_document' => (string) $this->request->post('receiver_document', ''),
            'receiver_bank' => (string) $this->request->post('receiver_bank', ''),
            'receiver_agency' => (string) $this->request->post('receiver_agency', ''),
            'receiver_account' => (string) $this->request->post('receiver_account', ''),
            'receiver_account_type' => (string) $this->request->post('receiver_account_type', 'checking'),
            'receiver_pix_key' => (string) $this->request->post('receiver_pix_key', ''),
            'receiver_email' => (string) $this->request->post('receiver_email', ''),
            'validation_mode' => (string) $this->request->post('validation_mode', 'automatic'),
            'mock_auto_approve' => $this->request->post('mock_auto_approve', ''),
            'validation_notes' => (string) $this->request->post('validation_notes', ''),
            'method_pix' => $this->request->post('method_pix', ''),
            'method_boleto' => $this->request->post('method_boleto', ''),
            'method_card' => $this->request->post('method_card', ''),
            'method_transfer' => $this->request->post('method_transfer', ''),
        ]);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_settings_saved', 'Configurações de pagamento salvas.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_settings_error', 'Não foi possível salvar as configurações.')));
        $this->redirectToRoute('billing/index');
    }

    public function approvePayment(int $transactionId): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $adminId = (int) ($this->auth->user()['id'] ?? 0);
        $note = trim((string) $this->request->post('validation_note', ''));

        $service = new SubscriptionService($this->registry);
        $result = $service->approvePaymentTransaction($transactionId, $adminId, $note);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_validation_approved', 'Pagamento aprovado.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_validation_error', 'Não foi possível aprovar o pagamento.')));
        $this->redirectToRoute('billing/index');
    }

    public function rejectPayment(int $transactionId): void
    {
        $this->boot('admin.billing');
        $this->requirePostAndCsrf();

        $adminId = (int) ($this->auth->user()['id'] ?? 0);
        $reason = trim((string) $this->request->post('rejection_reason', ''));

        $service = new SubscriptionService($this->registry);
        $result = $service->rejectPaymentTransaction($transactionId, $adminId, $reason);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? $this->t('billing.flash_validation_rejected', 'Pagamento rejeitado.')));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? $this->t('billing.flash_validation_error', 'Não foi possível rejeitar o pagamento.')));
        $this->redirectToRoute('billing/index');
    }
}
