<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

class TaxIdValidatorsEvent
{
    public function __construct(
        public array $validators = [],
    ) {}
}
