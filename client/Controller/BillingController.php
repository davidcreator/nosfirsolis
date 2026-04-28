<?php

namespace Client\Controller;

use System\Library\SubscriptionService;

class BillingController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.dashboard');

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $service = new SubscriptionService($this->registry);
        $service->ensureUserSubscription($userId);

        $this->render('billing/index', [
            'title' => $this->t('billing.title_index', 'Planos e Faturamento'),
            'subscription_context' => $service->contextForUser($userId),
            'available_plans' => $service->publicPlans(),
            'invoices' => $service->recentInvoices($userId, 30),
            'payment_methods' => $service->paymentMethodsForCheckout(),
            'billing_announcements' => $service->activeAnnouncements(10),
        ]);
    }

    public function subscribe(): void
    {
        $this->boot('client.dashboard');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $planSlug = trim((string) $this->request->post('plan_slug', ''));
        $paymentMethod = trim((string) $this->request->post('payment_method', 'pix'));
        if ($paymentMethod === '') {
            $paymentMethod = 'pix';
        }

        $service = new SubscriptionService($this->registry);
        $result = $service->changePlan($userId, $planSlug, $paymentMethod);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? 'Plano atualizado com sucesso.'));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? 'Não foi possível atualizar o plano.'));
        $this->redirectToRoute('billing/index');
    }

    public function payInvoice(int $invoiceId): void
    {
        $this->boot('client.dashboard');
        $this->ensurePostWithCsrf();

        if ($invoiceId <= 0) {
            flash('error', $this->t('billing.flash_invoice_invalid', 'Fatura inválida para pagamento.'));
            $this->redirectToRoute('billing/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $paymentMethod = trim((string) $this->request->post('payment_method', 'pix'));
        if ($paymentMethod === '') {
            $paymentMethod = 'pix';
        }

        $service = new SubscriptionService($this->registry);
        $result = $service->payInvoice($userId, $invoiceId, $paymentMethod);

        if (!empty($result['success'])) {
            flash('success', (string) ($result['message'] ?? 'Pagamento confirmado com sucesso.'));
            $this->redirectToRoute('billing/index');
        }

        flash('error', (string) ($result['message'] ?? 'Não foi possível processar o pagamento.'));
        $this->redirectToRoute('billing/index');
    }
}
