<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

use yii\base\Event;

class TaxIdValidatorsEvent extends Event
{
    public function __construct(
        public array $validators = [],
    ) {
    }
}
