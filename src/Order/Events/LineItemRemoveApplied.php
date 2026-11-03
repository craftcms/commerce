<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

/**
 * Dispatched after a removed line item is deleted when its order is saved.
 */
class LineItemRemoveApplied
{
    public function __construct(
        public Order $order,
        public LineItem $lineItem,
    ) {
    }
}
