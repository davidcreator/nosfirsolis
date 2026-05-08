<?php

namespace System\Library;

use System\Engine\Registry;

class CampaignTrackingService
{
    use TemporalClockTrait;
    use CampaignTrackingSchemaTrait;
    use CampaignTrackingOperationsTrait;

    private bool $ensured = false;
    private bool $schemaAvailable = true;

    public function __construct(private readonly Registry $registry)
    {
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        return $db instanceof Database ? $db : null;
    }

    private function config(): ?\System\Engine\Config
    {
        $config = $this->registry->get('config');
        return $config instanceof \System\Engine\Config ? $config : null;
    }
}
