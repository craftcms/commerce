<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\elements\Order;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Promotion\Models\Discount;

class MatchOrderEvent
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public Discount $discount,
    ) {
    }
}
