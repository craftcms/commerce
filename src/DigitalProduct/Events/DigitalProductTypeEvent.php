<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Events;

use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;

abstract class DigitalProductTypeEvent
{
    public function __construct(
        public DigitalProductType $digitalProductType,
        public bool $isNew = false,
    ) {
    }
}
