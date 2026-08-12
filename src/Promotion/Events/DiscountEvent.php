<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\models\Discount;

class DiscountEvent
{
    public function __construct(
        public Discount $discount,
        public bool $isNew,
    ) {}
}
