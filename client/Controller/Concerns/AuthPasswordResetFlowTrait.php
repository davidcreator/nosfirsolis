<?php

namespace Client\Controller\Concerns;

trait AuthPasswordResetFlowTrait
{
    use AuthPasswordResetRequestTrait;
    use AuthPasswordResetTokenTrait;
    use AuthEmailRecoveryFlowTrait;
}
