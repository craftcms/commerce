<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Events;

use craft\commerce\models\Email;

class EmailEvent
{
    public Email $email;
    public bool $isNew = false;
}
