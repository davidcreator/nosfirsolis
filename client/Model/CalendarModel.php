<?php

namespace Client\Model;

use System\Engine\Model;

class CalendarModel extends Model
{
    use CalendarModelEventsTrait;
    use CalendarModelBaseEventsTrait;
}
