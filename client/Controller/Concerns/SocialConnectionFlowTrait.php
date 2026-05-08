<?php

namespace Client\Controller\Concerns;

trait SocialConnectionFlowTrait
{
    use SocialConnectionOAuthFlowTrait;
    use SocialConnectionManualFlowTrait;
    use SocialConnectionSupportTrait;
}
