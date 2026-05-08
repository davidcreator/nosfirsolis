<?php

namespace System\Library;

use System\Engine\Registry;

class FeatureFlagService
{
    use TemporalClockTrait;
    use FeatureFlagSchemaTrait;
    use FeatureFlagCrudTrait;
    use FeatureFlagResolutionTrait;

    private bool $ensured = false;
    private bool $schemaAvailable = true;
    private bool $hierarchyColumnChecked = false;
    private bool $hierarchyColumnAvailable = false;

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
