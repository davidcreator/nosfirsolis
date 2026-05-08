<?php

namespace System\Library;

use System\Engine\Config;
use System\Engine\Registry;

class SubscriptionService
{
    use TemporalClockTrait;
    use SubscriptionServiceOperationsTrait;
    use SubscriptionServiceContextTrait;
    use SubscriptionServiceBillingInternalsTrait;
    use SubscriptionServicePlanPersistenceTrait;

    private bool $ensured = false;
    private bool $schemaAvailable = true;
    private array $contextCache = [];
    private array $tableCache = [];
    private array $settingsCache = [];

    public function __construct(private readonly Registry $registry)
    {
    }

    private function isProductionEnvironment(): bool
    {
        $environment = strtolower(trim((string) $this->config()?->get('app.environment', 'production')));
        return in_array($environment, ['production', 'prod', 'live'], true);
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function config(): ?Config
    {
        $config = $this->registry->get('config');
        return $config instanceof Config ? $config : null;
    }
}
