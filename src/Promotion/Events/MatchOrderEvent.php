<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Elements\Order;
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
