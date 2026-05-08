<?php

namespace System\Library;

trait SubscriptionServiceBillingSettingsTrait
{
    public function billingSettings(): array
    {
        if (isset($this->settingsCache['_billing_settings']) && is_array($this->settingsCache['_billing_settings'])) {
            return $this->settingsCache['_billing_settings'];
        }

        $configuredCurrency = strtoupper(trim((string) $this->config()?->get('integrations.billing.currency', 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $configuredCurrency)) {
            $configuredCurrency = 'BRL';
        }

        $validationMode = strtolower(trim((string) $this->settingValue('billing.validation_mode', 'automatic')));
        if (!in_array($validationMode, ['automatic', 'manual'], true)) {
            $validationMode = 'automatic';
        }

        $settings = [
            'currency' => strtoupper(trim((string) $this->settingValue('billing.currency', $configuredCurrency))),
            'receiver_name' => (string) $this->settingValue('billing.receiver_name', ''),
            'receiver_document' => (string) $this->settingValue('billing.receiver_document', ''),
            'receiver_bank' => (string) $this->settingValue('billing.receiver_bank', ''),
            'receiver_agency' => (string) $this->settingValue('billing.receiver_agency', ''),
            'receiver_account' => (string) $this->settingValue('billing.receiver_account', ''),
            'receiver_account_type' => (string) $this->settingValue('billing.receiver_account_type', 'checking'),
            'receiver_pix_key' => (string) $this->settingValue('billing.receiver_pix_key', ''),
            'receiver_email' => (string) $this->settingValue('billing.receiver_email', ''),
            'validation_mode' => $validationMode,
            'mock_auto_approve' => $this->truthy($this->settingValue('billing.mock_auto_approve', '1')),
            'validation_notes' => (string) $this->settingValue('billing.validation_notes', ''),
            'methods' => [
                'pix' => $this->truthy($this->settingValue('billing.method.pix', '1')),
                'boleto' => $this->truthy($this->settingValue('billing.method.boleto', '1')),
                'card' => $this->truthy($this->settingValue('billing.method.card', '1')),
                'transfer' => $this->truthy($this->settingValue('billing.method.transfer', '0')),
            ],
        ];

        if (!preg_match('/^[A-Z]{3}$/', $settings['currency'])) {
            $settings['currency'] = $configuredCurrency;
        }

        $this->settingsCache['_billing_settings'] = $settings;
        return $settings;
    }

    public function saveBillingSettings(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel salvar configuracoes de pagamento.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de billing indisponivel para salvar configuracoes.'];
        }
        if (!$this->tableExists('settings')) {
            return ['success' => false, 'message' => 'Tabela de configuracoes indisponivel para salvar billing.'];
        }

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'BRL')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'BRL';
        }

        $validationMode = strtolower(trim((string) ($payload['validation_mode'] ?? 'automatic')));
        if (!in_array($validationMode, ['automatic', 'manual'], true)) {
            $validationMode = 'automatic';
        }

        $methodPix = $this->truthy($payload['method_pix'] ?? 0);
        $methodBoleto = $this->truthy($payload['method_boleto'] ?? 0);
        $methodCard = $this->truthy($payload['method_card'] ?? 0);
        $methodTransfer = $this->truthy($payload['method_transfer'] ?? 0);
        if (!$methodPix && !$methodBoleto && !$methodCard && !$methodTransfer) {
            $methodPix = true;
        }

        $this->saveSettingValue('billing.currency', $currency);
        $this->saveSettingValue('billing.receiver_name', mb_substr(trim((string) ($payload['receiver_name'] ?? '')), 0, 140));
        $this->saveSettingValue('billing.receiver_document', mb_substr(trim((string) ($payload['receiver_document'] ?? '')), 0, 80));
        $this->saveSettingValue('billing.receiver_bank', mb_substr(trim((string) ($payload['receiver_bank'] ?? '')), 0, 120));
        $this->saveSettingValue('billing.receiver_agency', mb_substr(trim((string) ($payload['receiver_agency'] ?? '')), 0, 40));
        $this->saveSettingValue('billing.receiver_account', mb_substr(trim((string) ($payload['receiver_account'] ?? '')), 0, 60));
        $this->saveSettingValue('billing.receiver_account_type', mb_substr(trim((string) ($payload['receiver_account_type'] ?? 'checking')), 0, 40));
        $this->saveSettingValue('billing.receiver_pix_key', mb_substr(trim((string) ($payload['receiver_pix_key'] ?? '')), 0, 120));
        $this->saveSettingValue('billing.receiver_email', mb_substr(trim((string) ($payload['receiver_email'] ?? '')), 0, 140));
        $this->saveSettingValue('billing.validation_mode', $validationMode);
        $this->saveSettingValue('billing.mock_auto_approve', $this->truthy($payload['mock_auto_approve'] ?? 0) ? '1' : '0');
        $this->saveSettingValue('billing.validation_notes', mb_substr(trim((string) ($payload['validation_notes'] ?? '')), 0, 2000));
        $this->saveSettingValue('billing.method.pix', $methodPix ? '1' : '0');
        $this->saveSettingValue('billing.method.boleto', $methodBoleto ? '1' : '0');
        $this->saveSettingValue('billing.method.card', $methodCard ? '1' : '0');
        $this->saveSettingValue('billing.method.transfer', $methodTransfer ? '1' : '0');

        unset($this->settingsCache['_billing_settings']);
        return ['success' => true, 'message' => 'Configuracoes de pagamento atualizadas com sucesso.'];
    }

    public function paymentMethodsForCheckout(): array
    {
        $settings = $this->billingSettings();
        $methods = (array) ($settings['methods'] ?? []);

        $map = [
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'card' => 'Cartao',
            'transfer' => 'Transferencia',
        ];

        $available = [];
        foreach ($map as $key => $label) {
            if (!empty($methods[$key])) {
                $available[] = ['key' => $key, 'label' => $label];
            }
        }

        if ($available === []) {
            $available[] = ['key' => 'pix', 'label' => 'PIX'];
        }

        return $available;
    }
}
