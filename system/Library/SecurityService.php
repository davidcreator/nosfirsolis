<?php

namespace System\Library;

use System\Engine\Registry;

class SecurityService
{
    use TemporalClockTrait;
    use SecurityRuntimeTrait;
    use SecurityAuthAuditTrait;

    private bool $ensured = false;
    private ?bool $securityTablesAvailable = null;

    public function __construct(private readonly Registry $registry)
    {
    }
}
