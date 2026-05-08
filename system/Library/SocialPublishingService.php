<?php

namespace System\Library;

use System\Engine\Registry;

class SocialPublishingService
{
    use TemporalClockTrait;
    use SocialPublishingSchemaTrait;
    use SocialPublishingQueueTrait;
    use SocialPublishingDeliveryTrait;

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
