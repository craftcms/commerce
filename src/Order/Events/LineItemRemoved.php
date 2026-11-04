<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

/**
 * Dispatched after a line item is removed from an order.
 */
class LineItemRemoved
{
    public function __construct(
        public Order $order,
        public LineItem $lineItem,
    ) {
    }
}
