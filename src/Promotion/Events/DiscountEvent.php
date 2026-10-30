<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Commerce\Promotion\Data\Discount;

class DiscountEvent
{
    public function __construct(
        public Discount $discount,
        public bool $isNew,
    ) {
    }
}
