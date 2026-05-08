<?php

namespace Client\Model;

use System\Engine\Model;

class PlannerModel extends Model
{
    use PlannerModelPlanLifecycleTrait;
    use PlannerModelStatusAutomationTrait;
    use PlannerModelCalendarTrait;

    private ?bool $extraEventsTableReady = null;
    private ?bool $calendarColorsTableReady = null;
}
