<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Promotion\Models\Discount;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class MatchOrderEvent
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public Discount $discount,
    ) {}
}
