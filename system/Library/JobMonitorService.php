<?php

namespace System\Library;

use System\Engine\Registry;

class JobMonitorService
{
    use TemporalClockTrait;
    use JobMonitorSchemaTrait;
    use JobMonitorOperationsTrait;

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
}
