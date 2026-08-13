<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

class OrderLineItemsRefreshEvent
{
    public function __construct(
        public array $lineItems,
        public bool $recalculate = false,
    ) {
    }
}
