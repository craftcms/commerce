<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Promotion\Models\Discount;

class MatchLineItemEvent
{
    use ValidatableEvent;

    public function __construct(
        public LineItem $lineItem,
        public Discount $discount,
    ) {
    }
}
