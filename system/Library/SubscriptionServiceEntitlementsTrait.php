<?php

namespace System\Library;

trait SubscriptionServiceEntitlementsTrait
{
    public function evaluateFeature(int $userId, string $featureKey): array
    {
        $featureKey = strtolower(trim($featureKey));
        $context = $this->contextForUser($userId);
        $allowed = (bool) ($context['features'][$featureKey] ?? true);

        if ($allowed) {
            return ['allowed' => true, 'message' => ''];
        }

        $catalog = $this->featureDefinitions();
        $label = (string) ($catalog[$featureKey]['label'] ?? $featureKey);

        return [
            'allowed' => false,
            'message' => sprintf(
                'O recurso "%s" nao esta disponivel no plano %s. Faca upgrade em Planos e Faturamento.',
                $label,
                (string) ($context['plan']['name'] ?? 'atual')
            ),
        ];
    }

    public function evaluateQuota(int $userId, string $metricKey, int $increment = 1): array
    {
        $metricKey = strtolower(trim($metricKey));
        $increment = max(1, $increment);
        $context = $this->contextForUser($userId);

        $metricMap = [
            'max_editorial_plans_per_month' => ['usage_key' => 'editorial_plans', 'label' => 'planos editoriais no mes'],
            'max_social_publications_per_month' => ['usage_key' => 'social_publications', 'label' => 'publicacoes sociais no mes'],
            'max_social_accounts' => ['usage_key' => 'social_accounts', 'label' => 'contas sociais conectadas'],
            'max_tracking_links_per_month' => ['usage_key' => 'tracking_links', 'label' => 'links de rastreamento no mes'],
            'max_calendar_extra_events_per_month' => ['usage_key' => 'calendar_extra_events', 'label' => 'eventos extras de calendario no mes'],
        ];

        if (!isset($metricMap[$metricKey])) {
            return ['allowed' => true, 'message' => ''];
        }

        $usageKey = (string) $metricMap[$metricKey]['usage_key'];
        $label = (string) $metricMap[$metricKey]['label'];
        $current = (int) ($context['usage'][$usageKey] ?? 0);
        $limit = $this->intLimit((array) ($context['limits'] ?? []), $metricKey, -1);

        if ($limit < 0) {
            return ['allowed' => true, 'message' => ''];
        }

        if (($current + $increment) <= $limit) {
            return ['allowed' => true, 'message' => ''];
        }

        return [
            'allowed' => false,
            'message' => sprintf(
                'Limite do plano %s atingido para %s: %d/%d. Faca upgrade em Planos e Faturamento.',
                (string) ($context['plan']['name'] ?? 'atual'),
                $label,
                $current,
                $limit
            ),
        ];
    }

    public function featureCatalog(): array
    {
        $catalog = [];
        foreach ($this->featureDefinitions() as $key => $meta) {
            $catalog[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
            ];
        }

        return $catalog;
    }
}
