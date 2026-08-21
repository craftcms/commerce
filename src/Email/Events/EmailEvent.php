<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Events;

use CraftCms\Commerce\Email\Models\Email;

class EmailEvent
{
    public function __construct(
        public Email $email,
        public bool $isNew = false,
    ) {
    }
}
