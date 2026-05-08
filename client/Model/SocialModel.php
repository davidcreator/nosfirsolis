<?php

namespace Client\Model;

use System\Engine\Model;

class SocialModel extends Model
{
    use SocialModelConnectionsTrait;
    use SocialModelDraftsAndPresetsTrait;
    use SocialModelSchemaTrait;

    private bool $ensured = false;
    private bool $schemaAvailable = true;
}
