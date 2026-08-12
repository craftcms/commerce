<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\Discount;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class DiscountAdjustmentsEvent
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public Discount $discount,
        public array $adjustments,
    ) {}
}
